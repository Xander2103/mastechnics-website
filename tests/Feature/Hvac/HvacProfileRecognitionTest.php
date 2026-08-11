<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacMappingProfile;
use App\Services\Hvac\Import\HvacGuidedImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFrFixture;
use Tests\TestCase;

class HvacProfileRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(array $overrides = []): HvacMappingProfile
    {
        return HvacMappingProfile::create(array_merge([
            'name'           => 'TestSupplier — automatisch',
            'supplier_name'  => 'TestSupplier BV',
            'header_row'     => 1,
            'column_map'     => ['ProductID' => 'sku', 'LabelNL' => 'name', 'BrutPrice' => 'price'],
            'decimal_format' => 'auto',
            'delimiter'      => "\t",
            'category_filter' => ['column_header' => 'GroupName', 'selected' => ['Climatiseurs']],
            'price_semantics' => ['column_header' => 'BrutPrice', 'meaning' => 'gross'],
            'source_headers' => ['ProductID', 'LabelNL', 'LabelFR', 'BrutPrice', 'GroupName'],
            'is_active'      => true,
        ], $overrides));
    }

    public function test_profile_is_recognized_by_header_signature(): void
    {
        $profile = $this->makeProfile();

        $found = (new HvacGuidedImportService())->recognizeProfile(CatalogFrFixture::HEADERS);

        $this->assertNotNull($found);
        $this->assertTrue($profile->is($found));
    }

    public function test_profile_with_missing_headers_is_not_recognized(): void
    {
        $this->makeProfile(['source_headers' => ['ProductID', 'KolomDieNietBestaat']]);

        $found = (new HvacGuidedImportService())->recognizeProfile(CatalogFrFixture::HEADERS);

        $this->assertNull($found);
    }

    public function test_inactive_profiles_are_ignored(): void
    {
        $this->makeProfile(['is_active' => false]);

        $this->assertNull((new HvacGuidedImportService())->recognizeProfile(CatalogFrFixture::HEADERS));
    }

    public function test_most_specific_profile_wins(): void
    {
        $this->makeProfile(['name' => 'Generiek', 'source_headers' => ['ProductID', 'LabelNL']]);
        $specific = $this->makeProfile(['name' => 'Specifiek', 'source_headers' => ['ProductID', 'LabelNL', 'LabelFR', 'BrutPrice', 'GroupName', 'ProducerID']]);

        $found = (new HvacGuidedImportService())->recognizeProfile(CatalogFrFixture::HEADERS);

        $this->assertTrue($specific->is($found));
    }

    public function test_wizard_settings_round_trip_through_the_model(): void
    {
        $profile = $this->makeProfile();
        $fresh = HvacMappingProfile::find($profile->id);

        $this->assertSame("\t", $fresh->delimiter);
        $this->assertSame(['Climatiseurs'], $fresh->category_filter['selected']);
        $this->assertSame('gross', $fresh->price_semantics['meaning']);
    }
}
