<?php

namespace Tests\Support;

/**
 * Sanitized fixture reproducing the structural characteristics of a real
 * wholesaler catalog (CatalogFR.csv): tab-delimited, Windows-1252 encoded,
 * 22 header cells (trailing tab), space-padded ProductID, comma decimals,
 * empty RubricID cells, French/Dutch labels, categories far beyond HVAC.
 * All product data is fictional (TestBrand rule: no real manufacturers).
 */
class CatalogFrFixture
{
    public const HEADERS = [
        'ProductID', 'LabelFR', 'LabelNL', 'ProviderID', 'ProviderName', 'ProducerID',
        'BrutPrice', 'DeliveryCode', 'DeliveryDelay', 'UpgradeDate', 'FamilyID', 'FamilyName',
        'GroupID', 'GroupName', 'RubricID', 'RubricName', 'SequenceID', 'EAN', 'Intrastat',
        'Weight', 'Piece',
    ];

    public const GROUP_CLIMATE = 'Climatiseurs';
    public const GROUP_PUMPS = 'Pompes vide-cave';
    public const GROUP_SHOWER = 'Barres de douche';

    /** Number of fixture rows in the Climatiseurs group. */
    public const CLIMATE_COUNT = 5;

    public const TOTAL_COUNT = 10;

    /**
     * Row template (cp1252 bytes via "\xE9" escapes):
     * [ProductID, LabelFR, LabelNL, ProviderName, ProducerID, BrutPrice, FamilyName, GroupName]
     * Climatiseurs rows: 2 clearly indoor, 2 clearly outdoor, 1 ambiguous type.
     */
    private const ROWS = [
        ['118970', "TB clim murale int\xE9rieure 2,5 kW", "TB airco binnenunit wandmodel 2,5 kW", 'TESTBRAND CLIMATE', 'TB-IN-25', '403,5', "Pompes \xE0 chaleur et chaudi\xE8res", self::GROUP_CLIMATE],
        ['118971', "TB clim murale int\xE9rieure 3,5 kW", "TB airco binnenunit wandmodel 3,5 kW", 'TESTBRAND CLIMATE', 'TB-IN-35', '512,0', "Pompes \xE0 chaleur et chaudi\xE8res", self::GROUP_CLIMATE],
        ['118972', "TB groupe ext\xE9rieur mono 3,5 kW", "TB airco buitenunit mono 3,5 kW", 'TESTBRAND CLIMATE', 'TB-OUT-35', '780,25', "Pompes \xE0 chaleur et chaudi\xE8res", self::GROUP_CLIMATE],
        ['118973', "TB groupe ext\xE9rieur multi 5 kW", "TB airco buitenunit multi 5 kW", 'TESTBRAND CLIMATE', 'TB-OUT-50', '1250,00', "Pompes \xE0 chaleur et chaudi\xE8res", self::GROUP_CLIMATE],
        ['118974', "TB t\xE9l\xE9commande de confort", "TB comfortafstandsbediening", 'TESTBRAND CLIMATE', 'TB-RC-01', '45,9', "Pompes \xE0 chaleur et chaudi\xE8res", self::GROUP_CLIMATE],
        ['218970', 'Robusta pompe vide-cave 200TS', 'Robusta kelderpomp 200TS', 'TESTPUMP CONCEPT', 'TP-200TS', '403,5', 'Pompes', self::GROUP_PUMPS],
        ['218971', 'Robusta pompe vide-cave 300TS', 'Robusta kelderpomp 300TS', 'TESTPUMP CONCEPT', 'TP-300TS', '510,0', 'Pompes', self::GROUP_PUMPS],
        ['318970', 'Barre murale +porte douchette inox', 'Glijstang m/ergohendel rvs', "TESTSAN \xC9QUIPEMENT", 'TS-BAR-01', '318,72', "\xC9quipement de douches", self::GROUP_SHOWER],
        ['318971', "Barre de douche 90cm - \xE9ponges", 'Doucherail 90cm - sponzen', "TESTSAN \xC9QUIPEMENT", 'TS-BAR-02', '89,5', "\xC9quipement de douches", self::GROUP_SHOWER],
        ['318972', 'Barre de douche 120cm', 'Doucherail 120cm', "TESTSAN \xC9QUIPEMENT", 'TS-BAR-03', '99,0', "\xC9quipement de douches", self::GROUP_SHOWER],
    ];

    /** Windows-1252 encoded, tab-delimited file contents. */
    public static function contents(): string
    {
        $lines = [implode("\t", self::HEADERS) . "\t"];

        foreach (self::ROWS as $i => $row) {
            [$productId, $labelFr, $labelNl, $provider, $producerId, $price, $family, $group] = $row;
            $lines[] = implode("\t", [
                str_pad($productId, 20),                 // space-padded, as in the real file
                $labelFr,
                $labelNl,
                '000' . ($i % 3),
                $provider,
                $producerId,
                $price,
                '1A',
                '10',
                '26-03-2026',
                '7200',
                $family,
                'G00122' . $i,
                $group,
                '',                                       // RubricID often empty
                ' ' . strtoupper($group),
                '000201000000' . $i,
                $i % 2 === 0 ? '540023717000' . $i : '',  // EAN sometimes empty
                '',
                '0',
                '0',
            ]) . "\t";
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /** Writes the fixture to a temp file and returns the path. */
    public static function toTempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalogfr-') . '.csv';
        file_put_contents($path, self::contents());

        return $path;
    }
}
