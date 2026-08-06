<?php

namespace App\Services\Hvac;

use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
use Illuminate\Support\Facades\DB;

/**
 * CSV import for manufacturer compatibility rules. Products are referenced
 * by SKU; both sides must already exist in the catalog. All validation
 * happens before anything is written; the import itself is one transaction.
 */
class HvacCompatibilityCsvImporter
{
    public const COLUMNS = [
        'parent_sku', 'compatible_sku', 'compatibility_type',
        'minimum_connected_capacity_kw', 'maximum_connected_capacity_kw',
        'maximum_units', 'notes',
    ];

    public const REQUIRED_COLUMNS = ['parent_sku', 'compatible_sku', 'compatibility_type'];

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
                $globalErrors[] = 'Bestand was geen UTF-8; automatisch geconverteerd vanuit Windows-1252.';
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
        $seen = [];
        foreach (array_slice($lines, 1) as $i => $line) {
            $lineNumber = $i + 2;
            $values = str_getcsv($line, $delimiter);
            $raw = [];
            foreach ($header as $col => $name) {
                $raw[$name] = trim((string) ($values[$col] ?? ''));
            }

            $row = $this->validateRow($raw, $lineNumber);

            // Only clean rows claim the combination — a rejected row is never
            // imported and must not shadow a later valid one.
            $key = strtolower(($row['data']['parent_sku'] ?? '') . '|' . ($row['data']['compatible_sku'] ?? '') . '|' . ($row['data']['compatibility_type'] ?? ''));
            if (isset($seen[$key])) {
                $row['errors'][] = "Dubbele regel: dezelfde combinatie kwam al voor op lijn {$seen[$key]}.";
            } elseif ($row['errors'] === []) {
                $seen[$key] = $lineNumber;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'global_errors' => $globalErrors];
    }

    private function validateRow(array $raw, int $lineNumber): array
    {
        $errors = [];
        $data = [
            'parent_sku'         => $raw['parent_sku'] ?? '',
            'compatible_sku'     => $raw['compatible_sku'] ?? '',
            'compatibility_type' => strtolower($raw['compatibility_type'] ?? ''),
            'notes'              => ($raw['notes'] ?? '') ?: null,
        ];

        foreach (self::REQUIRED_COLUMNS as $field) {
            if (($raw[$field] ?? '') === '') {
                $errors[] = "Kolom '{$field}' is verplicht.";
            }
        }

        foreach (['parent_sku', 'compatible_sku', 'compatibility_type', 'notes'] as $field) {
            $value = (string) ($raw[$field] ?? '');
            if ($value !== '' && preg_match('/^[=@\t\r]/', $value) === 1) {
                $errors[] = "Kolom '{$field}': waarde lijkt op een spreadsheetformule en is geweigerd.";
            }
        }

        if ($data['compatibility_type'] !== ''
            && ! in_array($data['compatibility_type'], HvacProductCompatibility::TYPES, true)) {
            $errors[] = "Onbekend compatibiliteitstype '{$data['compatibility_type']}' (toegestaan: " . implode(', ', HvacProductCompatibility::TYPES) . ').';
        }

        if ($data['parent_sku'] !== '' && strcasecmp($data['parent_sku'], $data['compatible_sku']) === 0) {
            $errors[] = 'Een product kan niet compatibel zijn met zichzelf.';
        }

        foreach (['minimum_connected_capacity_kw', 'maximum_connected_capacity_kw'] as $field) {
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

        $maxUnits = $raw['maximum_units'] ?? '';
        if ($maxUnits === '') {
            $data['maximum_units'] = null;
        } elseif (! ctype_digit($maxUnits) || (int) $maxUnits < 1 || (int) $maxUnits > 20) {
            $errors[] = "Kolom 'maximum_units': '{$maxUnits}' is geen geldig aantal (1–20).";
            $data['maximum_units'] = null;
        } else {
            $data['maximum_units'] = (int) $maxUnits;
        }

        // Both SKUs must exist in the catalog and be unambiguous.
        $action = 'create';
        if ($errors === []) {
            $parent = $this->resolveSku($data['parent_sku'], $errors);
            $compatible = $this->resolveSku($data['compatible_sku'], $errors);

            if ($parent !== null && $compatible !== null) {
                $data['parent_product_id'] = $parent->id;
                $data['compatible_product_id'] = $compatible->id;

                $exists = HvacProductCompatibility::where('parent_product_id', $parent->id)
                    ->where('compatible_product_id', $compatible->id)
                    ->where('compatibility_type', $data['compatibility_type'])
                    ->exists();
                $action = $exists ? 'update' : 'create';
            }
        }

        return [
            'line'   => $lineNumber,
            'data'   => $data,
            'action' => $action,
            'errors' => $errors,
        ];
    }

    private function resolveSku(string $sku, array &$errors): ?HvacProduct
    {
        if ($sku === '') {
            return null;
        }

        $matches = HvacProduct::where('sku', $sku)->limit(2)->get();

        if ($matches->isEmpty()) {
            $errors[] = "SKU '{$sku}' bestaat niet in de catalogus. Importeer eerst de producten.";

            return null;
        }
        if ($matches->count() > 1) {
            $errors[] = "SKU '{$sku}' komt bij meerdere leveranciers voor en is niet eenduidig.";

            return null;
        }

        return $matches->first();
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $rows): array
    {
        return DB::transaction(function () use ($rows) {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                if ($row['errors'] !== [] || ! isset($row['data']['parent_product_id'])) {
                    $skipped++;
                    continue;
                }

                $data = $row['data'];

                $rule = HvacProductCompatibility::updateOrCreate(
                    [
                        'parent_product_id'     => $data['parent_product_id'],
                        'compatible_product_id' => $data['compatible_product_id'],
                        'compatibility_type'    => $data['compatibility_type'],
                    ],
                    [
                        'minimum_connected_capacity_kw' => $data['minimum_connected_capacity_kw'],
                        'maximum_connected_capacity_kw' => $data['maximum_connected_capacity_kw'],
                        'maximum_units'                 => $data['maximum_units'],
                        'notes'                         => $data['notes'],
                        'is_active'                     => true,
                    ]
                );

                $rule->wasRecentlyCreated ? $created++ : $updated++;
            }

            return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
        });
    }

    public static function template(): string
    {
        $lines = [
            implode(';', self::COLUMNS),
            'TEST-MULTI-80;TEST-BIN-25;multi_split_indoor;;;4;voorbeeldrij - verwijderen voor echte import',
            'TEST-BUITEN-35;TEST-BIN-35;indoor_outdoor;;;;voorbeeldrij - verwijderen voor echte import',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }
}
