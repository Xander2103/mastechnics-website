<?php

namespace App\Services\Hvac;

use Illuminate\Support\Arr;

/**
 * Human-readable catalog of every tunable rule in a rule-set configuration:
 * label, category, unit, explanation, default status and whether the rule is
 * CRITICAL (production approval of non-demo recommendations is blocked until
 * every critical rule is validated by the admin).
 *
 * Statuses: placeholder (invented starting value), te_valideren (business
 * rule to confirm), fabrikantspecifiek (real value comes from product data),
 * and — via hvac_rule_validations — gevalideerd.
 */
class HvacRuleCatalog
{
    public static function entries(): array
    {
        return [
            // ── Koellast ─────────────────────────────────────────────────────
            ['key' => 'insulation_w_per_m2.excellent', 'category' => 'Koellast', 'label' => 'Basislast isolatie: uitstekend', 'unit' => 'W/m²', 'explanation' => 'Vermogen per m² bij uitstekende isolatie (nieuwbouw/passief).', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'insulation_w_per_m2.good', 'category' => 'Koellast', 'label' => 'Basislast isolatie: goed', 'unit' => 'W/m²', 'explanation' => 'Vermogen per m² bij goede isolatie (vanaf ± 2015).', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'insulation_w_per_m2.average', 'category' => 'Koellast', 'label' => 'Basislast isolatie: gemiddeld', 'unit' => 'W/m²', 'explanation' => 'Vermogen per m² bij gemiddelde isolatie (± 2000–2015); ook de terugvalwaarde bij "onbekend".', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'insulation_w_per_m2.poor', 'category' => 'Koellast', 'label' => 'Basislast isolatie: beperkt', 'unit' => 'W/m²', 'explanation' => 'Vermogen per m² bij beperkte isolatie (oudere woning).', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'ceiling_reference_height_m', 'category' => 'Koellast', 'label' => 'Referentiehoogte plafond', 'unit' => 'm', 'explanation' => 'Hoogtefactor = kamerhoogte gedeeld door deze waarde.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'orientation_factors.north', 'category' => 'Koellast', 'label' => 'Liggingsfactor noord', 'unit' => '×', 'explanation' => 'Correctiefactor voor noordgerichte ruimtes.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'orientation_factors.east', 'category' => 'Koellast', 'label' => 'Liggingsfactor oost', 'unit' => '×', 'explanation' => 'Correctiefactor voor oostgerichte ruimtes.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'orientation_factors.west', 'category' => 'Koellast', 'label' => 'Liggingsfactor west', 'unit' => '×', 'explanation' => 'Correctiefactor voor westgerichte ruimtes.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'orientation_factors.south', 'category' => 'Koellast', 'label' => 'Liggingsfactor zuid', 'unit' => '×', 'explanation' => 'Correctiefactor voor zuidgerichte ruimtes.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'window_type_factors.large', 'category' => 'Koellast', 'label' => 'Raamfactor grote ramen', 'unit' => '×', 'explanation' => 'Correctiefactor bij grote ramen.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'window_type_factors.mixed', 'category' => 'Koellast', 'label' => 'Raamfactor gemengde ramen', 'unit' => '×', 'explanation' => 'Correctiefactor bij gemengde ramen.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'window_type_factors.small', 'category' => 'Koellast', 'label' => 'Raamfactor kleine ramen', 'unit' => '×', 'explanation' => 'Correctiefactor bij kleine ramen.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'window_type_factors.few_none', 'category' => 'Koellast', 'label' => 'Raamfactor weinig/geen ramen', 'unit' => '×', 'explanation' => 'Correctiefactor bij weinig of geen ramen.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'roof_type_factors', 'category' => 'Koellast', 'label' => 'Dak-/zoldercorrectie', 'unit' => '×', 'explanation' => 'Bewust neutraal (1,00) — er is nog geen gevalideerde dakcorrectie. Zolder-/platdakkamers krijgen een waarschuwing.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'occupancy.watts_per_extra_person', 'category' => 'Koellast', 'label' => 'Warmte per extra persoon', 'unit' => 'W', 'explanation' => 'Eerste persoon inbegrepen; elke extra persoon telt dit vermogen bij.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'occupancy.default_occupants_by_room_type', 'category' => 'Koellast', 'label' => 'Aangenomen bezetting per kamertype', 'unit' => 'personen', 'explanation' => 'Het formulier vraagt geen bezetting; deze aannames worden gebruikt (altijd met waarschuwing).', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'equipment_heat_w.tv', 'category' => 'Koellast', 'label' => 'Toestelwarmte tv', 'unit' => 'W', 'explanation' => 'Bijkomende warmte voor een televisie.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'equipment_heat_w.pc', 'category' => 'Koellast', 'label' => 'Toestelwarmte pc', 'unit' => 'W', 'explanation' => 'Bijkomende warmte voor een computerwerkplek.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'equipment_heat_w.open_kitchen', 'category' => 'Koellast', 'label' => 'Toestelwarmte open keuken', 'unit' => 'W', 'explanation' => 'Bijkomende warmte voor een open keuken.', 'default_status' => 'placeholder', 'critical' => false],

            // ── Koellast v2 (engineering-model, alleen in regelset v2) ───────
            ['key' => 'u_equivalent_by_insulation.excellent', 'category' => 'Koellast v2', 'label' => 'Ueq isolatie: uitstekend', 'unit' => 'W/m²K', 'explanation' => 'Equivalente U-waarde van de schil bij uitstekende isolatie (werkboek "New").', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'u_equivalent_by_insulation.good', 'category' => 'Koellast v2', 'label' => 'Ueq isolatie: goed', 'unit' => 'W/m²K', 'explanation' => 'Equivalente U-waarde bij goede isolatie (werkboek "Recent insulated").', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'u_equivalent_by_insulation.average', 'category' => 'Koellast v2', 'label' => 'Ueq isolatie: gemiddeld', 'unit' => 'W/m²K', 'explanation' => 'Equivalente U-waarde bij gemiddelde isolatie (werkboek "Older insulated"); ook de terugvalwaarde bij "onbekend".', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'u_equivalent_by_insulation.poor', 'category' => 'Koellast v2', 'label' => 'Ueq isolatie: beperkt', 'unit' => 'W/m²K', 'explanation' => 'Equivalente U-waarde bij beperkte isolatie (werkboek "Old uninsulated").', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'design_delta_t_k', 'category' => 'Koellast v2', 'label' => 'Ontwerp-temperatuurverschil', 'unit' => 'K', 'explanation' => 'Vast zomerontwerp-ΔT voor transmissie (werkboek: 8 K hardcoded).', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'solar_gain_w_per_m2_by_orientation.north', 'category' => 'Koellast v2', 'label' => 'Zoninstraling noord', 'unit' => 'W/m² raam', 'explanation' => 'Zonnewinst per m² raam op het noorden.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'solar_gain_w_per_m2_by_orientation.east', 'category' => 'Koellast v2', 'label' => 'Zoninstraling oost', 'unit' => 'W/m² raam', 'explanation' => 'Zonnewinst per m² raam op het oosten.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'solar_gain_w_per_m2_by_orientation.south', 'category' => 'Koellast v2', 'label' => 'Zoninstraling zuid', 'unit' => 'W/m² raam', 'explanation' => 'Zonnewinst per m² raam op het zuiden.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'solar_gain_w_per_m2_by_orientation.west', 'category' => 'Koellast v2', 'label' => 'Zoninstraling west', 'unit' => 'W/m² raam', 'explanation' => 'Zonnewinst per m² raam op het westen; ook de terugvalwaarde bij "onbekend" (werkboekgedrag).', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'shading_factors.none', 'category' => 'Koellast v2', 'label' => 'Zonweringsfactor geen', 'unit' => '×', 'explanation' => 'Zonder zonwering telt de volledige zonnewinst.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'shading_factors.internal_blinds', 'category' => 'Koellast v2', 'label' => 'Zonweringsfactor binnenzonwering', 'unit' => '×', 'explanation' => 'Reductie bij binnenzonwering.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'shading_factors.external_screen', 'category' => 'Koellast v2', 'label' => 'Zonweringsfactor buitenzonwering/rolluik', 'unit' => '×', 'explanation' => 'Reductie bij buitenzonwering of rolluik.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'assumed_shading', 'category' => 'Koellast v2', 'label' => 'Aangenomen zonwering', 'unit' => '—', 'explanation' => 'Het formulier vraagt geen zonwering; deze aanname geldt voor elke kamer (altijd met waarschuwing). "none" is de conservatieve keuze.', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'window_area_ratio_by_window_type.large', 'category' => 'Koellast v2', 'label' => 'Raamratio grote ramen', 'unit' => '× vloeroppervlak', 'explanation' => 'Afgeleide raamoppervlakte = vloeroppervlak × ratio (het formulier vraagt geen m² glas).', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'window_area_ratio_by_window_type.mixed', 'category' => 'Koellast v2', 'label' => 'Raamratio gemengde ramen', 'unit' => '× vloeroppervlak', 'explanation' => 'Afgeleide raamoppervlakte bij gemengde ramen.', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'window_area_ratio_by_window_type.small', 'category' => 'Koellast v2', 'label' => 'Raamratio kleine ramen', 'unit' => '× vloeroppervlak', 'explanation' => 'Afgeleide raamoppervlakte bij kleine ramen.', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'window_area_ratio_by_window_type.few_none', 'category' => 'Koellast v2', 'label' => 'Raamratio weinig/geen ramen', 'unit' => '× vloeroppervlak', 'explanation' => 'Afgeleide raamoppervlakte bij weinig of geen ramen.', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'people_sensible_w_per_person', 'category' => 'Koellast v2', 'label' => 'Voelbare warmte per persoon', 'unit' => 'W', 'explanation' => 'Voelbare interne winst per aanwezige persoon (werkboek: 75 W).', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'people_latent_w_per_person', 'category' => 'Koellast v2', 'label' => 'Latente warmte per persoon', 'unit' => 'W', 'explanation' => 'Latente (vocht)winst per aanwezige persoon (werkboek: 55 W).', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'ventilation_ach_default', 'category' => 'Koellast v2', 'label' => 'Luchtverversingsvoud (ACH)', 'unit' => '1/h', 'explanation' => 'Aangenomen luchtwisselingen per uur voor ventilatie/infiltratie (werkboek: 0,5).', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'ventilation_sensible_w_per_m3_ach', 'category' => 'Koellast v2', 'label' => 'Ventilatiecoëfficiënt voelbaar', 'unit' => 'W per m³·ACH', 'explanation' => 'Werkboekconstante 2,67 ≈ 0,334 Wh/(m³·K) × ΔT 8 K.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'ventilation_latent_w_per_m3_ach', 'category' => 'Koellast v2', 'label' => 'Ventilatiecoëfficiënt latent', 'unit' => 'W per m³·ACH', 'explanation' => 'Werkboekconstante 1,3 (impliciet vochtverschil binnen/buiten).', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'safety_factor', 'category' => 'Koellast v2', 'label' => 'Veiligheidsfactor', 'unit' => '×', 'explanation' => 'Toegepast op de som van voelbare en latente last (werkboek: 1,1).', 'default_status' => 'te_valideren', 'critical' => true],

            // ── Capaciteit & multi-split ─────────────────────────────────────
            ['key' => 'capacity_classes', 'category' => 'Capaciteit', 'label' => 'Capaciteitsklassen', 'unit' => 'kW', 'explanation' => 'Vertaling van berekende koellast naar doelklasse (zoekdoel, geen productkeuze).', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'max_class_kw', 'category' => 'Capaciteit', 'label' => 'Hoogste klasse', 'unit' => 'kW', 'explanation' => 'Boven deze klasse is handmatige beoordeling vereist.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'capacity_match.max_oversize_factor', 'category' => 'Capaciteit', 'label' => 'Maximale overdimensionering', 'unit' => '× klasse', 'explanation' => 'Producten met meer vermogen dan klasse × deze factor worden niet voorgesteld.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'diversity_factors', 'category' => 'Capaciteit', 'label' => 'Gelijktijdigheidsfactoren multi-split', 'unit' => '×', 'explanation' => 'Som van binnenklassen × factor = geschatte buitenunitbehoefte. Fabrikantcompatibiliteit gaat altijd voor.', 'default_status' => 'te_valideren', 'critical' => true],

            // ── Leidingen & elektrisch ───────────────────────────────────────
            ['key' => 'pipe.bend_equivalent_m', 'category' => 'Leidingen', 'label' => 'Equivalent per bocht', 'unit' => 'm', 'explanation' => 'Elke bocht telt als deze extra leidinglengte.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'pipe.rise_equivalent_factor', 'category' => 'Leidingen', 'label' => 'Equivalentfactor stijging', 'unit' => '×', 'explanation' => 'Verticale stijging telt × deze factor mee.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'pipe.generic_warning_threshold_m', 'category' => 'Leidingen', 'label' => 'Waarschuwingsdrempel leidinglengte', 'unit' => 'm', 'explanation' => 'Boven deze equivalente lengte verschijnt een waarschuwing; productlimieten gaan altijd voor.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'pipe.assumed_length_m', 'category' => 'Leidingen', 'label' => 'Aangenomen leidinglengte', 'unit' => 'm', 'explanation' => 'Het formulier vraagt geen leidinglengte; dit installatieprofiel wordt aangenomen (met waarschuwing).', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'pipe.assumed_bends', 'category' => 'Leidingen', 'label' => 'Aangenomen bochten', 'unit' => 'stuks', 'explanation' => 'Aanname bij ontbrekende invoer.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'pipe.assumed_rise_m', 'category' => 'Leidingen', 'label' => 'Aangenomen hoogteverschil', 'unit' => 'm', 'explanation' => 'Aanname bij ontbrekende invoer.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'electrical_by_class', 'category' => 'Elektrisch', 'label' => 'Zekering/kabel per klasse', 'unit' => 'A / kabel', 'explanation' => 'Generieke schatting per capaciteitsklasse; fabrikantdata op het product gaat altijd voor. Elektrische controle blijft verplicht.', 'default_status' => 'fabrikantspecifiek', 'critical' => true],

            // ── Toebehoren ───────────────────────────────────────────────────
            ['key' => 'accessories.wall_bracket_per_outdoor_unit', 'category' => 'Toebehoren', 'label' => 'Muurbeugels per buitenunit', 'unit' => 'stuks', 'explanation' => 'Muurmontage aangenomen zolang de plaatsing onbekend is.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'accessories.vibration_dampers_per_outdoor_unit', 'category' => 'Toebehoren', 'label' => 'Trillingsdempers per buitenunit', 'unit' => 'sets', 'explanation' => 'Standaard meegerekend.', 'default_status' => 'te_valideren', 'critical' => false],
            ['key' => 'accessories.drain_hose_m_per_indoor_unit', 'category' => 'Toebehoren', 'label' => 'Afvoerslang per binnenunit', 'unit' => 'm', 'explanation' => 'Standaardlengte per binnenunit.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'accessories.trunking_m_factor_of_pipe', 'category' => 'Toebehoren', 'label' => 'Gootfactor t.o.v. leiding', 'unit' => '×', 'explanation' => 'Leidinggoot = leidinglengte × deze factor.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'accessories.cable_m_factor_of_pipe', 'category' => 'Toebehoren', 'label' => 'Kabelfactor t.o.v. leiding', 'unit' => '×', 'explanation' => 'Kabellengte = leidinglengte × deze factor.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'accessories.refrigerant_above_equivalent_m', 'category' => 'Toebehoren', 'label' => 'Koelmiddelmarkering vanaf', 'unit' => 'm equivalent', 'explanation' => 'Boven deze lengte wordt extra koelmiddel als optionele regel gemarkeerd; hoeveelheid bepaalt de installateur met fabrikantdata.', 'default_status' => 'fabrikantspecifiek', 'critical' => false],

            // ── Arbeid & verplaatsing ────────────────────────────────────────
            ['key' => 'labor.base_installation_hours', 'category' => 'Arbeid', 'label' => 'Basisuren installatie', 'unit' => 'uur', 'explanation' => 'Basisinstallatie van 1 binnenunit + buitenunit.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.extra_indoor_unit_hours', 'category' => 'Arbeid', 'label' => 'Uren per extra binnenunit', 'unit' => 'uur', 'explanation' => 'Bijkomende tijd per extra binnenunit.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.hours_per_pipe_m_over_5', 'category' => 'Arbeid', 'label' => 'Uren per meter leiding boven 5 m', 'unit' => 'uur/m', 'explanation' => 'Extra leidingwerk boven het basistraject.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.drilling_hours_per_room', 'category' => 'Arbeid', 'label' => 'Uren doorboring per kamer', 'unit' => 'uur', 'explanation' => 'Muurdoorvoer per kamer.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.condensate_pump_hours', 'category' => 'Arbeid', 'label' => 'Uren condensaatpomp', 'unit' => 'uur', 'explanation' => 'Alleen wanneer een pomp wordt meegerekend.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.electrical_work_hours', 'category' => 'Arbeid', 'label' => 'Uren elektrische aansluiting', 'unit' => 'uur', 'explanation' => 'Schatting voor de aparte voedingskring; blijft altijd te controleren.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.roof_access_surcharge_hours', 'category' => 'Arbeid', 'label' => 'Toeslag dak/zolder', 'unit' => 'uur', 'explanation' => 'Aanname bij zolder- of platdaksituaties.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.second_technician_from_indoor_units', 'category' => 'Arbeid', 'label' => 'Tweede technieker vanaf', 'unit' => 'binnenunits', 'explanation' => 'Vanaf dit aantal binnenunits wordt een tweede technieker aangenomen.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.second_technician_hours', 'category' => 'Arbeid', 'label' => 'Uren tweede technieker', 'unit' => 'uur', 'explanation' => 'Bijkomende uren wanneer een tweede technieker wordt aangenomen.', 'default_status' => 'placeholder', 'critical' => false],
            ['key' => 'labor.hourly_rate_excl_vat', 'category' => 'Arbeid', 'label' => 'Uurtarief', 'unit' => '€/uur excl. btw', 'explanation' => 'Het gehanteerde uurtarief in elke prijsberekening.', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'labor.travel_flat_excl_vat', 'category' => 'Arbeid', 'label' => 'Verplaatsingsforfait', 'unit' => '€ excl. btw', 'explanation' => 'Vast bedrag per installatie.', 'default_status' => 'placeholder', 'critical' => true],

            // ── Prijs & btw ──────────────────────────────────────────────────
            ['key' => 'pricing.fallback_margin_pct_on_purchase', 'category' => 'Prijs & btw', 'label' => 'Terugvalmarge op aankoopprijs', 'unit' => '%', 'explanation' => 'Gebruikt wanneer een product géén verkoopprijs heeft (duidelijk gelabeld).', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'pricing.material_markup_pct', 'category' => 'Prijs & btw', 'label' => 'Materiaalopslag', 'unit' => '%', 'explanation' => 'Opslag op de aankoopprijs van materialen zonder verkoopprijs.', 'default_status' => 'placeholder', 'critical' => true],
            ['key' => 'pricing.default_vat_rate', 'category' => 'Prijs & btw', 'label' => 'Standaard btw-tarief', 'unit' => '%', 'explanation' => 'Wordt toegepast tot Martin handmatig een ander tarief bevestigt.', 'default_status' => 'te_valideren', 'critical' => true],
            ['key' => 'pricing.reduced_vat_rate', 'category' => 'Prijs & btw', 'label' => 'Verlaagd btw-tarief', 'unit' => '%', 'explanation' => 'Enkel als suggestie bij woning >10 jaar + particulier; nooit automatisch toegepast.', 'default_status' => 'te_valideren', 'critical' => false],
        ];
    }

    public static function keys(): array
    {
        return array_column(self::entries(), 'key');
    }

    public static function criticalKeys(): array
    {
        return array_column(
            array_filter(self::entries(), fn ($e) => $e['critical']),
            'key'
        );
    }

    public static function value(array $configuration, string $key): mixed
    {
        return Arr::get($configuration, $key);
    }

    /**
     * Compact, human-readable rendering of a rule value.
     */
    public static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'ja' : 'nee';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $v = implode(' ', array_map(fn ($x) => (string) $x, $v));
                }
                $parts[] = (is_int($k) ? '' : "{$k}: ") . $v;
            }

            return implode(' · ', array_slice($parts, 0, 12)) . (count($parts) > 12 ? ' …' : '');
        }

        return (string) $value;
    }

    public static function statusLabel(string $status): string
    {
        return [
            'placeholder'        => 'Placeholder',
            'te_valideren'       => 'Te valideren',
            'fabrikantspecifiek' => 'Fabrikantspecifiek',
            'validated'          => 'Gevalideerd',
        ][$status] ?? $status;
    }
}
