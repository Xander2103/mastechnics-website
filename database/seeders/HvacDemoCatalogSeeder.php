<?php

namespace Database\Seeders;

use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
use App\Models\HvacSupplier;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT-ONLY demo catalog with clearly fictional TEST products, so the
 * full flow (single split, two-room multi split, accessories, pricing,
 * budget/recommended/premium, compatibility) can be rehearsed safely.
 *
 * - Never runs in production (hard guard below).
 * - Never runs automatically: only via `php artisan db:seed --class=HvacDemoCatalogSeeder`.
 * - Every product name and SKU starts with TEST; the admin shows the banner
 *   "Testcatalogus — niet gebruiken voor echte offertes" while any TEST
 *   product is active.
 */
class HvacDemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'HvacDemoCatalogSeeder is uitsluitend voor ontwikkeling/test en mag nooit in productie draaien.'
            );
        }

        $brand = HvacBrand::firstOrCreate(['slug' => 'testmerk'], ['name' => 'TESTMERK', 'is_active' => true]);
        $supplier = HvacSupplier::firstOrCreate(['name' => 'TEST Leverancier BV'], ['is_active' => true]);

        $base = [
            'hvac_brand_id'    => $brand->id,
            'hvac_supplier_id' => $supplier->id,
            'is_active'        => true,
        ];

        $products = [
            // Single-split sets → budget / recommended / premium for a ± 2.6 kW room.
            ['sku' => 'TEST-SET-28', 'model' => 'TEST Set 28', 'name' => 'TEST single split 2.8 kW (budget)', 'product_type' => 'single_split_set', 'cooling_capacity_kw' => 2.8, 'maximum_pipe_length_m' => 15, 'maximum_height_difference_m' => 10, 'purchase_price_excl_vat' => 700, 'default_sale_price_excl_vat' => 1100, 'stock_quantity' => 0, 'lead_time_days' => 10, 'sound_level_db' => 24, 'seer' => 6.1, 'scop' => 4.0, 'wifi_included' => false, 'supported_voltage' => '230V mono', 'required_breaker_a' => 16, 'required_cable' => '3G2.5'],
            ['sku' => 'TEST-SET-35', 'model' => 'TEST Set 35', 'name' => 'TEST single split 3.5 kW (aanbevolen)', 'product_type' => 'single_split_set', 'cooling_capacity_kw' => 3.5, 'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 12, 'purchase_price_excl_vat' => 900, 'default_sale_price_excl_vat' => 1450, 'stock_quantity' => 5, 'lead_time_days' => 3, 'sound_level_db' => 21, 'seer' => 7.0, 'scop' => 4.4, 'wifi_included' => true, 'supported_voltage' => '230V mono', 'required_breaker_a' => 16, 'required_cable' => '3G2.5'],
            ['sku' => 'TEST-SET-35-PREM', 'model' => 'TEST Set 35 Premium', 'name' => 'TEST single split 3.5 kW (premium, fluisterstil)', 'product_type' => 'single_split_set', 'cooling_capacity_kw' => 3.5, 'maximum_pipe_length_m' => 25, 'maximum_height_difference_m' => 15, 'purchase_price_excl_vat' => 1400, 'default_sale_price_excl_vat' => 2100, 'stock_quantity' => 2, 'lead_time_days' => 5, 'sound_level_db' => 17, 'seer' => 8.5, 'scop' => 5.1, 'wifi_included' => true, 'supported_voltage' => '230V mono', 'required_breaker_a' => 16, 'required_cable' => '3G2.5'],

            // Two-room multi split: indoor + outdoor + compatibility.
            ['sku' => 'TEST-BIN-25', 'model' => 'TEST Binnenunit 25', 'name' => 'TEST wandunit 2.5 kW', 'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 2.5, 'purchase_price_excl_vat' => 450, 'default_sale_price_excl_vat' => 700, 'stock_quantity' => 6, 'lead_time_days' => 3, 'sound_level_db' => 20, 'seer' => 7.2, 'scop' => 4.5, 'wifi_included' => true],
            ['sku' => 'TEST-MULTI-50', 'model' => 'TEST Multi 50', 'name' => 'TEST multi-split buitenunit 5.0 kW', 'product_type' => 'multi_split_outdoor', 'cooling_capacity_kw' => 5.0, 'minimum_capacity_kw' => 3.0, 'maximum_capacity_kw' => 6.0, 'maximum_connected_indoor_units' => 3, 'maximum_pipe_length_m' => 40, 'maximum_pipe_length_per_unit_m' => 20, 'maximum_height_difference_m' => 10, 'purchase_price_excl_vat' => 1300, 'default_sale_price_excl_vat' => 1950, 'stock_quantity' => 2, 'lead_time_days' => 5, 'sound_level_db' => 46, 'seer' => 6.8, 'scop' => 4.2, 'supported_voltage' => '230V mono', 'required_breaker_a' => 20, 'required_cable' => '3G2.5'],

            // Accessories with prices so pricing/margin fully resolve.
            ['sku' => 'TEST-ACC-BEUGEL', 'model' => 'TEST Muurbeugel', 'name' => 'TEST muurbeugel buitenunit', 'product_type' => 'wall_bracket', 'purchase_price_excl_vat' => 18, 'default_sale_price_excl_vat' => 35, 'stock_quantity' => 20],
            ['sku' => 'TEST-ACC-DEMPER', 'model' => 'TEST Dempers', 'name' => 'TEST trillingsdempers (set)', 'product_type' => 'vibration_damper', 'purchase_price_excl_vat' => 8, 'default_sale_price_excl_vat' => 18, 'stock_quantity' => 30],
            ['sku' => 'TEST-ACC-LEIDING', 'model' => 'TEST Leiding', 'name' => 'TEST koelleiding per meter', 'product_type' => 'pipe', 'purchase_price_excl_vat' => 9, 'default_sale_price_excl_vat' => 16, 'stock_quantity' => 200],
            ['sku' => 'TEST-ACC-GOOT', 'model' => 'TEST Goot', 'name' => 'TEST leidinggoot per meter', 'product_type' => 'trunking', 'purchase_price_excl_vat' => 4, 'default_sale_price_excl_vat' => 9, 'stock_quantity' => 150],
            ['sku' => 'TEST-ACC-KABEL', 'model' => 'TEST Kabel', 'name' => 'TEST interconnectiekabel per meter', 'product_type' => 'electrical_accessory', 'purchase_price_excl_vat' => 2, 'default_sale_price_excl_vat' => 5, 'stock_quantity' => 300],
            ['sku' => 'TEST-ACC-AFVOER', 'model' => 'TEST Afvoer', 'name' => 'TEST condensafvoerslang per meter', 'product_type' => 'drain_hose', 'purchase_price_excl_vat' => 1.5, 'default_sale_price_excl_vat' => 4, 'stock_quantity' => 200],
            ['sku' => 'TEST-ACC-POMP', 'model' => 'TEST Pomp', 'name' => 'TEST condensaatpomp', 'product_type' => 'condensate_pump', 'purchase_price_excl_vat' => 55, 'default_sale_price_excl_vat' => 95, 'stock_quantity' => 8],
            ['sku' => 'TEST-ACC-WIFI', 'model' => 'TEST Wifi', 'name' => 'TEST Wi-Fi-module', 'product_type' => 'wifi_module', 'purchase_price_excl_vat' => 40, 'default_sale_price_excl_vat' => 75, 'stock_quantity' => 10],
            ['sku' => 'TEST-ACC-KOELMIDDEL', 'model' => 'TEST Koelmiddel', 'name' => 'TEST extra koelmiddel (per vulling)', 'product_type' => 'refrigerant', 'purchase_price_excl_vat' => 30, 'default_sale_price_excl_vat' => 60, 'stock_quantity' => 12],
        ];

        foreach ($products as $attributes) {
            HvacProduct::updateOrCreate(
                ['hvac_supplier_id' => $supplier->id, 'sku' => $attributes['sku']],
                $attributes + $base
            );
        }

        $indoor = HvacProduct::where('sku', 'TEST-BIN-25')->first();
        $outdoor = HvacProduct::where('sku', 'TEST-MULTI-50')->first();

        HvacProductCompatibility::updateOrCreate(
            [
                'parent_product_id'     => $outdoor->id,
                'compatible_product_id' => $indoor->id,
                'compatibility_type'    => 'multi_split_indoor',
            ],
            ['maximum_units' => 3, 'is_active' => true, 'notes' => 'TEST-demodata']
        );

        $this->command?->warn('TEST-democatalogus geladen — niet gebruiken voor echte offertes.');
    }
}
