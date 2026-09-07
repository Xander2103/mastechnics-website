<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacMappingProfile;
use App\Services\Hvac\HvacCompatibilityCsvImporter;
use App\Services\Hvac\HvacCsvImporter;
use App\Services\Hvac\Import\TabularFileReader;
use App\Services\Hvac\Import\XlsxReadException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HvacImportController extends Controller
{
    private const CACHE_TTL = 3600;

    private const UNREADABLE_FILE = 'Het bestand kon niet gelezen worden. Controleer of het een geldig CSV- of Excel-bestand is en bewaar het eventueel opnieuw.';

    private const ALREADY_IMPORTED = 'Deze import werd al uitgevoerd; de producten zijn niet nogmaals geïmporteerd. Upload het bestand opnieuw als u het nogmaals wilt importeren.';

    /**
     * Application-level upload limit in kilobytes, from
     * config('hvac.import.max_upload_mb') (HVAC_IMPORT_MAX_MB). The effective
     * limit is the LOWEST of this value, PHP's upload_max_filesize /
     * post_max_size and the web server's body limit — see
     * docs/hvac/import-deployment.md.
     */
    public static function maxUploadKb(): int
    {
        return max(1, (int) config('hvac.import.max_upload_mb', 25)) * 1024;
    }

    /** @return array<string, string> */
    private static function uploadMessages(): array
    {
        $maxMb = max(1, (int) config('hvac.import.max_upload_mb', 25));

        return [
            'file.max'        => "Het bestand is groter dan de maximale bestandsgrootte van {$maxMb} MB.",
            'file.extensions' => 'Alleen .csv, .txt of .xlsx-bestanden worden aanvaard.',
        ];
    }

    /**
     * Maximum number of sheet rows an import reads (config
     * hvac.import.max_rows, default 100 000). Larger files are refused with
     * a clear message instead of being silently cut off.
     */
    public static function maxRows(): int
    {
        return max(1, (int) config('hvac.import.max_rows', 100000));
    }

    /**
     * File contents as CSV text. XLSX files (template layout: headers on the
     * first filled row of the first visible sheet) are converted through the
     * safe workbook reader — formulas are never evaluated, macro files are
     * rejected. Files with a genuinely different layout belong in the guided
     * mapping import instead.
     *
     * @throws XlsxReadException when the workbook is unreadable OR has more
     *                           rows than maxRows() (never truncate silently)
     */
    private function contentsAsCsv(Request $request): string
    {
        $file = $request->file('file');

        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            return (string) file_get_contents($file->getRealPath());
        }

        $data = (new TabularFileReader())->rows($file->getRealPath(), 'xlsx', null, self::maxRows());

        if ($data['truncated']) {
            $limit = number_format(self::maxRows(), 0, ',', '.');

            throw new XlsxReadException("Het werkblad bevat meer dan {$limit} rijen; de import leest maximaal {$limit} rijen. Splits het bestand op in kleinere bestanden.");
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($data['rows'] as $cells) {
            $values = array_map(fn ($c) => (string) ($c ?? ''), $cells);
            if (array_filter($values, fn ($v) => trim($v) !== '') === []) {
                continue; // skip fully empty spacer rows
            }
            fputcsv($stream, $values, ';', '"', '\\');
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    public function index(): View
    {
        return view('admin.hvac.imports.index', [
            'mappingProfiles' => HvacMappingProfile::orderBy('supplier_name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function preview(Request $request, HvacCsvImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:' . self::maxUploadKb()],
            'mode' => ['required', 'in:create_and_update,create_only,update_only'],
        ], self::uploadMessages());

        try {
            $contents = $this->contentsAsCsv($request);
        } catch (XlsxReadException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::warning('HVAC template import: file could not be read', ['error' => $e->getMessage()]);

            return back()->withErrors(['file' => self::UNREADABLE_FILE]);
        }

        $parsed = $importer->parse($contents);

        if ($parsed['rows'] === []) {
            return back()->withErrors(['file' => implode(' ', $parsed['global_errors']) ?: 'Het bestand kon niet gelezen worden.']);
        }

        $token = Str::random(40);
        Cache::put("hvac-import:{$token}", [
            'rows'     => $parsed['rows'],
            'mode'     => $request->string('mode')->toString(),
            'filename' => $request->file('file')->getClientOriginalName(),
        ], self::CACHE_TTL);

        $rows = collect($parsed['rows']);

        return view('admin.hvac.imports.preview', [
            'token'        => $token,
            'mode'         => $request->string('mode')->toString(),
            'rows'         => $rows->take(100),
            'totalRows'    => $rows->count(),
            'createCount'  => $rows->where('action', 'create')->whereStrict('errors', [])->count(),
            'updateCount'  => $rows->where('action', 'update')->whereStrict('errors', [])->count(),
            'errorCount'   => $rows->filter(fn ($r) => $r['errors'] !== [])->count(),
            'globalErrors' => $parsed['global_errors'],
        ]);
    }

    public function confirm(Request $request, HvacCsvImporter $importer): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:40']]);
        $token = $request->string('token')->toString();

        // Same guard as the guided wizard: a repeated POST (double-click,
        // back button) after a completed import must say so instead of
        // "verlopen", and two concurrent POSTs may never import twice.
        if (Cache::get("hvac-import-done:{$token}") !== null) {
            return redirect()->route('admin.hvac.import.index')->withErrors(['file' => self::ALREADY_IMPORTED]);
        }

        $lock = Cache::lock("hvac-import-confirm:{$token}", 120);
        if (! $lock->get()) {
            return redirect()->route('admin.hvac.import.index')->withErrors(['file' => self::ALREADY_IMPORTED]);
        }

        try {
            if (Cache::get("hvac-import-done:{$token}") !== null) {
                return redirect()->route('admin.hvac.import.index')->withErrors(['file' => self::ALREADY_IMPORTED]);
            }

            $payload = Cache::get("hvac-import:{$token}");
            if ($payload === null) {
                return redirect()->route('admin.hvac.import.index')
                    ->withErrors(['file' => 'De voorbereide import is verlopen. Upload het bestand opnieuw.']);
            }

            $response = $this->runConfirmedImport($importer, $payload);

            Cache::put("hvac-import-done:{$token}", true, self::CACHE_TTL);
            Cache::forget("hvac-import:{$token}");

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function runConfirmedImport(HvacCsvImporter $importer, array $payload): RedirectResponse
    {
        $filename = (string) ($payload['filename'] ?? 'sjabloonbestand.csv');

        // Template imports belong in the Productlijsten overview too: link the
        // written products to a per-file template catalog with run history.
        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($importer, $payload, $filename) {
            $result = $importer->import($payload['rows'], $payload['mode'], ['source_file' => $filename]);

            if ($result['product_ids'] !== []) {
                $catalog = \App\Models\HvacImportCatalog::firstOrCreate(
                    ['name' => 'MAS-sjabloon — ' . pathinfo($filename, PATHINFO_FILENAME)],
                    ['source_type' => 'template', 'source_filename' => $filename]
                );
                $catalog->products()->syncWithoutDetaching(array_fill_keys(
                    array_values($result['product_ids']),
                    ['imported_at' => now()]
                ));
                \App\Models\HvacImportRun::create([
                    'hvac_import_catalog_id' => $catalog->id,
                    'created_count'          => $result['created'],
                    'updated_count'          => $result['updated'],
                    'skipped_count'          => $result['skipped'],
                    'imported_by'            => (string) session('admin_user_email'),
                    'source_filename'        => $filename,
                ]);
                $catalog->update([
                    'product_count'   => $catalog->products()->count(),
                    'imported_at'     => now(),
                    'source_filename' => $filename,
                    'imported_by'     => (string) session('admin_user_email'),
                ]);
            }

            return $result;
        });

        $errorRows = array_values(array_filter($payload['rows'], fn ($r) => $r['errors'] !== []));
        $reportToken = null;
        if ($errorRows !== []) {
            $reportToken = Str::random(40);
            Cache::put("hvac-import-errors:{$reportToken}", $errorRows, self::CACHE_TTL);
        }

        return redirect()->route('admin.hvac.import.index')->with([
            'success'             => 'hvac_import_done',
            'import_result'       => $result + ['errors' => count($errorRows)],
            'import_error_token'  => $reportToken,
        ]);
    }

    public function errorReport(string $token): Response
    {
        $rows = Cache::get("hvac-import-errors:{$token}");
        abort_if($rows === null, 404);

        return response(HvacCsvImporter::errorReport($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hvac-import-fouten.csv"',
        ]);
    }

    public function template(): Response
    {
        return response(HvacCsvImporter::template(), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hvac-producten-sjabloon.csv"',
        ]);
    }

    // ── Compatibility import ──────────────────────────────────────────────────

    public function compatPreview(Request $request, HvacCompatibilityCsvImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:' . self::maxUploadKb()],
        ], self::uploadMessages());

        try {
            $contents = $this->contentsAsCsv($request);
        } catch (XlsxReadException $e) {
            return back()->withErrors(['compat_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::warning('HVAC compatibility import: file could not be read', ['error' => $e->getMessage()]);

            return back()->withErrors(['compat_file' => self::UNREADABLE_FILE]);
        }

        $parsed = $importer->parse($contents);

        if ($parsed['rows'] === []) {
            return back()->withErrors(['compat_file' => implode(' ', $parsed['global_errors']) ?: 'Het bestand kon niet gelezen worden.']);
        }

        $token = Str::random(40);
        Cache::put("hvac-compat-import:{$token}", ['rows' => $parsed['rows']], self::CACHE_TTL);

        $rows = collect($parsed['rows']);

        return view('admin.hvac.imports.compat-preview', [
            'token'        => $token,
            'rows'         => $rows->take(100),
            'totalRows'    => $rows->count(),
            'validCount'   => $rows->filter(fn ($r) => $r['errors'] === [])->count(),
            'errorCount'   => $rows->filter(fn ($r) => $r['errors'] !== [])->count(),
            'globalErrors' => $parsed['global_errors'],
        ]);
    }

    public function compatConfirm(Request $request, HvacCompatibilityCsvImporter $importer): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:40']]);

        $payload = Cache::pull('hvac-compat-import:' . $request->string('token')->toString());
        if ($payload === null) {
            return redirect()->route('admin.hvac.import.index')
                ->withErrors(['compat_file' => 'De voorbereide import is verlopen. Upload het bestand opnieuw.']);
        }

        $result = $importer->import($payload['rows']);

        return redirect()->route('admin.hvac.import.index')->with([
            'success'              => 'hvac_compat_import_done',
            'compat_import_result' => $result,
        ]);
    }

    public function compatTemplate(): Response
    {
        return response(HvacCompatibilityCsvImporter::template(), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hvac-compatibiliteit-sjabloon.csv"',
        ]);
    }
}
