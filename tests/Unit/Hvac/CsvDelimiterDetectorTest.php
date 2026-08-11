<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\Import\CsvDelimiterDetector;
use PHPUnit\Framework\TestCase;

class CsvDelimiterDetectorTest extends TestCase
{
    private CsvDelimiterDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new CsvDelimiterDetector();
    }

    public function test_detects_tab_delimited_file_with_high_confidence(): void
    {
        // CatalogFR.csv shape: tabs everywhere, zero semicolons, commas only
        // inside decimal prices on some lines.
        $sample = implode("\n", [
            "ProductID\tLabelFR\tLabelNL\tBrutPrice\tGroupName",
            "118970\tRobusta pompe\tRobusta kelderpomp\t403,5\tPompes vide-cave",
            "551025\tErgosystem porte-savon\tErgosystem zeephouder\t40,4\tAccessoires",
            "496732\tBarre murale\tGlijstang\t318,72\tBarres de douche",
        ]);

        $result = $this->detector->detect($sample);

        $this->assertSame("\t", $result['delimiter']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_detects_semicolon_with_comma_decimals(): void
    {
        $sample = "Naam;Prijs;Aantal\nProduct A;1,50;3\nProduct B;2,75;1\n";

        $result = $this->detector->detect($sample);

        $this->assertSame(';', $result['delimiter']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_detects_comma_delimited_file(): void
    {
        $sample = "name,price,qty\nProduct A,1.50,3\nProduct B,2.75,1\n";

        $result = $this->detector->detect($sample);

        $this->assertSame(',', $result['delimiter']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_detects_pipe_delimited_file(): void
    {
        $sample = "sku|name|price\nA-1|Product A|10\nB-2|Product B|20\n";

        $result = $this->detector->detect($sample);

        $this->assertSame('|', $result['delimiter']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_quoted_values_containing_other_delimiters_do_not_confuse_detection(): void
    {
        $sample = implode("\n", [
            'sku;name;price',
            '"A;1";"Product, with comma";10',
            '"B;2";"Another, product";20',
            '"C;3";"Third, one";30',
        ]);

        $result = $this->detector->detect($sample);

        $this->assertSame(';', $result['delimiter']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_single_column_file_is_ambiguous(): void
    {
        $sample = "kolom\nwaarde een\nwaarde twee\n";

        $result = $this->detector->detect($sample);

        $this->assertNull($result['delimiter']);
        $this->assertSame('ambiguous', $result['confidence']);
    }

    public function test_two_equally_plausible_delimiters_are_ambiguous(): void
    {
        // Every line: exactly one ';' and one '|' → both give 2 consistent columns.
        $sample = "a;b|c\nd;e|f\ng;h|i\n";

        $result = $this->detector->detect($sample);

        $this->assertSame('ambiguous', $result['confidence']);
    }

    public function test_empty_input_is_ambiguous(): void
    {
        $result = $this->detector->detect('');

        $this->assertNull($result['delimiter']);
        $this->assertSame('ambiguous', $result['confidence']);
    }
}
