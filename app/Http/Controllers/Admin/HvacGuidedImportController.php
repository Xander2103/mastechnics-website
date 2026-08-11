<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacMappingProfile;
use App\Services\Hvac\HvacCsvImporter;
use App\Services\Hvac\Import\ColumnMappingSuggester;
use App\Services\Hvac\Import\HeaderRowDetector;
use App\Services\Hvac\Import\HvacGuidedImportService;
use App\Services\Hvac\Import\TabularFileReader;
use App\Services\Hvac\Import\XlsxReadException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Guided mapping import for arbitrary supplier files (CSV/XLSX):
 * upload → worksheet → header row → column mapping → normalized preview →
 * explicit confirmation. State lives in the cache under a token; the
 * uploaded file sits in storage/app/hvac-imports and is deleted after
 * confirmation, cancellation or expiry. Nothing is written to the catalog
 * before the final confirm step.
 */
class HvacGuidedImportController extends Controller
{
    private const CACHE_TTL = 3600;
    private const STORAGE_DIR = 'hvac-imports';
    private const MAX_ROWS = 20000;
    private const MAX_COLS = 64;

    public function __construct(
        private readonly TabularFileReader $files,
        private readonly HeaderRowDetector $headerDetector,
        private readonly ColumnMappingSuggester $suggester,
        private readonly HvacGuidedImportService $guided,
        private readonly HvacCsvImporter $importer,
    ) {
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file'       => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:' . HvacImportController::maxUploadKb()],
            'mode'       => ['required', 'in:create_and_update,create_only,update_only'],
            'profile_id' => ['nullable', 'integer', 'exists:hvac_mapping_profiles,id'],
        ], [
            'file.max'        => 'Het bestand is groter dan de maximale bestandsgrootte van ' . (int) config('hvac.import.max_upload_mb', 25) . ' MB.',
            'file.extensions' => 'Alleen .csv, .txt of .xlsx-bestanden worden aanvaard.',
        ]);

        $this->cleanupExpiredFiles();

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $token = Str::random(40);

        Storage::disk('local')->putFileAs(self::STORAGE_DIR, $file, "{$token}.{$extension}");
        $path = Storage::disk('local')->path(self::STORAGE_DIR . "/{$token}.{$extension}");

        // Structural validation up front: a broken/macro/bomb file never
        // enters the wizard.
        try {
            $sheets = $this->files->sheets($path, $extension);
        } catch (XlsxReadException $e) {
            Storage::disk('local')->delete(self::STORAGE_DIR . "/{$token}.{$extension}");

            return redirect()->route('admin.hvac.import.index')->withErrors(['guided_file' => $e->getMessage()]);
        }

        $state = [
            'extension'       => $extension,
            'original_name'   => $file->getClientOriginalName(),
            'mode'            => $request->string('mode')->toString(),
            'step'            => 'sheet',
            'sheet'           => null,
            'header_row'      => null,
            'column_map'      => null,
            'decimal_format'  => 'auto',
            'profile_id'      => null,
            'profile_warning' => null,
        ];

        $visibleSheets = array_values(array_filter($sheets, fn (array $s) => ! $s['hidden']));
        if (count($visibleSheets) === 1) {
            $state['sheet'] = $visibleSheets[0]['name'];
            $state['step'] = 'header';
        }

        // Try the saved profile; on any mismatch fall back to the manual
        // wizard with a clear warning — never import blindly.
        if ($request->filled('profile_id')) {
            $profile = HvacMappingProfile::where('is_active', true)->find($request->integer('profile_id'));
            if ($profile !== null) {
                $match = $this->guided->matchProfile(
                    $profile,
                    $sheets,
                    fn (string $sheet) => $this->readRows($path, $extension, $sheet, 40)['rows']
                );

                if ($match['ok']) {
                    $state['profile_id'] = $profile->id;
                    $state['sheet'] = $match['sheet'];
                    $state['header_row'] = $match['header_row'];
                    $state['column_map'] = $match['column_map'];
                    $state['decimal_format'] = $profile->decimal_format;
                    $state['step'] = 'preview';
                } else {
                    $state['profile_warning'] = 'Het bestand komt niet meer overeen met profiel "'
                        . $profile->name . '": ' . implode(' ', $match['problems'])
                        . ' Controleer de stappen hieronder handmatig.';
                }
            }
        }

        Cache::put("hvac-guided:{$token}", $state, self::CACHE_TTL);

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    public function step(string $token): View|RedirectResponse
    {
        $state = $this->state($token);
        if ($state === null) {
            return $this->expired();
        }

        $path = $this->filePath($token, $state['extension']);
        if ($path === null) {
            return $this->expired($token);
        }

        return match ($state['step']) {
            'sheet'   => $this->sheetView($token, $state, $path),
            'header'  => $this->headerView($token, $state, $path),
            'mapping' => $this->mappingView($token, $state, $path),
            default   => $this->previewView($token, $state, $path),
        };
    }

    public function chooseSheet(Request $request, string $token): RedirectResponse
    {
        $state = $this->state($token);
        $path = $state !== null ? $this->filePath($token, $state['extension']) : null;
        if ($state === null || $path === null) {
            return $this->expired($token);
        }

        $names = array_column($this->files->sheets($path, $state['extension']), 'name');
        $request->validate(['sheet' => ['required', 'string', 'in:' . implode(',', $names)]]);

        $state['sheet'] = $request->string('sheet')->toString();
        $state['step'] = 'header';
        $state['header_row'] = null;
        $state['column_map'] = null;
        Cache::put("hvac-guided:{$token}", $state, self::CACHE_TTL);

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    public function chooseHeader(Request $request, string $token): RedirectResponse
    {
        $state = $this->state($token);
        $path = $state !== null ? $this->filePath($token, $state['extension']) : null;
        if ($state === null || $path === null) {
            return $this->expired($token);
        }

        $request->validate(['header_row' => ['required', 'integer', 'min:1', 'max:1000']]);

        $state['header_row'] = $request->integer('header_row') - 1;
        $state['step'] = 'mapping';
        $state['column_map'] = null;
        Cache::put("hvac-guided:{$token}", $state, self::CACHE_TTL);

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    public function saveMapping(Request $request, string $token): RedirectResponse
    {
        $state = $this->state($token);
        $path = $state !== null ? $this->filePath($token, $state['extension']) : null;
        if ($state === null || $path === null) {
            return $this->expired($token);
        }

        $data = $request->validate([
            'mapping'            => ['required', 'array'],
            'mapping.*'          => ['nullable', 'string', 'in:' . implode(',', HvacCsvImporter::COLUMNS)],
            'decimal_format'     => ['required', 'in:auto,comma,point'],
            'save_profile'       => ['nullable', 'boolean'],
            'profile_name'       => ['required_if:save_profile,1', 'nullable', 'string', 'max:120'],
            'profile_supplier'   => ['required_if:save_profile,1', 'nullable', 'string', 'max:120'],
            'profile_sheet_pattern' => ['nullable', 'string', 'max:120'],
        ]);

        $columnMap = [];
        foreach ($data['mapping'] as $col => $field) {
            if ($field !== null && $field !== '') {
                $columnMap[(int) $col] = $field;
            }
        }

        // Never accept an ambiguous mapping: each internal field at most once,
        // and every required field must be present.
        $duplicates = array_diff_assoc($columnMap, array_unique($columnMap));
        if ($duplicates !== []) {
            return back()->withErrors([
                'mapping' => 'Elk intern veld mag maar aan één kolom gekoppeld worden (dubbel: ' . implode(', ', array_unique($duplicates)) . ').',
            ])->withInput();
        }

        $missing = array_diff(HvacCsvImporter::REQUIRED_COLUMNS, $columnMap);
        if ($missing !== []) {
            return back()->withErrors([
                'mapping' => 'Verplichte velden ontbreken in de koppeling: ' . implode(', ', $missing) . '.',
            ])->withInput();
        }

        $state['column_map'] = $columnMap;
        $state['decimal_format'] = $data['decimal_format'];
        $state['step'] = 'preview';

        if ($request->boolean('save_profile')) {
            $headerCells = $this->headerCells($path, $state);
            $sourceMap = [];
            foreach ($columnMap as $col => $field) {
                $sourceMap[(string) ($headerCells[$col] ?? "kolom {$col}")] = $field;
            }

            HvacMappingProfile::updateOrCreate(
                ['name' => trim((string) $data['profile_name'])],
                [
                    'supplier_name'          => trim((string) $data['profile_supplier']),
                    'worksheet_name_pattern' => trim((string) ($data['profile_sheet_pattern'] ?? '')) ?: null,
                    'header_row'             => $state['header_row'] + 1,
                    'column_map'             => $sourceMap,
                    'decimal_format'         => $data['decimal_format'],
                    'is_active'              => true,
                    'created_by'             => (string) session('admin_user_email'),
                ]
            );
        }

        Cache::put("hvac-guided:{$token}", $state, self::CACHE_TTL);

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    public function confirm(string $token): RedirectResponse
    {
        $state = $this->state($token);
        $path = $state !== null ? $this->filePath($token, $state['extension']) : null;
        if ($state === null || $path === null || $state['step'] !== 'preview') {
            return $this->expired($token);
        }

        $validated = $this->validatedRows($path, $state);
        $result = $this->importer->import($validated['rows'], $state['mode']);

        $errorRows = array_values(array_filter($validated['rows'], fn ($r) => $r['errors'] !== []));
        $reportToken = null;
        if ($errorRows !== []) {
            $reportToken = Str::random(40);
            Cache::put("hvac-import-errors:{$reportToken}", $errorRows, self::CACHE_TTL);
        }

        $this->forget($token, $state['extension']);

        return redirect()->route('admin.hvac.import.index')->with([
            'success'            => 'hvac_import_done',
            'import_result'      => $result + ['errors' => count($errorRows)],
            'import_error_token' => $reportToken,
        ]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $state = $this->state($token);
        if ($state !== null) {
            $this->forget($token, $state['extension']);
        }

        return redirect()->route('admin.hvac.import.index');
    }

    /** Change step backwards (links in the wizard). */
    public function backTo(Request $request, string $token): RedirectResponse
    {
        $state = $this->state($token);
        if ($state === null) {
            return $this->expired($token);
        }

        $step = $request->string('step')->toString();
        if (in_array($step, ['sheet', 'header', 'mapping'], true)) {
            $state['step'] = $step;
            Cache::put("hvac-guided:{$token}", $state, self::CACHE_TTL);
        }

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    public function toggleProfile(HvacMappingProfile $profile): RedirectResponse
    {
        $profile->update(['is_active' => ! $profile->is_active]);

        return redirect()->route('admin.hvac.import.index');
    }

    // ── Views per step ───────────────────────────────────────────────────────

    private function sheetView(string $token, array $state, string $path): View
    {
        return view('admin.hvac.imports.guided.sheet', [
            'token'  => $token,
            'state'  => $state,
            'sheets' => $this->files->sheets($path, $state['extension']),
        ]);
    }

    private function headerView(string $token, array $state, string $path): View
    {
        $preview = $this->readRows($path, $state['extension'], $state['sheet'], 20);
        $detected = $this->headerDetector->detect($preview['rows']);

        return view('admin.hvac.imports.guided.header', [
            'token'       => $token,
            'state'       => $state,
            'rows'        => $preview['rows'],
            'hasFormulas' => $preview['has_formulas'],
            'detected'    => $detected,
            'selected'    => $state['header_row'] ?? $detected['row'],
        ]);
    }

    private function mappingView(string $token, array $state, string $path): View
    {
        $preview = $this->readRows($path, $state['extension'], $state['sheet'], $state['header_row'] + 6);
        $headerCells = $preview['rows'][$state['header_row']] ?? [];
        $suggestions = $this->suggester->suggest($headerCells);

        $samples = [];
        foreach ($headerCells as $col => $_) {
            $samples[$col] = [];
            for ($r = $state['header_row'] + 1; $r <= $state['header_row'] + 3; $r++) {
                $samples[$col][] = $preview['rows'][$r][$col] ?? null;
            }
        }

        $current = $state['column_map'] ?? [];
        $preselected = [];
        foreach ($headerCells as $col => $_) {
            if (isset($current[$col])) {
                $preselected[$col] = $current[$col];
            } elseif (($suggestions[$col]['confidence'] ?? 'none') === 'exact') {
                $preselected[$col] = $suggestions[$col]['field'];
            } else {
                $preselected[$col] = null;
            }
        }

        return view('admin.hvac.imports.guided.mapping', [
            'token'        => $token,
            'state'        => $state,
            'headerCells'  => $headerCells,
            'suggestions'  => $suggestions,
            'samples'      => $samples,
            'preselected'  => $preselected,
            'fields'       => HvacCsvImporter::COLUMNS,
            'required'     => HvacCsvImporter::REQUIRED_COLUMNS,
        ]);
    }

    private function previewView(string $token, array $state, string $path): View
    {
        $validated = $this->validatedRows($path, $state);
        $rows = collect($validated['rows']);

        return view('admin.hvac.imports.guided.preview', [
            'token'        => $token,
            'state'        => $state,
            'rows'         => $rows->take(10),
            'totalRows'    => $rows->count(),
            'createCount'  => $rows->where('action', 'create')->whereStrict('errors', [])->count(),
            'updateCount'  => $rows->where('action', 'update')->whereStrict('errors', [])->count(),
            'errorCount'   => $rows->filter(fn ($r) => $r['errors'] !== [])->count(),
            'truncated'    => $validated['truncated'],
            'profile'      => $state['profile_id'] !== null ? HvacMappingProfile::find($state['profile_id']) : null,
            'columnLabels' => $this->mappingSummary($path, $state),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function state(string $token): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        return Cache::get("hvac-guided:{$token}");
    }

    private function filePath(string $token, string $extension): ?string
    {
        $relative = self::STORAGE_DIR . "/{$token}.{$extension}";

        return Storage::disk('local')->exists($relative)
            ? Storage::disk('local')->path($relative)
            : null;
    }

    private function readRows(string $path, string $extension, ?string $sheet, int $maxRows): array
    {
        return $this->files->rows($path, $extension, $sheet, $maxRows, self::MAX_COLS);
    }

    /** @return array{rows: array, truncated: bool} */
    private function validatedRows(string $path, array $state): array
    {
        $data = $this->readRows($path, $state['extension'], $state['sheet'], self::MAX_ROWS);
        $rawRows = $this->guided->normalizeRows(
            $data['rows'],
            (int) $state['header_row'],
            (array) $state['column_map'],
            (string) $state['decimal_format']
        );

        return [
            'rows'      => $this->importer->validateRows($rawRows),
            'truncated' => $data['truncated'],
        ];
    }

    /** @return array<int, string|null> */
    private function headerCells(string $path, array $state): array
    {
        $preview = $this->readRows($path, $state['extension'], $state['sheet'], $state['header_row'] + 1);

        return $preview['rows'][$state['header_row']] ?? [];
    }

    /** @return array<string, string> source header => internal field */
    private function mappingSummary(string $path, array $state): array
    {
        $headerCells = $this->headerCells($path, $state);
        $summary = [];
        foreach ((array) $state['column_map'] as $col => $field) {
            $summary[(string) ($headerCells[$col] ?? 'kolom ' . ($col + 1))] = $field;
        }

        return $summary;
    }

    private function expired(?string $token = null): RedirectResponse
    {
        if ($token !== null) {
            foreach (['csv', 'txt', 'xlsx'] as $ext) {
                Storage::disk('local')->delete(self::STORAGE_DIR . "/{$token}.{$ext}");
            }
            Cache::forget("hvac-guided:{$token}");
        }

        return redirect()->route('admin.hvac.import.index')
            ->withErrors(['guided_file' => 'De begeleide import is verlopen of geannuleerd. Upload het bestand opnieuw.']);
    }

    private function forget(string $token, string $extension): void
    {
        Storage::disk('local')->delete(self::STORAGE_DIR . "/{$token}.{$extension}");
        Cache::forget("hvac-guided:{$token}");
    }

    private function cleanupExpiredFiles(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::STORAGE_DIR) as $file) {
            if ($disk->lastModified($file) < now()->subHours(2)->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
