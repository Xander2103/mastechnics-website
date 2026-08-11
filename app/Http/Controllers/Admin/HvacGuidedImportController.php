<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacImportCatalog;
use App\Models\HvacImportRun;
use App\Models\HvacMappingProfile;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use App\Services\Hvac\HvacCsvImporter;
use App\Services\Hvac\Import\CategoryDetector;
use App\Services\Hvac\Import\ColumnMappingSuggester;
use App\Services\Hvac\Import\HeaderRowDetector;
use App\Services\Hvac\Import\HvacGuidedImportService;
use App\Services\Hvac\Import\TabularFileReader;
use App\Services\Hvac\Import\XlsxReadException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Guided supplier import wizard: the system does the technical work (file
 * type, delimiter, encoding, worksheet, header row, column mapping, profile
 * recognition), the admin only makes business decisions (which products,
 * what a price means, which product list). Steps:
 *
 *   1 Bestand    upload + automatic analysis; delimiter/worksheet/header
 *                questions appear ONLY when detection is genuinely unsure
 *   2 Producten  category selection ("Welke producten wilt u importeren?")
 *   3 Controle   recognized-fields summary, open questions, preview
 *   4 Importeren short confirmation (list name, new/update) — first DB write
 *   5 Resultaat  friendly outcome + optional "remember these settings"
 *
 * State lives in the cache under a token; the uploaded file sits in
 * storage/app/hvac-imports and is deleted after import, cancellation or
 * expiry. Nothing is written to the catalog before the confirm step.
 */
class HvacGuidedImportController extends Controller
{
    private const CACHE_TTL = 3600;
    private const STORAGE_DIR = 'hvac-imports';
    private const MAX_ROWS = 100000;
    private const MAX_COLS = 64;
    private const PREVIEW_OK_ROWS = 7;
    private const PREVIEW_PROBLEM_ROWS = 3;

    public const PRICE_MEANINGS = ['gross', 'net_purchase', 'sale', 'unknown'];

    /**
     * Label for rows whose category cell is empty — they must stay selectable,
     * products are never excluded silently.
     */
    public const EMPTY_CATEGORY = '(zonder groep)';

    public function __construct(
        private readonly TabularFileReader $files,
        private readonly HeaderRowDetector $headerDetector,
        private readonly ColumnMappingSuggester $suggester,
        private readonly CategoryDetector $categories,
        private readonly HvacGuidedImportService $guided,
        private readonly HvacCsvImporter $importer,
    ) {
    }

    // ── Step 1: upload + automatic analysis ─────────────────────────────────

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file'          => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:' . HvacImportController::maxUploadKb()],
            'supplier_name' => ['nullable', 'string', 'max:120'],
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

        $state = [
            'extension'       => $extension,
            'original_name'   => $file->getClientOriginalName(),
            'supplier_name'   => trim((string) $request->string('supplier_name')),
            'step'            => 'review',
            'delimiter'       => null,
            'sheet'           => null,
            'header_row'      => null,
            'column_map'      => null,
            'decimal_format'  => 'auto',
            'category'        => null,
            'price_meaning'   => null,
            'type_fallback'   => null,
            'brand_name'      => null,
            'mode'            => 'create_and_update',
            'profile_id'      => null,
            'profile_notice'  => null,
            'profile_warning' => null,
            'result'          => null,
        ];

        try {
            $state = $this->analyze($state, $path);
        } catch (XlsxReadException $e) {
            $this->forget($token, $extension);

            return redirect()->route('admin.hvac.import.index')->withErrors(['guided_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::warning('HVAC guided import: analysis failed', ['file' => $state['original_name'], 'error' => $e->getMessage()]);
            $this->forget($token, $extension);

            return redirect()->route('admin.hvac.import.index')->withErrors([
                'guided_file' => 'Het bestand kon niet gelezen worden. Controleer of het een geldig CSV- of Excel-bestand is.',
            ]);
        }

        $this->put($token, $state);

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    /**
     * Runs every automatic detection it can and leaves 'step' at the first
     * question that genuinely needs the admin. Re-entrant: called again after
     * each answered question.
     */
    private function analyze(array $state, string $path): array
    {
        // 1. Delimiter (CSV only): ask only when detection cannot decide.
        if ($state['extension'] !== 'xlsx' && $state['delimiter'] === null) {
            $detected = $this->files->detectDelimiter($path);
            if ($detected['confidence'] === 'high' && $detected['delimiter'] !== null) {
                $state['delimiter'] = $detected['delimiter'];
            } else {
                $state['step'] = 'delimiter';

                return $state;
            }
        }

        // 2. Worksheet: only a question when there are several visible ones.
        if ($state['sheet'] === null) {
            $sheets = $this->files->sheets($path, $state['extension']);
            $visible = array_values(array_filter($sheets, fn (array $s) => ! $s['hidden']));
            if (count($visible) === 1) {
                $state['sheet'] = $visible[0]['name'];
            } elseif ($visible === []) {
                $state['sheet'] = $sheets[0]['name'] ?? null;
            } else {
                $state['step'] = 'sheet';

                return $state;
            }
        }

        // 3. Header row: auto when confidently detected.
        if ($state['header_row'] === null) {
            $preview = $this->readRows($state, $path, 20);
            $detected = $this->headerDetector->detect($preview['rows']);
            if ($detected['confidence'] === 'high') {
                $state['header_row'] = $detected['row'];
            } else {
                $state['step'] = 'header';

                return $state;
            }
        }

        // 4. Column mapping: profile recognition first, alias suggestions second.
        if ($state['column_map'] === null) {
            $headerCells = $this->headerCells($state, $path);

            if ($state['profile_id'] === null) {
                $profile = $this->guided->recognizeProfile($headerCells);
                if ($profile !== null) {
                    $state = $this->applyProfile($state, $profile, $headerCells);
                }
            }

            if ($state['column_map'] === null) {
                $map = [];
                foreach ($this->suggester->suggest($headerCells) as $col => $suggestion) {
                    if ($suggestion['confidence'] === 'exact' && $suggestion['field'] !== null) {
                        $map[$col] = $suggestion['field'];
                    }
                }
                $state['column_map'] = $map;
            }
        }

        // 5. Categories: a business decision — always shown when a category
        //    column exists (unless a recognized profile already answered it).
        if ($state['category'] === null) {
            $sample = $this->readRows($state, $path, 2000);
            $rows = $sample['rows'];
            unset($rows[$state['header_row']]);
            $rows = array_filter($rows, fn ($cells, $i) => $i > $state['header_row'], ARRAY_FILTER_USE_BOTH);

            $detected = $this->categories->detect($this->headerCells($state, $path), $rows);
            if ($detected['column'] === null || count($detected['values']) <= 1) {
                $state['category'] = ['skipped' => true];
            } else {
                // Full streaming count over the whole file for honest numbers.
                $counts = $this->countCategoryValues($state, $path, $detected['column']);
                $state['category'] = [
                    'column'   => $detected['column'],
                    'header'   => (string) ($this->headerCells($state, $path)[$detected['column']] ?? 'kolom'),
                    'values'   => $counts,
                    'selected' => null,
                    'skipped'  => false,
                ];
                $state['step'] = 'categories';

                return $state;
            }
        }
        if (! ($state['category']['skipped'] ?? false) && $state['category']['selected'] === null) {
            $state['step'] = 'categories';

            return $state;
        }

        $state['step'] = 'review';

        return $state;
    }

    private function applyProfile(array $state, HvacMappingProfile $profile, array $headerCells): array
    {
        $headerByName = [];
        foreach ($headerCells as $col => $cell) {
            $normalized = ColumnMappingSuggester::normalize((string) ($cell ?? ''));
            if ($normalized !== '' && ! isset($headerByName[$normalized])) {
                $headerByName[$normalized] = (int) $col;
            }
        }

        $map = [];
        foreach ((array) $profile->column_map as $sourceHeader => $field) {
            $normalized = ColumnMappingSuggester::normalize((string) $sourceHeader);
            if (isset($headerByName[$normalized]) && in_array($field, ColumnMappingSuggester::targets(), true)) {
                $map[$headerByName[$normalized]] = (string) $field;
            }
        }
        if ($map === []) {
            return $state;
        }

        $state['column_map'] = $map;
        $state['decimal_format'] = $profile->decimal_format ?? 'auto';
        $state['profile_id'] = $profile->id;
        $state['type_fallback'] = $profile->type_fallback;
        $state['profile_notice'] = 'We herkennen dit bestand als een bestand van ' . $profile->supplier_name
            . '. Vorige instellingen zijn toegepast.';
        if ($state['supplier_name'] === '') {
            $state['supplier_name'] = $profile->supplier_name;
        }
        if (is_array($profile->price_semantics) && in_array($profile->price_semantics['meaning'] ?? null, self::PRICE_MEANINGS, true)) {
            $state['price_meaning'] = $profile->price_semantics['meaning'];
        }

        // Category filter from the profile — applied only when the column and
        // all remembered values still exist in this file.
        $filter = $profile->category_filter;
        if (is_array($filter) && isset($filter['column_header'], $filter['selected'])) {
            $normalized = ColumnMappingSuggester::normalize((string) $filter['column_header']);
            if (isset($headerByName[$normalized])) {
                $state['category'] = [
                    'column'   => $headerByName[$normalized],
                    'header'   => (string) $filter['column_header'],
                    'values'   => null, // counts computed lazily for the categories screen
                    'selected' => array_values((array) $filter['selected']),
                    'skipped'  => false,
                ];
            }
        }

        return $state;
    }

    // ── Step router ──────────────────────────────────────────────────────────

    public function step(string $token): View|RedirectResponse
    {
        $state = $this->state($token);
        if ($state === null) {
            return $this->expired();
        }

        if ($state['step'] === 'done') {
            return redirect()->route('admin.hvac.import.guided.result', $token);
        }

        $path = $this->filePath($token, $state['extension']);
        if ($path === null) {
            return $this->expired($token);
        }

        try {
            return match ($state['step']) {
                'delimiter'  => $this->delimiterView($token, $state),
                'sheet'      => $this->sheetView($token, $state, $path),
                'header'     => $this->headerView($token, $state, $path),
                'categories' => $this->categoriesView($token, $state, $path),
                'confirm'    => $this->confirmView($token, $state, $path),
                default      => $this->reviewView($token, $state, $path),
            };
        } catch (XlsxReadException $e) {
            $this->forget($token, $state['extension']);

            return redirect()->route('admin.hvac.import.index')->withErrors(['guided_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::warning('HVAC guided import: step failed', ['step' => $state['step'], 'error' => $e->getMessage()]);
            $this->forget($token, $state['extension']);

            return redirect()->route('admin.hvac.import.index')->withErrors([
                'guided_file' => 'Het bestand kon niet verder verwerkt worden. Probeer het opnieuw of controleer het bestand.',
            ]);
        }
    }

    // ── Question endpoints ───────────────────────────────────────────────────

    public function chooseDelimiter(Request $request, string $token): RedirectResponse
    {
        [$state, $path] = $this->stateAndPath($token);
        if ($state === null) {
            return $this->expired($token);
        }

        $request->validate(['delimiter' => ['required', 'in:auto,semicolon,comma,tab,pipe']]);

        $state['delimiter'] = match ($request->string('delimiter')->toString()) {
            'semicolon' => ';',
            'comma'     => ',',
            'tab'       => "\t",
            'pipe'      => '|',
            default     => $this->files->detectDelimiter($path)['delimiter'] ?? ';',
        };

        return $this->reanalyze($token, $state, $path);
    }

    public function chooseSheet(Request $request, string $token): RedirectResponse
    {
        [$state, $path] = $this->stateAndPath($token);
        if ($state === null) {
            return $this->expired($token);
        }

        $names = array_column($this->files->sheets($path, $state['extension']), 'name');
        $request->validate(['sheet' => ['required', 'string', 'in:' . implode(',', $names)]]);

        $state['sheet'] = $request->string('sheet')->toString();
        $state['header_row'] = null;
        $state['column_map'] = null;
        $state['category'] = null;

        return $this->reanalyze($token, $state, $path);
    }

    public function chooseHeader(Request $request, string $token): RedirectResponse
    {
        [$state, $path] = $this->stateAndPath($token);
        if ($state === null) {
            return $this->expired($token);
        }

        $request->validate(['header_row' => ['required', 'integer', 'min:1', 'max:1000']]);

        $state['header_row'] = $request->integer('header_row') - 1;
        $state['column_map'] = null;
        $state['category'] = null;

        return $this->reanalyze($token, $state, $path);
    }

    public function chooseCategories(Request $request, string $token): RedirectResponse
    {
        [$state, $path] = $this->stateAndPath($token);
        if ($state === null) {
            return $this->expired($token);
        }
        if (($state['category']['column'] ?? null) === null) {
            return redirect()->route('admin.hvac.import.guided.step', $token);
        }

        $data = $request->validate([
            'categories'   => ['required', 'array', 'min:1'],
            'categories.*' => ['string', 'max:255'],
        ], [
            'categories.required' => 'Kies minstens één productgroep om te importeren.',
        ]);

        $known = array_keys($state['category']['values'] ?? $this->countCategoryValues($state, $path, $state['category']['column']));
        $state['category']['selected'] = array_values(array_intersect($data['categories'], $known));
        if ($state['category']['selected'] === []) {
            return back()->withErrors(['categories' => 'Kies minstens één productgroep om te importeren.']);
        }

        return $this->reanalyze($token, $state, $path);
    }

    public function saveReview(Request $request, string $token): RedirectResponse
    {
        [$state, $path] = $this->stateAndPath($token);
        if ($state === null) {
            return $this->expired($token);
        }

        $data = $request->validate([
            'price_meaning'  => ['nullable', 'in:gross,net_purchase,sale,unknown'],
            'type_fallback'  => ['nullable', 'in:' . implode(',', HvacProduct::PRODUCT_TYPES)],
            'supplier_name'  => ['nullable', 'string', 'max:120'],
            'brand_name'     => ['nullable', 'string', 'max:120'],
            'decimal_format' => ['nullable', 'in:auto,comma,point'],
            'mapping'        => ['nullable', 'array'],
            'mapping.*'      => ['nullable', 'string', 'in:' . implode(',', ColumnMappingSuggester::targets())],
        ]);

        if ($request->has('mapping')) {
            $map = [];
            foreach ((array) $data['mapping'] as $col => $field) {
                if ($field !== null && $field !== '') {
                    $map[(int) $col] = $field;
                }
            }
            $duplicates = array_diff_assoc($map, array_unique($map));
            if ($duplicates !== []) {
                return back()->withErrors([
                    'mapping' => 'Elk veld mag maar aan één kolom gekoppeld worden (dubbel: ' . implode(', ', array_unique($duplicates)) . ').',
                ])->withInput();
            }
            $state['column_map'] = $map;
        }

        if ($request->filled('supplier_name')) {
            $state['supplier_name'] = trim((string) $data['supplier_name']);
        }
        if ($request->filled('brand_name')) {
            $state['brand_name'] = trim((string) $data['brand_name']);
        }
        if ($request->filled('price_meaning')) {
            $state['price_meaning'] = $data['price_meaning'];
        }
        if ($request->filled('type_fallback')) {
            $state['type_fallback'] = $data['type_fallback'];
        }
        if ($request->filled('decimal_format')) {
            $state['decimal_format'] = $data['decimal_format'];
        }

        // Hard requirements the derivations cannot repair — say it in plain
        // Dutch, pointing at the business fact that is missing.
        $problems = $this->blockingProblems($state, $path);
        if ($problems !== []) {
            return back()->withErrors(['review' => implode(' ', $problems)])->withInput();
        }

        $state['step'] = 'confirm';
        $this->put($token, $state);

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    /** @return string[] */
    private function blockingProblems(array $state, string $path): array
    {
        $map = (array) $state['column_map'];
        $problems = [];

        if (! in_array('sku', $map, true)) {
            $problems[] = 'We weten nog niet welke kolom het artikelnummer bevat. Kies die kolom bij "Geavanceerde koppelingen".';
        }
        if (! in_array('name', $map, true) && ! in_array('name_fallback', $map, true)) {
            $problems[] = 'We weten nog niet welke kolom de productnaam bevat.';
        }
        if (! in_array('supplier', $map, true) && $state['supplier_name'] === '') {
            $problems[] = 'Vul de naam van de leverancier in.';
        }
        if (! in_array('brand', $map, true) && trim((string) ($state['brand_name'] ?? '')) === '') {
            $problems[] = 'Vul het merk in of koppel een merkkolom.';
        }
        if (in_array('price', $map, true) && $state['price_meaning'] === null) {
            $problems[] = 'Geef aan wat de prijs in dit bestand betekent.';
        }

        return $problems;
    }

    // ── Step 4 + 5: confirm, import, result ─────────────────────────────────

    public function confirm(Request $request, string $token): RedirectResponse
    {
        [$state, $path] = $this->stateAndPath($token);
        if ($state === null || $state['step'] !== 'confirm') {
            return $this->expired($token);
        }

        $data = $request->validate([
            'mode'               => ['required', 'in:create_and_update,create_only,update_only'],
            'catalog_choice'     => ['required', 'in:new,existing'],
            'catalog_name'       => ['required_if:catalog_choice,new', 'nullable', 'string', 'max:150'],
            'catalog_id'         => ['required_if:catalog_choice,existing', 'nullable', 'integer', 'exists:hvac_import_catalogs,id'],
            'deactivate_missing' => ['nullable', 'boolean'],
        ], [
            'catalog_name.required_if' => 'Geef deze productlijst een naam.',
            'catalog_id.required_if'   => 'Kies welke bestaande productlijst u wilt bijwerken.',
        ]);

        $state['mode'] = $data['mode'];

        try {
            $validated = $this->validatedRows($state, $path);
        } catch (Throwable $e) {
            Log::warning('HVAC guided import: validation failed at confirm', ['error' => $e->getMessage()]);
            $this->forget($token, $state['extension']);

            return redirect()->route('admin.hvac.import.index')->withErrors([
                'guided_file' => 'Het bestand kon niet verwerkt worden. Start de import opnieuw.',
            ]);
        }

        $provenanceByLine = [];
        foreach ($validated['normalized'] as $entry) {
            $provenanceByLine[$entry['line']] = $entry['provenance'];
        }

        $result = DB::transaction(function () use ($state, $data, $validated, $provenanceByLine) {
            $catalog = $this->resolveCatalog($state, $data);

            $importResult = $this->importer->import($validated['rows'], $state['mode'], [
                'source_file'        => $state['original_name'],
                'profile_id'         => $state['profile_id'],
                'catalog_id'         => $catalog->id,
                'provenance_by_line' => $provenanceByLine,
            ]);

            $missing = $this->syncCatalog($catalog, $importResult['product_ids'], $provenanceByLine, $validated['rows']);

            $deactivated = 0;
            if ((bool) ($data['deactivate_missing'] ?? false)) {
                $deactivated = $this->deactivateMissing($catalog, $missing);
            }

            $needsReview = count(array_filter(
                $provenanceByLine,
                fn ($p, $line) => ($p['needs_review'] ?? false) && isset($importResult['product_ids'][$line]),
                ARRAY_FILTER_USE_BOTH
            ));
            $errorRows = array_values(array_filter($validated['rows'], fn ($r) => $r['errors'] !== []));

            HvacImportRun::create([
                'hvac_import_catalog_id' => $catalog->id,
                'created_count'          => $importResult['created'],
                'updated_count'          => $importResult['updated'],
                'skipped_count'          => $importResult['skipped'],
                'warning_count'          => $needsReview,
                'deactivated_count'      => $deactivated,
                'imported_by'            => (string) session('admin_user_email'),
                'source_filename'        => $state['original_name'],
            ]);

            $catalog->update([
                'product_count' => $catalog->products()->count(),
                'imported_at'   => now(),
                'status'        => HvacImportCatalog::STATUS_ACTIVE,
            ]);

            return [
                'catalog_id'   => $catalog->id,
                'catalog_name' => $catalog->name,
                'created'      => $importResult['created'],
                'updated'      => $importResult['updated'],
                'skipped'      => $importResult['skipped'],
                'needs_review' => $needsReview,
                'deactivated'  => $deactivated,
                'errors'       => count($errorRows),
                'error_rows'   => array_slice($errorRows, 0, 50),
                'missing'      => count($missing),
            ];
        });

        // Error report for the "problemen bekijken" button.
        if ($result['errors'] > 0) {
            $reportToken = Str::random(40);
            Cache::put("hvac-import-errors:{$reportToken}", $result['error_rows'], self::CACHE_TTL);
            $result['error_token'] = $reportToken;
        }
        unset($result['error_rows']);

        // Snapshot the headers BEFORE deleting the file — the result page's
        // profile-save prompt needs them. The state lives on (step 'done').
        $state['header_cells_snapshot'] = $this->headerCellsSafe($state, $path);
        Storage::disk('local')->delete(self::STORAGE_DIR . "/{$token}.{$state['extension']}");
        $state['step'] = 'done';
        $state['result'] = $result;
        $this->put($token, $state);

        return redirect()->route('admin.hvac.import.guided.result', $token);
    }

    private function resolveCatalog(array $state, array $data): HvacImportCatalog
    {
        $supplier = $state['supplier_name'] !== ''
            ? HvacSupplier::firstOrCreate(['name' => $state['supplier_name']], ['is_active' => true])
            : null;

        if ($data['catalog_choice'] === 'existing') {
            $catalog = HvacImportCatalog::findOrFail((int) $data['catalog_id']);
            $catalog->update([
                'source_filename'         => $state['original_name'],
                'imported_by'             => (string) session('admin_user_email'),
                'hvac_mapping_profile_id' => $state['profile_id'] ?? $catalog->hvac_mapping_profile_id,
            ]);

            return $catalog;
        }

        return HvacImportCatalog::create([
            'name'                    => trim((string) $data['catalog_name']),
            'hvac_supplier_id'        => $supplier?->id,
            'source_filename'         => $state['original_name'],
            'source_type'             => 'guided',
            'imported_by'             => (string) session('admin_user_email'),
            'imported_at'             => now(),
            'hvac_mapping_profile_id' => $state['profile_id'],
        ]);
    }

    /**
     * Links written products to the catalog and returns the previously linked
     * products that are MISSING from this file (never deleted automatically).
     *
     * @param array<int, int> $productIds line => product id
     * @return int[] product ids missing from the new file
     */
    private function syncCatalog(HvacImportCatalog $catalog, array $productIds, array $provenanceByLine, array $rows): array
    {
        $previouslyLinked = $catalog->products()->pluck('hvac_products.id')->all();

        $pricesByLine = [];
        foreach ($rows as $row) {
            $pricesByLine[$row['line']] = $row['data'];
        }

        $attach = [];
        foreach ($productIds as $line => $productId) {
            $provenance = $provenanceByLine[$line] ?? [];
            $data = $pricesByLine[$line] ?? [];
            $attach[$productId] = [
                'source_row'       => $provenance['source_row'] ?? null,
                'imported_at'      => now(),
                'source_price'     => isset($provenance['price']['raw']) && is_numeric(str_replace(',', '.', (string) $provenance['price']['raw']))
                    ? (float) str_replace(',', '.', (string) $provenance['price']['raw'])
                    : ($data['purchase_price_excl_vat'] ?? $data['sale_price_excl_vat'] ?? null),
                'source_stock'     => $data['stock_quantity'] ?? null,
                'source_lead_time' => $data['lead_time_days'] ?? null,
            ];
        }

        $catalog->products()->syncWithoutDetaching($attach);

        return array_values(array_diff($previouslyLinked, array_keys($attach)));
    }

    /**
     * Explicitly requested only: deactivate products missing from the new
     * file, and only those not present in another active catalog.
     *
     * @param int[] $missingIds
     */
    private function deactivateMissing(HvacImportCatalog $catalog, array $missingIds): int
    {
        $count = 0;
        foreach (HvacProduct::whereIn('id', $missingIds)->get() as $product) {
            $inOtherActiveCatalog = $product->importCatalogs()
                ->where('hvac_import_catalogs.id', '!=', $catalog->id)
                ->where('status', HvacImportCatalog::STATUS_ACTIVE)
                ->exists();
            if (! $inOtherActiveCatalog && $product->is_active) {
                $product->update(['is_active' => false]);
                $count++;
            }
        }

        return $count;
    }

    public function result(string $token): View|RedirectResponse
    {
        $state = $this->state($token);
        if ($state === null || $state['step'] !== 'done' || $state['result'] === null) {
            return redirect()->route('admin.hvac.import.index');
        }

        return view('admin.hvac.imports.guided.result', [
            'token'    => $token,
            'state'    => $state,
            'result'   => $state['result'],
            'catalog'  => HvacImportCatalog::find($state['result']['catalog_id']),
            'canSaveProfile' => $state['profile_id'] === null
                && is_array($state['header_cells_snapshot'] ?? null)
                && ($state['header_cells_snapshot'] ?? []) !== [],
        ]);
    }

    public function saveProfile(Request $request, string $token): RedirectResponse
    {
        $state = $this->state($token);
        if ($state === null || $state['step'] !== 'done') {
            return $this->expired($token);
        }

        $headerCells = (array) ($state['header_cells_snapshot'] ?? []);
        $sourceMap = [];
        foreach ((array) $state['column_map'] as $col => $field) {
            $sourceMap[(string) ($headerCells[$col] ?? 'kolom ' . ((int) $col + 1))] = $field;
        }

        $supplier = $state['supplier_name'] !== '' ? $state['supplier_name'] : 'Leverancier';

        HvacMappingProfile::updateOrCreate(
            ['name' => $supplier . ' — automatisch'],
            [
                'supplier_name'   => $supplier,
                'header_row'      => (int) $state['header_row'] + 1,
                'column_map'      => $sourceMap,
                'decimal_format'  => $state['decimal_format'],
                'delimiter'       => $state['delimiter'],
                'category_filter' => ($state['category']['skipped'] ?? true) ? null : [
                    'column_header' => $state['category']['header'],
                    'selected'      => $state['category']['selected'],
                ],
                'price_semantics' => $state['price_meaning'] !== null ? [
                    'column_header' => $this->priceColumnHeader($state, $headerCells),
                    'meaning'       => $state['price_meaning'],
                ] : null,
                'source_headers'  => array_values(array_filter(array_map(fn ($c) => (string) ($c ?? ''), $headerCells))),
                'type_fallback'   => $state['type_fallback'],
                'is_active'       => true,
                'created_by'      => (string) session('admin_user_email'),
            ]
        );

        $this->forgetState($token);

        return redirect()->route('admin.hvac.import.index')
            ->with('success', 'hvac_profile_saved');
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
        [$state, $path] = $this->stateAndPath($token);
        if ($state === null) {
            return $this->expired($token);
        }

        $step = $request->string('step')->toString();
        if (in_array($step, ['delimiter', 'sheet', 'header', 'categories', 'review'], true)) {
            if ($step === 'categories' && ($state['category']['column'] ?? null) !== null) {
                $state['category']['selected'] = null;
            }
            $state['step'] = $step;
            $this->put($token, $state);
        }

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    public function toggleProfile(HvacMappingProfile $profile): RedirectResponse
    {
        $profile->update(['is_active' => ! $profile->is_active]);

        return redirect()->route('admin.hvac.import.index');
    }

    // ── Views per step ───────────────────────────────────────────────────────

    private function delimiterView(string $token, array $state): View
    {
        return view('admin.hvac.imports.guided.delimiter', [
            'token' => $token,
            'state' => $state,
        ]);
    }

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
        $preview = $this->readRows($state, $path, 20);
        $detected = $this->headerDetector->detect($preview['rows']);

        return view('admin.hvac.imports.guided.header', [
            'token'    => $token,
            'state'    => $state,
            'rows'     => $preview['rows'],
            'detected' => $detected,
            'selected' => $state['header_row'] ?? $detected['row'],
        ]);
    }

    private function categoriesView(string $token, array $state, string $path): View
    {
        $category = $state['category'];
        if (($category['values'] ?? null) === null) {
            $category['values'] = $this->countCategoryValues($state, $path, $category['column']);
            $state['category']['values'] = $category['values'];
            $this->put($token, $state);
        }

        $preselected = $category['selected'];
        if ($preselected === null) {
            $preselected = array_values(array_filter(
                array_keys($category['values']),
                fn (string $v) => $this->categories->hvacLikely($v)
            ));
        }

        return view('admin.hvac.imports.guided.categories', [
            'token'       => $token,
            'state'       => $state,
            'header'      => $category['header'],
            'values'      => $category['values'],
            'preselected' => $preselected,
            'total'       => array_sum($category['values']),
        ]);
    }

    private function reviewView(string $token, array $state, string $path): View
    {
        $headerCells = $this->headerCells($state, $path);
        $validated = $this->validatedRows($state, $path);
        $rows = collect($validated['rows']);
        $provenanceByLine = [];
        foreach ($validated['normalized'] as $entry) {
            $provenanceByLine[$entry['line']] = $entry['provenance'];
        }

        $okCount = $rows->filter(fn ($r) => $r['errors'] === [] && ! ($provenanceByLine[$r['line']]['needs_review'] ?? false))->count();
        $reviewCount = $rows->filter(fn ($r) => $r['errors'] === [] && ($provenanceByLine[$r['line']]['needs_review'] ?? false))->count();
        $blockedCount = $rows->filter(fn ($r) => $r['errors'] !== [])->count();

        // ~10 representative rows: mostly OK ones plus a few problems.
        $preview = $rows->filter(fn ($r) => $r['errors'] === [])->take(self::PREVIEW_OK_ROWS)
            ->concat($rows->filter(fn ($r) => $r['errors'] !== [])->take(self::PREVIEW_PROBLEM_ROWS));

        $unresolvedTypes = $rows->filter(
            fn ($r) => ($r['data']['product_type'] ?? '') === ''
        )->count();

        return view('admin.hvac.imports.guided.review', [
            'token'            => $token,
            'state'            => $state,
            'headerCells'      => $headerCells,
            'summary'          => $this->fieldSummary($state, $headerCells, $unresolvedTypes),
            'questions'        => $this->openQuestions($state, $headerCells, $unresolvedTypes),
            'suggestions'      => $this->suggester->suggest($headerCells),
            'fields'           => ColumnMappingSuggester::targets(),
            'previewRows'      => $preview,
            'provenanceByLine' => $provenanceByLine,
            'totalRows'        => $rows->count(),
            'okCount'          => $okCount,
            'reviewCount'      => $reviewCount,
            'blockedCount'     => $blockedCount,
            'truncated'        => $validated['truncated'],
            'productTypes'     => HvacProduct::PRODUCT_TYPES,
        ]);
    }

    private function confirmView(string $token, array $state, string $path): View
    {
        $validated = $this->validatedRows($state, $path);
        $rows = collect($validated['rows']);
        $provenanceByLine = [];
        foreach ($validated['normalized'] as $entry) {
            $provenanceByLine[$entry['line']] = $entry['provenance'];
        }

        $clean = $rows->filter(fn ($r) => $r['errors'] === []);

        return view('admin.hvac.imports.guided.confirm', [
            'token'        => $token,
            'state'        => $state,
            'totalRows'    => $rows->count(),
            'createCount'  => $clean->where('action', 'create')->count(),
            'updateCount'  => $clean->where('action', 'update')->count(),
            'errorCount'   => $rows->count() - $clean->count(),
            'reviewCount'  => $clean->filter(fn ($r) => $provenanceByLine[$r['line']]['needs_review'] ?? false)->count(),
            'suggestedName' => $this->suggestCatalogName($state),
            'catalogs'     => HvacImportCatalog::where('status', '!=', HvacImportCatalog::STATUS_ARCHIVED)
                ->orderBy('name')->get(),
        ]);
    }

    private function suggestCatalogName(array $state): string
    {
        $base = pathinfo((string) $state['original_name'], PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[_\-]+/', ' ', $base));

        $year = null;
        if (preg_match('/(20\d{2})/', $base, $m) === 1) {
            $year = $m[1];
        }

        $parts = [];
        if ($state['supplier_name'] !== '') {
            $parts[] = $state['supplier_name'];
        }
        if ($base !== '' && ($state['supplier_name'] === '' || mb_stripos($base, (string) $state['supplier_name']) === false)) {
            $parts[] = $base;
        }
        if ($parts === []) {
            $parts[] = 'Productlijst';
        }
        if ($year === null) {
            $parts[count($parts) - 1] .= ' ' . now()->year;
        }

        return mb_substr(implode(' — ', $parts), 0, 150);
    }

    // ── Review helpers ───────────────────────────────────────────────────────

    /**
     * Business-level summary rows: label, source description, status
     * ('ok' | 'attention' | 'missing').
     *
     * @return array<int, array{label: string, source: string, status: string}>
     */
    private function fieldSummary(array $state, array $headerCells, int $unresolvedTypes): array
    {
        $map = (array) $state['column_map'];
        $headerFor = fn (string $field) => ($col = array_search($field, $map, true)) !== false
            ? (string) ($headerCells[$col] ?? 'kolom ' . ((int) $col + 1))
            : null;

        $rows = [];

        $rows[] = $state['supplier_name'] !== ''
            ? ['label' => 'Leverancier', 'source' => $state['supplier_name'], 'status' => 'ok']
            : (($h = $headerFor('supplier')) !== null
                ? ['label' => 'Leverancier', 'source' => $h, 'status' => 'ok']
                : ['label' => 'Leverancier', 'source' => 'Nog in te vullen', 'status' => 'attention']);

        $rows[] = ($h = $headerFor('brand')) !== null
            ? ['label' => 'Merk', 'source' => $h, 'status' => 'ok']
            : (trim((string) ($state['brand_name'] ?? '')) !== ''
                ? ['label' => 'Merk', 'source' => (string) $state['brand_name'], 'status' => 'ok']
                : ['label' => 'Merk', 'source' => 'Nog in te vullen', 'status' => 'attention']);

        $rows[] = ($h = $headerFor('sku')) !== null
            ? ['label' => 'Artikelnummer', 'source' => $h, 'status' => 'ok']
            : ['label' => 'Artikelnummer', 'source' => 'Niet gevonden', 'status' => 'attention'];

        $nameSource = $headerFor('name');
        $fallbackSource = $headerFor('name_fallback');
        $rows[] = $nameSource !== null
            ? ['label' => 'Productnaam', 'source' => $nameSource . ($fallbackSource !== null ? " (anders {$fallbackSource})" : ''), 'status' => 'ok']
            : ($fallbackSource !== null
                ? ['label' => 'Productnaam', 'source' => $fallbackSource, 'status' => 'ok']
                : ['label' => 'Productnaam', 'source' => 'Niet gevonden', 'status' => 'attention']);

        $rows[] = ($h = $headerFor('model')) !== null
            ? ['label' => 'Model', 'source' => $h, 'status' => 'ok']
            : ['label' => 'Model', 'source' => 'Artikelnummer wordt gebruikt', 'status' => 'ok'];

        $priceHeader = $headerFor('price') ?? $headerFor('purchase_price_excl_vat') ?? $headerFor('sale_price_excl_vat');
        if ($priceHeader === null) {
            $rows[] = ['label' => 'Prijs', 'source' => 'Niet beschikbaar', 'status' => 'missing'];
        } elseif ($headerFor('price') !== null && $state['price_meaning'] === null) {
            $rows[] = ['label' => 'Prijs', 'source' => $priceHeader, 'status' => 'attention'];
        } else {
            $meaning = match (true) {
                $headerFor('purchase_price_excl_vat') !== null => 'netto aankoopprijs',
                $headerFor('sale_price_excl_vat') !== null     => 'verkoopprijs',
                default => match ($state['price_meaning']) {
                    'gross'        => 'brutoprijs (alleen ter info)',
                    'net_purchase' => 'netto aankoopprijs',
                    'sale'         => 'verkoopprijs',
                    default        => 'betekenis onbekend',
                },
            };
            $rows[] = ['label' => 'Prijs', 'source' => "{$priceHeader} — {$meaning}", 'status' => in_array($state['price_meaning'], ['unknown'], true) ? 'attention' : 'ok'];
        }

        if (($h = $headerFor('product_type')) !== null) {
            $rows[] = ['label' => 'Producttype', 'source' => $h, 'status' => 'ok'];
        } elseif ($unresolvedTypes === 0) {
            $rows[] = ['label' => 'Producttype', 'source' => 'Automatisch herkend uit de productnaam', 'status' => 'ok'];
        } else {
            $rows[] = ['label' => 'Producttype', 'source' => "Type controleren voor {$unresolvedTypes} producten", 'status' => 'attention'];
        }

        $rows[] = ($h = $headerFor('ean')) !== null
            ? ['label' => 'EAN', 'source' => $h, 'status' => 'ok']
            : ['label' => 'EAN', 'source' => 'Niet beschikbaar', 'status' => 'missing'];

        return $rows;
    }

    /**
     * Only genuinely open questions get a prominent block.
     *
     * @return array<string, mixed>
     */
    private function openQuestions(array $state, array $headerCells, int $unresolvedTypes): array
    {
        $map = (array) $state['column_map'];
        $questions = [];

        if (in_array('price', $map, true) && $state['price_meaning'] === null) {
            $questions['price'] = ['header' => $this->priceColumnHeader($state, $headerCells)];
        }
        if (! in_array('supplier', $map, true) && $state['supplier_name'] === '') {
            $questions['supplier'] = true;
        }
        if (! in_array('brand', $map, true) && trim((string) ($state['brand_name'] ?? '')) === '') {
            $questions['brand'] = true;
        }
        if ($unresolvedTypes > 0 && ! in_array('product_type', $map, true)) {
            $questions['type'] = ['count' => $unresolvedTypes];
        }

        return $questions;
    }

    private function priceColumnHeader(array $state, array $headerCells): string
    {
        $col = array_search('price', (array) $state['column_map'], true);

        return $col !== false ? (string) ($headerCells[$col] ?? 'prijs') : 'prijs';
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    private function reanalyze(string $token, array $state, string $path): RedirectResponse
    {
        try {
            $state = $this->analyze($state, $path);
        } catch (XlsxReadException $e) {
            $this->forget($token, $state['extension']);

            return redirect()->route('admin.hvac.import.index')->withErrors(['guided_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::warning('HVAC guided import: re-analysis failed', ['error' => $e->getMessage()]);
            $this->forget($token, $state['extension']);

            return redirect()->route('admin.hvac.import.index')->withErrors([
                'guided_file' => 'Het bestand kon niet verder verwerkt worden. Probeer het opnieuw.',
            ]);
        }

        $this->put($token, $state);

        return redirect()->route('admin.hvac.import.guided.step', $token);
    }

    /** @return array{0: array|null, 1: string|null} */
    private function stateAndPath(string $token): array
    {
        $state = $this->state($token);
        $path = $state !== null ? $this->filePath($token, $state['extension']) : null;

        return $state === null || $path === null ? [null, null] : [$state, $path];
    }

    private function state(string $token): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        return Cache::get("hvac-guided:{$token}");
    }

    private function put(string $token, array $state): void
    {
        Cache::put("hvac-guided:{$token}", $state, self::CACHE_TTL);
    }

    private function filePath(string $token, string $extension): ?string
    {
        $relative = self::STORAGE_DIR . "/{$token}.{$extension}";

        return Storage::disk('local')->exists($relative)
            ? Storage::disk('local')->path($relative)
            : null;
    }

    private function readRows(array $state, string $path, int $maxRows, ?callable $rowFilter = null): array
    {
        return $this->files->rows(
            $path,
            $state['extension'],
            $state['sheet'],
            $maxRows,
            self::MAX_COLS,
            $state['delimiter'],
            $rowFilter
        );
    }

    /** @return array<int, string|null> */
    private function headerCells(array $state, string $path): array
    {
        $preview = $this->readRows($state, $path, (int) $state['header_row'] + 1);

        return $preview['rows'][$state['header_row']] ?? [];
    }

    /** @return array<int, string|null>|null */
    private function headerCellsSafe(array $state, string $path): ?array
    {
        try {
            return $this->headerCells($state, $path);
        } catch (Throwable) {
            return null;
        }
    }

    /** Streaming pass over the whole file: counts values without keeping rows. */
    private function countCategoryValues(array $state, string $path, int $column): array
    {
        $counts = [];
        $headerRow = (int) $state['header_row'];
        $this->readRows($state, $path, 1, function (array $cells, int $index) use (&$counts, $headerRow, $column): bool {
            if ($index > $headerRow) {
                $hasAnyValue = array_filter($cells, fn ($c) => trim((string) ($c ?? '')) !== '') !== [];
                if ($hasAnyValue) {
                    $value = trim((string) ($cells[$column] ?? ''));
                    $value = $value === '' ? self::EMPTY_CATEGORY : $value;
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }

            return false; // never keep rows — pure counting pass
        });
        arsort($counts);

        return $counts;
    }

    /**
     * Full pipeline: read (with category filter) → normalize/derive → validate.
     *
     * @return array{rows: array, normalized: array, truncated: bool}
     */
    private function validatedRows(array $state, string $path): array
    {
        $headerRow = (int) $state['header_row'];
        $category = null;
        $rowFilter = null;

        if (! ($state['category']['skipped'] ?? true) && ($state['category']['selected'] ?? null) !== null) {
            $column = (int) $state['category']['column'];
            $selected = (array) $state['category']['selected'];
            // The service compares raw cell values — translate the visible
            // "(zonder groep)" bucket back to the empty cell it represents.
            $category = ['column' => $column, 'selected' => array_map(
                fn (string $v) => $v === self::EMPTY_CATEGORY ? '' : $v,
                $selected
            )];
            $rowFilter = function (array $cells, int $index) use ($headerRow, $column, $selected): bool {
                if ($index <= $headerRow) {
                    return true;
                }
                $value = trim((string) ($cells[$column] ?? ''));

                return in_array($value === '' ? self::EMPTY_CATEGORY : $value, $selected, true);
            };
        }

        $data = $this->readRows($state, $path, self::MAX_ROWS, $rowFilter);

        $normalized = $this->guided->normalizeRows(
            $data['rows'],
            $headerRow,
            (array) $state['column_map'],
            (string) $state['decimal_format'],
            [
                'supplier'      => $state['supplier_name'],
                'brand'         => $state['brand_name'],
                'price_meaning' => $state['price_meaning'],
                'category'      => $category,
                'type_fallback' => $state['type_fallback'],
                'header_cells'  => $data['rows'][$headerRow] ?? [],
            ]
        );

        return [
            'rows'       => $this->importer->validateRows($normalized),
            'normalized' => $normalized,
            'truncated'  => $data['truncated'],
        ];
    }

    private function expired(?string $token = null): RedirectResponse
    {
        if ($token !== null) {
            $this->forgetState($token);
            foreach (['csv', 'txt', 'xlsx'] as $ext) {
                Storage::disk('local')->delete(self::STORAGE_DIR . "/{$token}.{$ext}");
            }
        }

        return redirect()->route('admin.hvac.import.index')
            ->withErrors(['guided_file' => 'De begeleide import is verlopen of geannuleerd. Upload het bestand opnieuw.']);
    }

    private function forget(string $token, string $extension): void
    {
        Storage::disk('local')->delete(self::STORAGE_DIR . "/{$token}.{$extension}");
        $this->forgetState($token);
    }

    private function forgetState(string $token): void
    {
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
