<?php

namespace App\Services\Hvac;

use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CSV import for the HVAC product catalog.
 *
 * Safety: UTF-8 validation (with a Windows-1252 rescue attempt), spreadsheet
 * formula-injection rejection, comma/point decimals, row-level errors, and a
 * single DB transaction so an import can never leave the catalog half-written.
 * Products are identified by supplier + SKU.
 */
class HvacCsvImporter
{
    public const REQUIRED_COLUMNS = ['supplier', 'brand', 'sku', 'model', 'name', 'product_type'];

    public const COLUMNS = [
        'supplier', 'brand', 'sku', 'model', 'name', 'product_type',
        'cooling_capacity_kw', 'heating_capacity_kw', 'minimum_capacity_kw', 'maximum_capacity_kw',
        'purchase_price_excl_vat', 'sale_price_excl_vat', 'stock_quantity', 'lead_time_days',
        'voltage', 'phase', 'breaker_a', 'cable',
        'liquid_pipe_diameter', 'gas_pipe_diameter',
        'max_pipe_length_m', 'max_pipe_length_per_unit_m', 'max_height_difference_m',
        'max_connected_indoor_units', 'sound_level_db', 'seer', 'scop', 'wifi_included',
        'active', 'notes',
    ];

    // Product types that make no sense without a cooling capacity.
    private const CAPACITY_REQUIRED_TYPES = [
        'indoor_unit', 'outdoor_unit', 'single_split_set', 'multi_split_outdoor',
    ];

    private const NUMERIC_FIELDS = [
        'cooling_capacity_kw', 'heating_capacity_kw', 'minimum_capacity_kw', 'maximum_capacity_kw',
        'purchase_price_excl_vat', 'sale_price_excl_vat', 'sound_level_db', 'seer', 'scop',
        'max_pipe_length_m', 'max_pipe_length_per_unit_m', 'max_height_difference_m',
    ];

    private const INTEGER_FIELDS = [
        'stock_quantity', 'lead_time_days', 'breaker_a', 'max_connected_indoor_units',
    ];

    /**
     * @return array{rows: array, global_errors: array}
     */
    public function parse(string $contents): array
    {
        $globalErrors = [];

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $converted = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $contents);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $contents = $converted;
                $globalErrors[] = 'Bestand was geen UTF-8; automatisch geconverteerd vanuit Windows-1252. Controleer speciale tekens in de preview.';
            } else {
                return ['rows' => [], 'global_errors' => ['Bestandscodering wordt niet herkend. Bewaar het bestand als UTF-8 CSV.']];
            }
        }

        $contents = str_replace("\r\n", "\n", trim($contents, "\xEF\xBB\xBF \n\r"));
        $lines = array_values(array_filter(explode("\n", $contents), fn ($l) => trim($l) !== ''));

        if (count($lines) < 2) {
            return ['rows' => [], 'global_errors' => ['Het bestand bevat geen datarijen.']];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv($lines[0], $delimiter));

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missing !== []) {
            return ['rows' => [], 'global_errors' => ['Verplichte kolommen ontbreken: ' . implode(', ', $missing) . '.']];
        }

        $rows = [];
        $seenKeys = [];
        foreach (array_slice($lines, 1) as $i => $line) {
            $lineNumber = $i + 2;
            $values = str_getcsv($line, $delimiter);
            $raw = [];
            foreach ($header as $col => $name) {
                $raw[$name] = trim((string) ($values[$col] ?? ''));
            }

            $row = $this->validateRow($raw, $lineNumber);

            // The same supplier+SKU twice in one file would silently
            // last-win — flag it as a row error instead.
            // Only clean rows claim the supplier+SKU — a rejected row is never
            // imported and must not shadow a later valid one.
            $key = strtolower(($row['data']['supplier'] ?? '') . '|' . ($row['data']['sku'] ?? ''));
            if ($key !== '|' && isset($seenKeys[$key])) {
                $row['errors'][] = "Dubbele rij: leverancier + SKU kwamen al voor op lijn {$seenKeys[$key]}.";
            } elseif ($key !== '|' && $row['errors'] === []) {
                $seenKeys[$key] = $lineNumber;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'global_errors' => $globalErrors];
    }

    private function validateRow(array $raw, int $lineNumber): array
    {
        $errors = [];
        $data = [];

        foreach (self::REQUIRED_COLUMNS as $field) {
            if (($raw[$field] ?? '') === '') {
                $errors[] = "Kolom '{$field}' is verplicht.";
            }
        }

        // Spreadsheet formula-injection guard on all text values.
        foreach ($raw as $field => $value) {
            if ($value !== '' && ! in_array($field, [...self::NUMERIC_FIELDS, ...self::INTEGER_FIELDS], true)) {
                if (preg_match('/^[=@\t\r]/', $value) === 1
                    || (preg_match('/^[+\-]/', $value) === 1 && ! is_numeric(str_replace(',', '.', $value)))) {
                    $errors[] = "Kolom '{$field}': waarde lijkt op een spreadsheetformule en is geweigerd.";
                }
            }
        }

        $productType = strtolower($raw['product_type'] ?? '');
        if ($productType !== '' && ! in_array($productType, HvacProduct::PRODUCT_TYPES, true)) {
            $errors[] = "Onbekend producttype '{$productType}'.";
        }

        foreach (self::NUMERIC_FIELDS as $field) {
            $value = $raw[$field] ?? '';
            if ($value === '') {
                $data[$field] = null;
                continue;
            }
            $normalized = str_replace(',', '.', $value);
            if (! is_numeric($normalized) || (float) $normalized < 0) {
                $errors[] = "Kolom '{$field}': '{$value}' is geen geldig positief getal.";
                $data[$field] = null;
            } else {
                $data[$field] = (float) $normalized;
            }
        }

        foreach (self::INTEGER_FIELDS as $field) {
            $value = $raw[$field] ?? '';
            if ($value === '') {
                $data[$field] = null;
                continue;
            }
            if (! ctype_digit($value)) {
                $errors[] = "Kolom '{$field}': '{$value}' is geen geldig geheel getal.";
                $data[$field] = null;
            } else {
                $data[$field] = (int) $value;
            }
        }

        foreach (['wifi_included', 'active'] as $boolField) {
            $value = strtolower($raw[$boolField] ?? '');
            if ($value === '') {
                $data[$boolField] = null;
                continue;
            }
            $data[$boolField] = match (true) {
                in_array($value, ['1', 'ja', 'yes', 'true'], true)  => true,
                in_array($value, ['0', 'nee', 'no', 'false'], true) => false,
                default => null,
            };
            if ($data[$boolField] === null) {
                $errors[] = "Kolom '{$boolField}': '{$raw[$boolField]}' is geen geldige ja/nee-waarde (gebruik ja/nee).";
            }
        }

        // Units without a cooling capacity can never be selected — reject early.
        if (in_array($productType, self::CAPACITY_REQUIRED_TYPES, true)
            && ($data['cooling_capacity_kw'] ?? null) === null) {
            $errors[] = "Producttype '{$productType}' vereist een koelvermogen (cooling_capacity_kw).";
        }

        // Voltage plausibility: free text is allowed, but it must at least
        // contain a recognisable voltage number.
        $voltage = $raw['voltage'] ?? '';
        if ($voltage !== '' && preg_match('/\d{3}/', $voltage) !== 1) {
            $errors[] = "Kolom 'voltage': '{$voltage}' wordt niet herkend (verwacht bv. '230V mono' of '400V tri').";
        }

        $data += [
            'supplier'             => $raw['supplier'] ?? '',
            'brand'                => $raw['brand'] ?? '',
            'sku'                  => $raw['sku'] ?? '',
            'model'                => $raw['model'] ?? '',
            'name'                 => $raw['name'] ?? '',
            'product_type'         => $productType,
            'voltage'              => ($raw['voltage'] ?? '') ?: null,
            'phase'                => ($raw['phase'] ?? '') ?: null,
            'cable'                => ($raw['cable'] ?? '') ?: null,
            'liquid_pipe_diameter' => ($raw['liquid_pipe_diameter'] ?? '') ?: null,
            'gas_pipe_diameter'    => ($raw['gas_pipe_diameter'] ?? '') ?: null,
            'notes'                => ($raw['notes'] ?? '') ?: null,
        ];

        $action = 'create';
        if ($errors === [] && $data['supplier'] !== '' && $data['sku'] !== '') {
            $supplier = HvacSupplier::whereRaw('LOWER(name) = ?', [strtolower($data['supplier'])])->first();
            if ($supplier !== null && HvacProduct::where('hvac_supplier_id', $supplier->id)->where('sku', $data['sku'])->exists()) {
                $action = 'update';
            }
        }

        return [
            'line'   => $lineNumber,
            'data'   => $data,
            'action' => $action,
            'errors' => $errors,
        ];
    }

    /**
     * Imports the VALID rows in one transaction. Rows with errors are never
     * written; the caller reports them.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $rows, string $mode): array
    {
        return DB::transaction(function () use ($rows, $mode) {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                if ($row['errors'] !== []) {
                    $skipped++;
                    continue;
                }

                $data = $row['data'];

                $supplier = HvacSupplier::firstOrCreate(
                    ['name' => $data['supplier']],
                    ['is_active' => true]
                );
                $brand = HvacBrand::firstOrCreate(
                    ['slug' => Str::slug($data['brand'])],
                    ['name' => $data['brand'], 'is_active' => true]
                );

                $existing = HvacProduct::where('hvac_supplier_id', $supplier->id)
                    ->where('sku', $data['sku'])
                    ->first();

                if ($existing !== null && $mode === 'create_only') {
                    $skipped++;
                    continue;
                }
                if ($existing === null && $mode === 'update_only') {
                    $skipped++;
                    continue;
                }

                $attributes = [
                    'hvac_brand_id'                  => $brand->id,
                    'model'                          => $data['model'],
                    'name'                           => $data['name'],
                    'product_type'                   => $data['product_type'],
                    'cooling_capacity_kw'            => $data['cooling_capacity_kw'],
                    'heating_capacity_kw'            => $data['heating_capacity_kw'],
                    'minimum_capacity_kw'            => $data['minimum_capacity_kw'],
                    'maximum_capacity_kw'            => $data['maximum_capacity_kw'],
                    'supported_voltage'              => $data['voltage'],
                    'supported_phase'                => $data['phase'],
                    'required_breaker_a'             => $data['breaker_a'],
                    'required_cable'                 => $data['cable'],
                    'liquid_pipe_diameter'           => $data['liquid_pipe_diameter'],
                    'gas_pipe_diameter'              => $data['gas_pipe_diameter'],
                    'maximum_pipe_length_m'          => $data['max_pipe_length_m'],
                    'maximum_pipe_length_per_unit_m' => $data['max_pipe_length_per_unit_m'],
                    'maximum_height_difference_m'    => $data['max_height_difference_m'],
                    'maximum_connected_indoor_units' => $data['max_connected_indoor_units'],
                    'purchase_price_excl_vat'        => $data['purchase_price_excl_vat'],
                    'default_sale_price_excl_vat'    => $data['sale_price_excl_vat'],
                    'stock_quantity'                 => $data['stock_quantity'],
                    'lead_time_days'                 => $data['lead_time_days'],
                    'sound_level_db'                 => $data['sound_level_db'],
                    'seer'                           => $data['seer'],
                    'scop'                           => $data['scop'],
                    'wifi_included'                  => $data['wifi_included'],
                ];

                if ($data['active'] !== null) {
                    $attributes['is_active'] = $data['active'];
                }
                if ($data['notes'] !== null) {
                    $attributes['metadata'] = ['notes' => $data['notes']];
                }

                if ($existing !== null) {
                    $existing->update($attributes);
                    $updated++;
                } else {
                    HvacProduct::create($attributes + [
                        'hvac_supplier_id' => $supplier->id,
                        'sku'              => $data['sku'],
                        'is_active'        => $data['active'] ?? true,
                    ]);
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
        });
    }

    /**
     * Sample CSV template (semicolon-delimited for Excel friendliness).
     */
    public static function template(): string
    {
        $lines = [
            implode(';', self::COLUMNS),
            'TEST Leverancier BV;TESTMERK;TEST-SET-25;TEST 25 Set;TEST single split 2.5 kW;single_split_set;2.5;3.2;;;780.00;1250.00;4;3;230V mono;1;16;3G2.5;1/4;3/8;20;;15;;19.5;6.5;4.6;ja;ja;voorbeeldrij - verwijderen voor echte import',
            'TEST Leverancier BV;TESTMERK;TEST-MULTI-80;TEST Multi 80;TEST multi-split buitenunit 8 kW;multi_split_outdoor;8.0;9.0;4.0;9.6;1900.00;2950.00;2;5;230V mono;1;25;3G4;1/4;1/2;60;25;15;4;48;7.1;4.2;nee;ja;voorbeeldrij - verwijderen voor echte import',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Error-report CSV for rejected rows (values sanitised against formula
     * injection on the way out).
     */
    public static function errorReport(array $errorRows): string
    {
        $out = "lijn;fouten;sku;model\r\n";
        foreach ($errorRows as $row) {
            $sanitise = function (string $v): string {
                return preg_match('/^[=@+\-\t]/', $v) === 1 ? "'" . $v : $v;
            };
            $out .= implode(';', [
                $row['line'],
                $sanitise(implode(' | ', $row['errors'])),
                $sanitise($row['data']['sku'] ?? ''),
                $sanitise($row['data']['model'] ?? ''),
            ]) . "\r\n";
        }

        return $out;
    }
}
