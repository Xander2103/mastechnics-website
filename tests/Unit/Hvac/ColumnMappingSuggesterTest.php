<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\Import\ColumnMappingSuggester;
use PHPUnit\Framework\TestCase;
use Tests\Support\CatalogFrFixture;

class ColumnMappingSuggesterTest extends TestCase
{
    private ColumnMappingSuggester $suggester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suggester = new ColumnMappingSuggester();
    }

    /** @return array<string, array{field: string|null, confidence: string}> keyed by header */
    private function suggestFor(array $headers): array
    {
        $byHeader = [];
        foreach ($this->suggester->suggest($headers) as $i => $suggestion) {
            $byHeader[$headers[$i]] = $suggestion;
        }

        return $byHeader;
    }

    public function test_catalogfr_headers_map_to_expected_fields(): void
    {
        $s = $this->suggestFor(CatalogFrFixture::HEADERS);

        $this->assertSame(['field' => 'sku', 'confidence' => 'exact'], $s['ProductID']);
        $this->assertSame(['field' => 'name', 'confidence' => 'exact'], $s['LabelNL']);
        $this->assertSame(['field' => 'name_fallback', 'confidence' => 'exact'], $s['LabelFR']);
        $this->assertSame(['field' => 'model', 'confidence' => 'exact'], $s['ProducerID']);
        $this->assertSame(['field' => 'ean', 'confidence' => 'exact'], $s['EAN']);
        $this->assertSame('lead_time_days', $s['DeliveryDelay']['field']);

        // Brand from ProviderName: exact, and NOT poisoned by ProviderID.
        $this->assertSame(['field' => 'brand', 'confidence' => 'exact'], $s['ProviderName']);
    }

    public function test_gross_price_never_auto_maps_to_purchase_or_sale(): void
    {
        foreach (['BrutPrice', 'Brutoprijs', 'Bruto prijs', 'Catalogusprijs', 'Prix catalogue', 'List price'] as $header) {
            $suggestion = $this->suggestFor([$header])[$header];

            $this->assertSame('price', $suggestion['field'], "{$header} must map to the ask-first price field");
            $this->assertNotSame('purchase_price_excl_vat', $suggestion['field']);
            $this->assertNotSame('sale_price_excl_vat', $suggestion['field']);
        }
    }

    public function test_explicit_net_and_sale_prices_still_map_directly(): void
    {
        $s = $this->suggestFor(['Netto prijs', 'Verkoopprijs']);

        $this->assertSame(['field' => 'purchase_price_excl_vat', 'confidence' => 'exact'], $s['Netto prijs']);
        $this->assertSame(['field' => 'sale_price_excl_vat', 'confidence' => 'exact'], $s['Verkoopprijs']);
    }

    public function test_two_exact_matches_for_same_field_are_both_downgraded(): void
    {
        $s = $this->suggestFor(['Artikelnummer', 'Productcode']);

        $this->assertSame('fuzzy', $s['Artikelnummer']['confidence']);
        $this->assertSame('fuzzy', $s['Productcode']['confidence']);
    }

    public function test_a_fuzzy_duplicate_does_not_downgrade_an_exact_match(): void
    {
        // ProviderName → brand (exact); ProviderID contains 'provider' → fuzzy
        // brand hint. The exact one must survive for auto-mapping.
        $s = $this->suggestFor(['ProviderName', 'ProviderID']);

        $this->assertSame('exact', $s['ProviderName']['confidence']);
        $this->assertSame('brand', $s['ProviderName']['field']);
    }

    public function test_unknown_headers_get_no_suggestion(): void
    {
        $s = $this->suggestFor(['UpgradeDate', 'Intrastat', 'SequenceID']);

        foreach ($s as $suggestion) {
            $this->assertNull($suggestion['field']);
        }
    }

    public function test_targets_include_virtual_fields(): void
    {
        $targets = ColumnMappingSuggester::targets();

        $this->assertContains('sku', $targets);
        $this->assertContains('price', $targets);
        $this->assertContains('name_fallback', $targets);
        $this->assertContains('ean', $targets);
    }
}
