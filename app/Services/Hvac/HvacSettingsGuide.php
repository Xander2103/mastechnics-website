<?php

namespace App\Services\Hvac;

/**
 * Plain-language presentation of the rule catalog for the admin screen
 * "Offerte-instellingen": which of the five sections a rule belongs to, what
 * it means for Martin, a worked example with the CURRENT value, what goes
 * wrong when it is off, and whether (and within which bounds) the value may
 * be edited in a draft.
 *
 * Presentation only. Rule keys, criticality and the approval gate stay
 * defined by HvacRuleCatalog / HvacRecommendationReadiness.
 */
class HvacSettingsGuide
{
    public const SECTIONS = [
        'koelvermogen' => [
            'number'  => 1,
            'title'   => 'Koelvermogen',
            'summary' => 'Hoe het benodigde koelvermogen van een ruimte wordt ingeschat.',
            'intro'   => 'Er zijn twee methodes. De snelle inschatting rekent met één vuistregel per m³ en is alleen indicatief. De gedetailleerde berekening gebruikt de gegevens uit de aanvraag (afmetingen, isolatie, ligging, ramen) en is de basis voor elke voorcalculatie.',
        ],
        'toestellen' => [
            'number'  => 2,
            'title'   => 'Toestellen',
            'summary' => 'Hoe een berekend koelvermogen gekoppeld wordt aan toestellen uit uw catalogus.',
            'intro'   => 'Het berekende vermogen wordt eerst vertaald naar een capaciteitsklasse. Daarna zoekt het systeem in uw eigen productcatalogus naar toestellen in die klasse. Het kiest nooit een toestel dat niet in uw catalogus staat en koppelt binnen- en buitenunits alleen wanneer de compatibiliteit is ingevoerd.',
        ],
        'installatie' => [
            'number'  => 3,
            'title'   => 'Installatie',
            'summary' => 'Leidingen, elektrische aansluiting en toebehoren.',
            'intro'   => 'Het aanvraagformulier vraagt geen leidingtraject of plaats van de buitenunit. Het systeem rekent daarom met een standaardsituatie en meldt dat telkens als aanname. U bevestigt de werkelijke situatie op basis van de foto\'s of een plaatsbezoek.',
        ],
        'werkuren' => [
            'number'  => 4,
            'title'   => 'Werkuren',
            'summary' => 'Hoeveel uren het systeem raamt voor een installatie.',
            'intro'   => 'De geraamde uren worden opgeteld en daarna vermenigvuldigd met uw uurtarief (zie Verkoopprijzen). Elke urenregel staat apart in de voorcalculatie, zodat u ziet waar het totaal vandaan komt.',
        ],
        'verkoopprijzen' => [
            'number'  => 5,
            'title'   => 'Verkoopprijzen',
            'summary' => 'Uurtarief, verplaatsing, opslagen en btw.',
            'intro'   => 'Verkoopprijzen van toestellen en materialen komen uit uw catalogus. Alleen wanneer daar geen verkoopprijs staat, rekent het systeem met een opslag op de aankoopprijs. Zonder enige prijs blijft de regel leeg en kan de optie niet goedgekeurd worden — er wordt nooit een prijs verzonnen.',
        ],
    ];

    /** Rules that live in another section than their catalog category. */
    private const SECTION_OVERRIDES = [
        'labor.hourly_rate_excl_vat' => 'verkoopprijzen',
        'labor.travel_flat_excl_vat' => 'verkoopprijzen',
    ];

    private const SECTION_BY_CATEGORY = [
        'Snelle inschatting' => 'koelvermogen',
        'Koellast'           => 'koelvermogen',
        'Koellast v2'        => 'koelvermogen',
        'Capaciteit'         => 'toestellen',
        'Leidingen'          => 'installatie',
        'Elektrisch'         => 'installatie',
        'Toebehoren'         => 'installatie',
        'Arbeid'             => 'werkuren',
        'Prijs & btw'        => 'verkoopprijzen',
    ];

    private const GROUP_BY_CATEGORY = [
        'Snelle inschatting' => 'Snelle inschatting (vuistregels per m³)',
        'Koellast'           => 'Gedetailleerde berekening',
        'Koellast v2'        => 'Gedetailleerde berekening (uitgebreid model)',
        'Capaciteit'         => 'Capaciteitsklassen en multi-split',
        'Leidingen'          => 'Leidingen',
        'Elektrisch'         => 'Elektrische aansluiting',
        'Toebehoren'         => 'Toebehoren',
        'Arbeid'             => 'Geraamde uren',
        'Prijs & btw'        => 'Opslagen en btw',
    ];

    /** Numeric rules that still must not be edited from the screen. */
    private const NOT_EDITABLE = [
        'max_class_kw' => 'Deze waarde hangt samen met de klassentabel en de elektrische tabel en wordt samen met de ontwikkelaar aangepast.',
    ];

    private const INSULATION_LEVELS = [
        'excellent' => 'uitstekende',
        'good'      => 'goede',
        'average'   => 'gemiddelde',
        'poor'      => 'beperkte',
    ];

    private const WINDOW_TYPES = [
        'large'    => 'grote ramen',
        'mixed'    => 'gemengde ramen',
        'small'    => 'kleine ramen',
        'few_none' => 'weinig of geen ramen',
    ];

    public static function sectionFor(array $entry): string
    {
        return self::SECTION_OVERRIDES[$entry['key']]
            ?? self::SECTION_BY_CATEGORY[$entry['category']]
            ?? 'koelvermogen';
    }

    public static function groupFor(array $entry): string
    {
        if (isset(self::SECTION_OVERRIDES[$entry['key']])) {
            return 'Tarieven';
        }

        return self::GROUP_BY_CATEGORY[$entry['category']] ?? $entry['category'];
    }

    /**
     * The four statuses Martin sees. "Nog instellen" = a starting value nobody
     * chose yet; "Controleren" = a value that needs his confirmation.
     *
     * @return array{key: string, label: string}
     */
    public static function friendlyStatus(string $catalogStatus, bool $valueChanged = false): array
    {
        if ($catalogStatus === 'validated') {
            return ['key' => 'approved', 'label' => 'Goedgekeurd'];
        }
        if ($valueChanged) {
            return ['key' => 'review', 'label' => 'Controleren'];
        }

        return $catalogStatus === 'placeholder'
            ? ['key' => 'todo', 'label' => 'Nog instellen']
            : ['key' => 'review', 'label' => 'Controleren'];
    }

    /** Why a rule carries its status, in one sentence. */
    public static function statusReason(string $catalogStatus, bool $valueChanged = false): string
    {
        if ($catalogStatus === 'validated') {
            return 'U hebt deze waarde bevestigd.';
        }
        if ($valueChanged) {
            return 'De waarde is gewijzigd sinds de laatste bevestiging en moet opnieuw bevestigd worden.';
        }

        return match ($catalogStatus) {
            'placeholder'        => 'Dit is een startwaarde van de software, niet van Mastechnics. Vul uw eigen waarde in of bevestig dat deze klopt.',
            'fabrikantspecifiek' => 'Algemene schatting. Gegevens van de fabrikant op het product gaan altijd voor.',
            default              => 'Deze waarde is nog niet door u bevestigd.',
        };
    }

    /**
     * @return array{
     *   name: string, meaning: string, used_for: string, example: ?string,
     *   consequence: string, confirm_label: string
     * }
     */
    public static function guide(array $entry, mixed $value, array $config): array
    {
        $specific = self::specificGuide($entry['key'], $value, $config);

        $guide = array_merge([
            'name'        => $entry['label'],
            'meaning'     => $entry['explanation'],
            'used_for'    => self::genericUse($entry['category']),
            'example'     => null,
            'consequence' => self::genericConsequence($entry['category']),
        ], $specific);

        $guide['confirm_label'] = $specific['confirm_label']
            ?? 'Ik bevestig dat deze waarde klopt voor Mastechnics.';

        return $guide;
    }

    /**
     * Edit bounds for a rule, or null with a reason when the value cannot be
     * changed from the screen (tables, text choices, linked values).
     *
     * @return array{editable: bool, reason?: string, min?: float, max?: float, step?: string, choices?: array<int, float>, integer?: bool}
     */
    public static function editSpec(array $entry, mixed $value): array
    {
        if (isset(self::NOT_EDITABLE[$entry['key']])) {
            return ['editable' => false, 'reason' => self::NOT_EDITABLE[$entry['key']]];
        }
        if (is_array($value)) {
            return ['editable' => false, 'reason' => 'Deze instelling is een tabel met meerdere waarden en wordt samen met de ontwikkelaar aangepast.'];
        }
        if (! is_int($value) && ! is_float($value)) {
            return ['editable' => false, 'reason' => 'Deze instelling is een keuze uit vaste opties en wordt samen met de ontwikkelaar aangepast.'];
        }

        if (in_array($entry['key'], ['pricing.default_vat_rate', 'pricing.reduced_vat_rate'], true)) {
            // Same set the quote editor accepts.
            return ['editable' => true, 'choices' => [0.0, 6.0, 12.0, 21.0]];
        }

        [$min, $max, $step, $integer] = match ($entry['unit']) {
            '€/uur excl. btw'            => [10, 500, '0.01', false],
            '€ excl. btw'                => [0, 1000, '0.01', false],
            '%'                          => [0, 300, '0.1', false],
            'uur'                        => [0, 100, '0.25', false],
            'uur/m'                      => [0, 10, '0.05', false],
            'W/m²'                       => [10, 500, '1', false],
            'W/m³'                       => [5, 200, '1', false],
            'W'                          => [0, 5000, '1', false],
            'W/m² raam'                  => [0, 1500, '1', false],
            'W/m²K'                      => [0.05, 10, '0.01', false],
            'W per m³·ACH'               => [0, 20, '0.01', false],
            'K'                          => [1, 30, '0.5', false],
            '1/h'                        => [0, 10, '0.1', false],
            'm', 'm equivalent'          => [0, 200, '0.1', false],
            'kW'                         => [1, 50, '0.1', false],
            'stuks', 'sets', 'binnenunits' => [0, 20, '1', true],
            '× vloeroppervlak'           => [0, 1, '0.01', false],
            default                      => [0, 10, '0.01', false],
        };

        return ['editable' => true, 'min' => (float) $min, 'max' => (float) $max, 'step' => $step, 'integer' => $integer];
    }

    /**
     * Readable value: one line for a single value, several for a table.
     *
     * @return string[]
     */
    public static function valueLines(array $entry, mixed $value): array
    {
        if ($value === null) {
            return ['—'];
        }

        if (! is_array($value)) {
            return [self::scalarWithUnit($value, $entry['unit'])];
        }

        return match ($entry['key']) {
            'capacity_classes' => array_map(
                fn (array $band) => 'Koellast tot ' . self::nl($band['max_load_kw']) . ' kW → klasse ' . self::nl($band['class_kw']) . ' kW',
                $value
            ),
            'diversity_factors' => array_map(
                fn ($units, $factor) => ($units === '5_plus' ? '5 of meer' : $units) . ' binnenunits: × ' . self::nl($factor),
                array_keys($value),
                $value
            ),
            'electrical_by_class' => array_map(
                fn ($class, array $row) => 'Klasse ' . self::nl((float) $class) . ' kW: zekering ' . $row['breaker_a'] . ' A · kabel ' . $row['cable'],
                array_keys($value),
                $value
            ),
            'occupancy.default_occupants_by_room_type' => array_map(
                fn ($room, $persons) => ucfirst((string) $room) . ': ' . $persons . ' personen',
                array_keys($value),
                $value
            ),
            'roof_type_factors' => count(array_unique(array_map('floatval', $value))) === 1
                ? ['Alle daktypes: × ' . self::nl((float) reset($value)) . ' (neutraal — geen correctie)']
                : [HvacRuleCatalog::formatValue($value)],
            default => [HvacRuleCatalog::formatValue($value)],
        };
    }

    // ── Specific guide texts ────────────────────────────────────────────────

    private static function specificGuide(string $key, mixed $value, array $config): array
    {
        $v = is_numeric($value) ? (float) $value : null;

        // Quick estimate — four rules of thumb per m³.
        if (str_starts_with($key, 'quick_estimate.w_per_m3.')) {
            $situation = QuickCoolingEstimator::SITUATIONS[substr($key, strlen('quick_estimate.w_per_m3.'))] ?? 'deze situatie';

            return [
                'name'          => 'Vuistregel: ' . mb_strtolower($situation),
                'meaning'       => 'Het indicatieve koelvermogen per m³ ruimte voor de situatie "' . $situation . '". Deze waarde hebt u zelf aangeleverd als vuistregel; het is geen technische ontwerpnorm.',
                'used_for'      => 'Alleen voor de snelle inschatting. De gedetailleerde berekening, de toestelkeuze en de offerte gebruiken deze waarde niet.',
                'example'       => $v !== null
                    ? 'Ruimte van 5 × 4 × 2,5 m = 50 m³. 50 m³ × ' . self::nl($v) . ' W/m³ = ' . self::nl(50 * $v, 0) . ' W = ' . self::nl(50 * $v / 1000) . ' kW (indicatief).'
                    : null,
                'consequence'   => 'Een te lage waarde geeft u een te optimistische eerste indruk, een te hoge een te groot vermogen. Omdat de snelle inschatting nooit een toestel kiest, heeft een fout hier geen gevolg voor bestaande offertes.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::nl($v) . ' W/m³ mijn vuistregel is voor deze situatie.' : null,
            ];
        }

        // Detailed v1 — base load per m² by insulation level.
        if (str_starts_with($key, 'insulation_w_per_m2.')) {
            $level = self::INSULATION_LEVELS[substr($key, strlen('insulation_w_per_m2.'))] ?? 'deze';

            return [
                'name'          => "Basisvermogen bij {$level} isolatie",
                'meaning'       => "Het koelvermogen dat de gedetailleerde berekening per m² vloeroppervlak rekent voor een woning met {$level} isolatie, vóór de correcties voor plafondhoogte, ligging en ramen.",
                'used_for'      => 'Dit is het startpunt van de koellast per kamer. Het resultaat bepaalt in welke capaciteitsklasse naar toestellen gezocht wordt.',
                'example'       => $v !== null ? 'Kamer van 20 m² × ' . self::nl($v) . ' W/m² = ' . self::nl(20 * $v, 0) . ' W basisvermogen. Daarna volgen de correcties voor hoogte, ligging en ramen.' : null,
                'consequence'   => 'Te laag: het voorgestelde toestel is te klein en koelt onvoldoende op warme dagen. Te hoog: u stelt een groter en duurder toestel voor dan nodig.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::nl($v) . " W/m² klopt bij {$level} isolatie." : null,
            ];
        }

        // Detailed v2 — equivalent U-value by insulation level.
        if (str_starts_with($key, 'u_equivalent_by_insulation.')) {
            $level = self::INSULATION_LEVELS[substr($key, strlen('u_equivalent_by_insulation.'))] ?? 'deze';
            $deltaT = (float) ($config['design_delta_t_k'] ?? 8.0);

            return [
                'name'          => "Warmtedoorgang bij {$level} isolatie",
                'meaning'       => "Hoeveel warmte er per m² muur en plafond binnenkomt per graad temperatuurverschil, voor een woning met {$level} isolatie (equivalente U-waarde).",
                'used_for'      => 'Het uitgebreide rekenmodel berekent hiermee de warmte die door muren en plafond binnenkomt.',
                'example'       => $v !== null
                    ? 'Kamer 5 × 4 × 2,5 m: muren en plafond samen 65 m². 65 m² × ' . self::nl($v) . ' × ' . self::nl($deltaT) . ' K = ' . self::nl(65 * $v * $deltaT, 0) . ' W door de schil.'
                    : null,
                'consequence'   => 'Te laag: de koellast wordt onderschat en het toestel is te klein. Te hoog: een te groot toestel.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::nl($v) . " W/m²K klopt bij {$level} isolatie." : null,
            ];
        }

        if (str_starts_with($key, 'window_area_ratio_by_window_type.')) {
            $type = self::WINDOW_TYPES[substr($key, strlen('window_area_ratio_by_window_type.'))] ?? 'dit raamtype';

            return [
                'name'          => "Geschatte glasoppervlakte bij {$type}",
                'meaning'       => "Het formulier vraagt alleen het raamtype, geen m² glas. Het uitgebreide model schat de glasoppervlakte daarom als een deel van de vloeroppervlakte. Dit aandeel geldt bij {$type}. Het is een eigen aanname van de software, niet afkomstig uit het referentiewerkboek.",
                'used_for'      => 'Berekening van de zonnewarmte door het glas.',
                'example'       => $v !== null ? 'Kamer van 20 m² × ' . self::nl($v) . ' = ' . self::nl(20 * $v) . ' m² glas waarmee gerekend wordt.' : null,
                'consequence'   => 'Bij grote glaspartijen kan de werkelijke glasoppervlakte veel groter zijn. Een te laag aandeel onderschat dan de zonnewarmte en dus het toestel.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat een aandeel van ' . self::nl($v * 100) . "% glas realistisch is bij {$type}." : null,
            ];
        }

        $hours = (float) ($config['labor']['base_installation_hours'] ?? 6.0);

        return match ($key) {
            'design_delta_t_k' => [
                'name'          => 'Temperatuurverschil binnen–buiten',
                'meaning'       => 'Het vaste temperatuurverschil tussen buiten en binnen waarmee het uitgebreide model op een warme zomerdag rekent.',
                'used_for'      => 'Warmte door muren en plafond = oppervlakte × warmtedoorgang × dit temperatuurverschil.',
                'example'       => $v !== null ? '65 m² schil × 0,60 W/m²K × ' . self::nl($v) . ' K = ' . self::nl(65 * 0.6 * $v, 0) . ' W.' : null,
                'consequence'   => 'Een te klein verschil onderschat elke koellast, een te groot verschil overschat ze allemaal.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::nl($v) . ' K het juiste ontwerpverschil is.' : null,
            ],
            'assumed_shading' => [
                'name'        => 'Aangenomen zonwering',
                'meaning'     => 'Het formulier vraagt niet of er zonwering is. Het uitgebreide model rekent daarom voor elke kamer met deze aanname. "none" (geen zonwering) is de voorzichtige keuze: ze geeft de hoogste koellast.',
                'used_for'    => 'Zonnewarmte door het glas wordt vermenigvuldigd met de factor van deze zonwering.',
                'example'     => 'Bij "none" telt de volledige zonnewarmte mee. Bij buitenzonwering zou dat maar ongeveer een derde zijn.',
                'consequence' => 'Rekenen met zonwering die er niet is, geeft een te klein toestel.',
                'confirm_label' => 'Ik bevestig dat standaard zonder zonwering gerekend mag worden.',
            ],
            'ventilation_ach_default' => [
                'name'          => 'Luchtverversing per uur',
                'meaning'       => 'Hoe vaak per uur de lucht in de ruimte aangenomen wordt te verversen (ventilatie en kieren). Het formulier vraagt dit niet; het is altijd een aanname.',
                'used_for'      => 'Warmte en vocht die met buitenlucht binnenkomen in het uitgebreide model.',
                'example'       => $v !== null ? 'Kamer van 50 m³ × ' . self::nl($v) . ' per uur × 2,67 = ' . self::nl(50 * $v * 2.67, 0) . ' W voelbare ventilatiewarmte.' : null,
                'consequence'   => 'Bij sterk geventileerde ruimtes (keuken, praktijkruimte) is de werkelijke waarde hoger en wordt de koellast onderschat.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::nl($v) . ' luchtwisselingen per uur een goede standaard is.' : null,
            ],
            'safety_factor' => [
                'name'          => 'Veiligheidsmarge op de koellast',
                'meaning'       => 'Het uitgebreide model vermenigvuldigt de berekende koellast met deze factor als reserve.',
                'used_for'      => 'Laatste stap van de uitgebreide koellastberekening.',
                'example'       => $v !== null ? 'Berekende last 2.338 W × ' . self::nl($v) . ' = ' . self::nl(2338 * $v, 0) . ' W ontwerplast.' : null,
                'consequence'   => 'Zonder marge kan een toestel op de warmste dagen tekortschieten; een te grote marge duwt aanvragen naar een hogere, duurdere klasse.',
                'confirm_label' => $v !== null ? 'Ik bevestig een veiligheidsmarge van × ' . self::nl($v) . '.' : null,
            ],
            'capacity_classes' => [
                'name'        => 'Capaciteitsklassen',
                'meaning'     => 'De tabel die een berekende koellast vertaalt naar een standaardvermogen (klasse). De klasse is een zoekdoel in uw catalogus — nog geen toestelkeuze.',
                'used_for'    => 'Na de koellastberekening zoekt het systeem toestellen waarvan het vermogen de koellast dekt en niet te ver boven de klasse ligt.',
                'example'     => is_array($value) && isset($value[0])
                    ? 'Een berekende koellast van ' . self::nl(max(0.1, (float) $value[0]['max_load_kw'] - 0.3)) . ' kW valt onder de grens van ' . self::nl($value[0]['max_load_kw']) . ' kW en krijgt dus klasse ' . self::nl($value[0]['class_kw']) . ' kW.'
                    : null,
                'consequence' => 'Verkeerde grenzen sturen de zoekopdracht naar te kleine of te grote toestellen, ook als de koellast zelf juist berekend is.',
                'confirm_label' => 'Ik bevestig dat deze klassen en grenzen overeenkomen met hoe Mastechnics toestellen kiest.',
            ],
            'diversity_factors' => [
                'name'        => 'Gelijktijdigheid bij multi-split',
                'meaning'     => 'Bij meerdere binnenunits op één buitenunit draaien zelden alle units tegelijk op vol vermogen. Deze factoren verlagen daarom het geschatte vermogen van de buitenunit.',
                'used_for'    => 'Geschat vermogen buitenunit = som van de klassen van de binnenunits × factor. De compatibiliteitsgegevens van de fabrikant gaan altijd voor.',
                'example'     => is_array($value) && isset($value['3'])
                    ? 'Drie binnenunits van 2,5 + 2,5 + 3,5 kW = 8,5 kW × ' . self::nl($value['3']) . ' = ' . self::nl(8.5 * (float) $value['3']) . ' kW voor de buitenunit.'
                    : null,
                'consequence' => 'Een te lage factor stelt een te kleine buitenunit voor; een te hoge een onnodig dure.',
                'confirm_label' => 'Ik bevestig dat deze gelijktijdigheidsfactoren kloppen.',
            ],
            'electrical_by_class' => [
                'name'        => 'Zekering en kabel per klasse',
                'meaning'     => 'Een algemene schatting van de zekering en de kabelsectie per capaciteitsklasse. Staan er gegevens van de fabrikant op het product, dan gaan die altijd voor.',
                'used_for'    => 'Wordt als indicatie getoond in de voorcalculatie, met de melding dat elektrische controle verplicht blijft.',
                'example'     => is_array($value) && isset($value['3.5'])
                    ? 'Een toestel van klasse 3,5 kW krijgt als indicatie: zekering ' . $value['3.5']['breaker_a'] . ' A en kabel ' . $value['3.5']['cable'] . '.'
                    : null,
                'consequence' => 'Een foute schatting kan ertoe leiden dat de elektrische aansluiting in de offerte onderschat wordt. Controle ter plaatse door de installateur of elektricien blijft altijd nodig.',
                'confirm_label' => 'Ik bevestig dat deze tabel een correcte algemene indicatie is.',
            ],
            'labor.hourly_rate_excl_vat' => [
                'name'          => 'Uurtarief installatie',
                'meaning'       => 'Het bedrag per werkuur, exclusief btw.',
                'used_for'      => 'Dit bedrag wordt gebruikt om de geraamde werkuren om te zetten naar arbeidskosten in een offerte.',
                'example'       => $v !== null ? self::nl($hours) . ' uur × ' . self::euro($v) . ' = ' . self::euro($hours * $v) . ' exclusief btw.' : null,
                'consequence'   => 'Elke voorbereide offerte rekent met dit tarief. Een te laag tarief kost u marge op elke installatie; een te hoog tarief maakt uw offertes onnodig duur.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::euro($v) . ' per uur (excl. btw) het juiste tarief is.' : null,
            ],
            'labor.travel_flat_excl_vat' => [
                'name'          => 'Verplaatsingskost',
                'meaning'       => 'Een vast bedrag voor verplaatsing, exclusief btw. Het systeem rekent niet per kilometer.',
                'used_for'      => 'Komt als aparte regel "Verplaatsing" op elke automatisch voorbereide offerte.',
                'example'       => $v !== null ? 'Elke installatie: 1 × ' . self::euro($v) . ' = ' . self::euro($v) . ' exclusief btw, ongeacht de afstand.' : null,
                'consequence'   => 'Omdat het bedrag vast is, moet u het bij een verre werf zelf aanpassen in de voorcalculatie of de offerte.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::euro($v) . ' (excl. btw) de juiste vaste verplaatsingskost is.' : null,
            ],
            'pricing.fallback_margin_pct_on_purchase' => [
                'name'          => 'Opslag op toestellen zonder verkoopprijs',
                'meaning'       => 'Heeft een toestel in de catalogus alleen een aankoopprijs, dan berekent het systeem de verkoopprijs als aankoopprijs + dit percentage. Dit is een opslag op de aankoopprijs — géén brutomarge.',
                'used_for'      => 'Alleen voor toestellen zonder eigen verkoopprijs. In de voorcalculatie verschijnt dan een waarschuwing om de prijs te controleren.',
                'example'       => $v !== null
                    ? 'Aankoopprijs € 1.000,00 (fictief) + ' . self::nl($v) . '% = verkoopprijs ' . self::euro(1000 * (1 + $v / 100)) . '. De brutomarge op die verkoopprijs is dan ' . self::nl($v / (100 + $v) * 100, 1) . '% — niet ' . self::nl($v) . '%.'
                    : null,
                'consequence'   => 'Wie dit percentage als marge leest, rekent zich rijk: een opslag van bijvoorbeeld 35% levert maar 25,9% marge op. Een te lage opslag verkoopt toestellen onder uw gebruikelijke prijs.',
                'confirm_label' => $v !== null ? 'Ik bevestig een opslag van ' . self::nl($v) . '% op de aankoopprijs.' : null,
            ],
            'pricing.material_markup_pct' => [
                'name'          => 'Opslag op materialen zonder verkoopprijs',
                'meaning'       => 'Heeft een materiaal (leiding, goot, beugel …) in de catalogus alleen een aankoopprijs, dan wordt de verkoopprijs aankoopprijs + dit percentage. Ook dit is een opslag, geen marge.',
                'used_for'      => 'Alleen voor materialen zonder eigen verkoopprijs. Staat er helemaal geen prijs in de catalogus, dan blijft de regel zonder prijs.',
                'example'       => $v !== null
                    ? 'Koelleiding aankoop € 8,00 per meter (fictief) + ' . self::nl($v) . '% = ' . self::euro(8 * (1 + $v / 100)) . ' per meter. Brutomarge op de verkoopprijs: ' . self::nl($v / (100 + $v) * 100, 1) . '%.'
                    : null,
                'consequence'   => 'Een te lage opslag verkoopt materiaal bijna tegen kostprijs; een te hoge maakt de materiaalpost opvallend duur.',
                'confirm_label' => $v !== null ? 'Ik bevestig een opslag van ' . self::nl($v) . '% op materialen.' : null,
            ],
            'pricing.default_vat_rate' => [
                'name'          => 'Standaard btw-tarief',
                'meaning'       => 'Het btw-tarief waarmee elke voorcalculatie start.',
                'used_for'      => 'Btw-bedrag en totaal inclusief btw van elke optie. Het verlaagde tarief wordt nooit automatisch toegepast: u kiest het per aanvraag zelf via "Btw aanpassen", met een reden.',
                'example'       => $v !== null ? '€ 2.000,00 excl. btw × ' . self::nl($v) . '% = ' . self::euro(2000 * $v / 100) . ' btw → ' . self::euro(2000 * (1 + $v / 100)) . ' incl. btw.' : null,
                'consequence'   => 'Een fout standaardtarief zet op elke offerte een verkeerd totaal. U blijft zelf verantwoordelijk voor het juiste tarief per klant en woning.',
                'confirm_label' => $v !== null ? 'Ik bevestig dat ' . self::nl($v) . '% het juiste standaardtarief is.' : null,
            ],
            'pricing.reduced_vat_rate' => [
                'name'        => 'Verlaagd btw-tarief (suggestie)',
                'meaning'     => 'Het tarief dat het systeem als mogelijkheid meldt bij een particuliere klant met een woning ouder dan 10 jaar. Het wordt nooit automatisch toegepast.',
                'used_for'    => 'Alleen de waarschuwing "Mogelijk 6% btw van toepassing" in de voorcalculatie.',
                'example'     => $v !== null ? '€ 2.000,00 excl. btw × ' . self::nl($v) . '% = ' . self::euro(2000 * $v / 100) . ' btw — alleen nadat u de voorwaarden zelf gecontroleerd hebt.' : null,
                'consequence' => 'Geen rechtstreeks gevolg voor offertes: u past het tarief altijd handmatig toe.',
            ],
            'roof_type_factors' => [
                'name'        => 'Correctie voor dak of zolder',
                'meaning'     => 'Bewust neutraal (× 1,00): er bestaat nog geen bevestigde correctie voor kamers onder een plat dak of op zolder. Zulke kamers krijgen wel een waarschuwing in de voorcalculatie.',
                'used_for'    => 'Gedetailleerde berekening. Zolang de waarde 1,00 is, verandert ze niets aan het resultaat.',
                'consequence' => 'Kamers onder het dak worden mogelijk onderschat. Pas het vermogen van zo\'n kamer zo nodig handmatig aan ("Vermogen aanpassen").',
            ],
            default => [],
        };
    }

    private static function genericUse(string $category): string
    {
        return match ($category) {
            'Koellast'    => 'Correctie of aanname in de gedetailleerde koellastberekening per kamer.',
            'Koellast v2' => 'Onderdeel van het uitgebreide rekenmodel voor de koellast per kamer.',
            'Capaciteit'  => 'Koppeling tussen de berekende koellast en de toestellen uit de catalogus.',
            'Leidingen'   => 'Schatting van het leidingtraject. Het formulier vraagt dit niet, dus het blijft een aanname tot u de werf kent.',
            'Elektrisch'  => 'Indicatie van de elektrische aansluiting.',
            'Toebehoren'  => 'Bepaalt de hoeveelheid materiaal op de voorcalculatie. De prijs komt altijd uit de catalogus.',
            'Arbeid'      => 'Telt mee in het totaal aantal geraamde werkuren.',
            'Prijs & btw' => 'Prijsberekening van elke optie.',
            default       => 'Onderdeel van de voorcalculatie.',
        };
    }

    private static function genericConsequence(string $category): string
    {
        return match ($category) {
            'Koellast', 'Koellast v2' => 'Een verkeerde waarde verschuift de berekende koellast en kan tot een te klein of te groot toestel leiden.',
            'Capaciteit'  => 'Een verkeerde waarde laat geschikte toestellen wegvallen of stelt te grote toestellen voor.',
            'Leidingen'   => 'Een verkeerde aanname geeft te weinig of te veel leiding, goot en kabel op de offerte.',
            'Toebehoren'  => 'Een verkeerde hoeveelheid geeft te weinig of te veel materiaal op de offerte.',
            'Arbeid'      => 'Te weinig uren kost u marge; te veel uren maakt de offerte te duur.',
            default       => 'Een verkeerde waarde werkt door in elke nieuwe voorcalculatie.',
        };
    }

    // ── Formatting ──────────────────────────────────────────────────────────

    public static function nl(float|int|string $value, ?int $decimals = null): string
    {
        $value = (float) $value;
        if ($decimals !== null) {
            return number_format($value, $decimals, ',', '.');
        }

        $formatted = number_format($value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    public static function euro(float|int $value): string
    {
        return '€ ' . number_format((float) $value, 2, ',', '.');
    }

    private static function scalarWithUnit(mixed $value, string $unit): string
    {
        if (is_bool($value)) {
            return $value ? 'ja' : 'nee';
        }
        if (! is_numeric($value)) {
            return (string) $value;
        }

        return match ($unit) {
            '€/uur excl. btw' => self::euro((float) $value) . ' per uur, exclusief btw',
            '€ excl. btw'     => self::euro((float) $value) . ', exclusief btw',
            '%'               => self::nl($value) . '%',
            '×'               => '× ' . self::nl($value),
            '—'               => self::nl($value),
            default           => self::nl($value) . ' ' . $unit,
        };
    }
}
