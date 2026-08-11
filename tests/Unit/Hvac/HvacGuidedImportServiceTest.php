<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\Import\HvacGuidedImportService;
use PHPUnit\Framework\TestCase;

class HvacGuidedImportServiceTest extends TestCase
{
    private HvacGuidedImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HvacGuidedImportService();
    }

    /**
     * CatalogFR-shaped mini sheet: header row 0, columns
     * 0=ProductID 1=LabelFR 2=LabelNL 3=ProviderName 4=ProducerID 5=BrutPrice 6=GroupName
     */
    private function rows(): array
    {
        return [
            0 => ['ProductID', 'LabelFR', 'LabelNL', 'ProviderName', 'ProducerID', 'BrutPrice', 'GroupName'],
            1 => ['118970', 'TB clim murale intérieure 2,5 kW', 'TB airco binnenunit wandmodel 2,5 kW', 'TESTBRAND', 'TB-IN-25', '403,5', 'Climatiseurs'],
            2 => ['118972', 'TB groupe extérieur mono 3,5 kW', 'TB airco buitenunit mono 3,5 kW', 'TESTBRAND', 'TB-OUT-35', '780,25', 'Climatiseurs'],
            3 => ['118974', 'TB télécommande', 'TB afstandsbediening', 'TESTBRAND', 'TB-RC-01', '45,9', 'Climatiseurs'],
            4 => ['218970', 'Robusta pompe vide-cave', 'Robusta kelderpomp', 'TESTPUMP', 'TP-200', '403,5', 'Pompes vide-cave'],
            5 => ['318971', 'Barre de douche 90cm', null, 'TESTSAN', 'TS-BAR-02', '89,5', 'Barres de douche'],
        ];
    }

    private function columnMap(): array
    {
        return [0 => 'sku', 1 => 'name_fallback', 2 => 'name', 3 => 'brand', 4 => 'model', 5 => 'price', 6 => 'product_type_source'];
    }

    private function normalize(array $options = []): array
    {
        $map = [0 => 'sku', 1 => 'name_fallback', 2 => 'name', 3 => 'brand', 4 => 'model', 5 => 'price'];

        return $this->service->normalizeRows($this->rows(), 0, $map, 'auto', $options + [
            'supplier'     => 'TestSupplier BV',
            'header_cells' => $this->rows()[0],
        ]);
    }

    public function test_category_filter_keeps_only_selected_rows(): void
    {
        $result = $this->normalize([
            'category' => ['column' => 6, 'selected' => ['Climatiseurs']],
        ]);

        $this->assertCount(3, $result);
        $this->assertSame(['118970', '118972', '118974'], array_column(array_column($result, 'raw'), 'sku'));
    }

    public function test_supplier_is_filled_from_wizard_choice_when_not_mapped(): void
    {
        $result = $this->normalize();

        $this->assertSame('TestSupplier BV', $result[0]['raw']['supplier']);
        $this->assertSame('manual', $result[0]['provenance']['fields']['supplier']);
    }

    public function test_name_falls_back_to_secondary_label_when_primary_empty(): void
    {
        $result = $this->normalize();

        $shower = $result[4]; // row 5: LabelNL empty
        $this->assertSame('Barre de douche 90cm', $shower['raw']['name']);
        $this->assertStringStartsWith('derived', $shower['provenance']['fields']['name']);

        // Primary name wins when present.
        $this->assertSame('TB airco binnenunit wandmodel 2,5 kW', $result[0]['raw']['name']);
    }

    public function test_gross_price_meaning_never_fills_price_columns_and_flags_review(): void
    {
        $result = $this->normalize(['price_meaning' => 'gross']);

        $row = $result[0];
        $this->assertArrayNotHasKey('purchase_price_excl_vat', $row['raw']);
        $this->assertArrayNotHasKey('sale_price_excl_vat', $row['raw']);
        $this->assertSame('gross', $row['provenance']['price']['meaning']);
        $this->assertSame('403,5', $row['provenance']['price']['raw']);
        $this->assertTrue($row['provenance']['needs_review']);
    }

    public function test_net_purchase_price_meaning_fills_purchase_price(): void
    {
        $result = $this->normalize(['price_meaning' => 'net_purchase']);

        $this->assertSame('403,5', $result[0]['raw']['purchase_price_excl_vat']);
        // (the row is still review-flagged, but only because its capacity was
        // derived from the name — a known price never flags review by itself)
        $this->assertSame('column:BrutPrice', $result[0]['provenance']['fields']['purchase_price_excl_vat']);
    }

    public function test_unknown_price_meaning_flags_review_but_does_not_block(): void
    {
        $result = $this->normalize(['price_meaning' => 'unknown']);

        $this->assertArrayNotHasKey('purchase_price_excl_vat', $result[0]['raw']);
        $this->assertTrue($result[0]['provenance']['needs_review']);
    }

    public function test_model_falls_back_to_sku_when_not_mapped(): void
    {
        $map = [0 => 'sku', 2 => 'name', 3 => 'brand', 5 => 'price'];
        $result = $this->service->normalizeRows($this->rows(), 0, $map, 'auto', [
            'supplier' => 'TestSupplier BV', 'header_cells' => $this->rows()[0],
        ]);

        $this->assertSame('118970', $result[0]['raw']['model']);
        $this->assertSame('derived:sku', $result[0]['provenance']['fields']['model']);
    }

    public function test_product_type_is_inferred_conservatively_from_name(): void
    {
        $result = $this->normalize(['price_meaning' => 'net_purchase']);

        $this->assertSame('indoor_unit', $result[0]['raw']['product_type']);
        $this->assertSame('outdoor_unit', $result[1]['raw']['product_type']);

        // Remote control: no confident keyword → empty (visible "Type controleren").
        $this->assertSame('', $result[2]['raw']['product_type']);
        $this->assertTrue($result[2]['provenance']['needs_review']);
    }

    public function test_type_fallback_fills_uninferable_rows_and_marks_them(): void
    {
        $result = $this->normalize(['price_meaning' => 'net_purchase', 'type_fallback' => 'installation_accessory']);

        $remote = $result[2];
        $this->assertSame('installation_accessory', $remote['raw']['product_type']);
        $this->assertSame('manual:fallback', $remote['provenance']['fields']['product_type']);

        // Inferred rows keep their inferred type, not the fallback.
        $this->assertSame('indoor_unit', $result[0]['raw']['product_type']);
    }

    public function test_cooling_capacity_is_derived_from_name_for_units_and_flagged(): void
    {
        $result = $this->normalize(['price_meaning' => 'net_purchase']);

        $indoor = $result[0]; // "TB airco binnenunit wandmodel 2,5 kW"
        $this->assertSame('2.5', $indoor['raw']['cooling_capacity_kw']);
        $this->assertSame('derived:naam', $indoor['provenance']['fields']['cooling_capacity_kw']);
        $this->assertTrue($indoor['provenance']['needs_review'], 'derived capacity must be checked by the admin');

        // Non-unit rows never get a derived capacity.
        $pump = $result[3];
        $this->assertArrayNotHasKey('cooling_capacity_kw', $pump['raw']);
    }

    public function test_watt_capacities_are_converted_to_kilowatts(): void
    {
        $rows = [
            0 => ['ProductID', 'LabelNL'],
            1 => ['984062', 'Nagano buitenunit 5 posten 10500W R32'],
            2 => ['984063', 'Nagano buitenunit 220V zonder vermogen'],
        ];
        $map = [0 => 'sku', 1 => 'name'];

        $result = $this->service->normalizeRows($rows, 0, $map, 'auto', [
            'supplier' => 'X', 'brand' => 'Y', 'header_cells' => $rows[0],
        ]);

        $this->assertSame('10.5', $result[0]['raw']['cooling_capacity_kw']);
        // "220V" must never be read as a capacity.
        $this->assertArrayNotHasKey('cooling_capacity_kw', $result[1]['raw']);
    }

    public function test_dash_placeholder_cells_are_treated_as_empty(): void
    {
        $rows = [
            0 => ['ProductID', 'LabelNL', 'ProducerID'],
            1 => ['128774', 'Set console vloermodel R32', '-'],
        ];
        $map = [0 => 'sku', 1 => 'name', 2 => 'model'];

        $result = $this->service->normalizeRows($rows, 0, $map, 'auto', [
            'supplier' => 'X', 'brand' => 'Y', 'header_cells' => $rows[0],
        ]);

        // "-" is a supplier placeholder, not a model (and not a "formula"):
        // the model falls back to the SKU.
        $this->assertSame('128774', $result[0]['raw']['model']);
        $this->assertSame('derived:sku', $result[0]['provenance']['fields']['model']);
    }

    public function test_ean_is_recorded_as_provenance_not_as_raw_field(): void
    {
        $rows = $this->rows();
        $rows[1][7] = '5400237170001';
        $rows[0][7] = 'EAN';
        $map = [0 => 'sku', 2 => 'name', 3 => 'brand', 5 => 'price', 7 => 'ean'];

        $result = $this->service->normalizeRows($rows, 0, $map, 'auto', [
            'supplier' => 'TestSupplier BV', 'header_cells' => $rows[0],
        ]);

        $this->assertArrayNotHasKey('ean', $result[0]['raw']);
        $this->assertSame('5400237170001', $result[0]['provenance']['ean']);
    }
}
