<?php

// ─────────────────────────────────────────────────────────────────────────────
// HVAC pre-calculation rule set — version 1 defaults.
//
// These values are STARTING DEFAULTS, not certified engineering data. They are
// seeded into the hvac_rule_sets table (versioned) on first use; historical
// calculations always keep the snapshot of the rule set they ran with.
// Every value below must be validated by Martin before production use — see
// docs/hvac/rules-to-validate.md.
//
// Never read these values directly from services: resolve the active rule set
// through App\Services\Hvac\HvacRuleSetResolver so versioning keeps working.
// ─────────────────────────────────────────────────────────────────────────────

return [

    // Which explanation generator to use: 'null' (default, no AI) or a
    // container binding for a real provider. The system is fully functional
    // with the null provider.
    'explanation_provider' => env('HVAC_EXPLANATION_PROVIDER', 'null'),

    'default_rule_set' => [
        'name'    => 'Standaard voorcalculatie',
        'version' => 1,

        'configuration' => [

            // ── Cooling load ────────────────────────────────────────────────
            // Base load per m² by insulation level. other/unknown fall back to
            // 'average' AND raise a warning (never silently).
            'insulation_w_per_m2' => [
                'excellent' => 70,
                'good'      => 90,
                'average'   => 110,
                'poor'      => 140,
                'other'     => 110,
                'unknown'   => 110,
            ],
            'insulation_fallback_values' => ['other', 'unknown'],

            // Ceiling factor = height_m / reference height.
            'ceiling_reference_height_m' => 2.5,

            'orientation_factors' => [
                'north'   => 0.95,
                'east'    => 1.00,
                'west'    => 1.08,
                'south'   => 1.12,
                'other'   => 1.00,
                'unknown' => 1.00,
            ],
            'orientation_fallback_values' => ['other', 'unknown'],

            // The form asks a window TYPE (no window area). Factors mirror the
            // window-ratio table below. When a real window area becomes
            // available, the ratio table takes precedence.
            'window_type_factors' => [
                'large'    => 1.18,
                'mixed'    => 1.10,
                'small'    => 1.05,
                'few_none' => 1.00,
                'other'    => 1.05,
                'unknown'  => 1.05,
            ],
            'window_fallback_values' => ['other', 'unknown'],

            // Used only when window_area_m2 is known (not asked in the form yet).
            'window_ratio_factors' => [
                ['max_ratio' => 0.10, 'factor' => 1.00],
                ['max_ratio' => 0.20, 'factor' => 1.05],
                ['max_ratio' => 0.30, 'factor' => 1.10],
                ['max_ratio' => null,  'factor' => 1.18],
            ],

            // Roof/attic correction is deliberately NEUTRAL (1.00): no factor
            // may be invented without Martin's validation. Attic / flat-roof
            // rooms raise a warning that no validated correction is applied.
            'roof_type_factors' => [
                'flat_roof'              => 1.00,
                'attic_no_roof_window'   => 1.00,
                'attic_with_roof_window' => 1.00,
                'none'                   => 1.00,
                'other'                  => 1.00,
                'unknown'                => 1.00,
            ],
            'roof_warning_values' => ['flat_roof', 'attic_no_roof_window', 'attic_with_roof_window'],

            // ── Occupancy & equipment heat ──────────────────────────────────
            'occupancy' => [
                'included_persons'       => 1,
                'watts_per_extra_person' => 120,
                // The form does not ask occupancy — assumed per room type,
                // always with a warning.
                'default_occupants_by_room_type' => [
                    'slaapkamer'  => 2,
                    'woonkamer'   => 3,
                    'bureau'      => 1,
                    'keuken'      => 2,
                    'zolderkamer' => 2,
                    'andere'      => 2,
                ],
            ],

            'equipment_heat_w' => [
                'tv'           => 100,
                'pc'           => 150,
                'open_kitchen' => 300,
            ],
            // The form does not ask equipment — assumed per room type, always
            // with a warning.
            'default_equipment_by_room_type' => [
                'woonkamer'   => ['tv'],
                'bureau'      => ['pc'],
                'keuken'      => ['open_kitchen'],
                'slaapkamer'  => ['tv'],
                'zolderkamer' => [],
                'andere'      => [],
            ],

            // ── Capacity class ──────────────────────────────────────────────
            'capacity_classes' => [
                ['max_load_kw' => 2.2, 'class_kw' => 2.5],
                ['max_load_kw' => 3.2, 'class_kw' => 3.5],
                ['max_load_kw' => 4.6, 'class_kw' => 5.0],
                ['max_load_kw' => 6.3, 'class_kw' => 6.0],
            ],
            'max_class_kw' => 7.1,
            // Above max_class_kw × review threshold → manual review required.
            'manual_review_above_kw' => 7.1,

            // A product matches a room when its cooling capacity covers the
            // calculated load without absurd oversizing.
            'capacity_match' => [
                'max_oversize_factor' => 1.30, // vs target class kW
            ],

            // ── Multi-split ─────────────────────────────────────────────────
            'diversity_factors' => [
                '2'      => 0.90,
                '3'      => 0.88,
                '4'      => 0.85,
                '5_plus' => 0.82,
            ],

            // ── Pipes ───────────────────────────────────────────────────────
            'pipe' => [
                'bend_equivalent_m'           => 1.0,
                'rise_equivalent_factor'      => 0.5,
                'generic_warning_threshold_m' => 15,
                // The form does not ask pipe runs — assumed installation
                // profile, always with a warning.
                'assumed_length_m' => 5.0,
                'assumed_bends'    => 4,
                'assumed_rise_m'   => 2.5,
            ],

            // ── Electrical (generic estimates; manufacturer data overrides) ─
            'electrical_by_class' => [
                '2.5' => ['breaker_a' => 16, 'cable' => '3G2.5'],
                '3.5' => ['breaker_a' => 16, 'cable' => '3G2.5'],
                '5.0' => ['breaker_a' => 20, 'cable' => '3G2.5 of 3G4'],
                '6.0' => ['breaker_a' => 20, 'cable' => '3G2.5 of 3G4'],
                '7.1' => ['breaker_a' => 25, 'cable' => '3G4'],
            ],
            'electrical_default_voltage' => '230V mono',

            // ── Accessories (quantity rules; prices come from the catalog) ──
            'accessories' => [
                'wall_bracket_per_outdoor_unit'      => 1,
                'vibration_dampers_per_outdoor_unit' => 1, // sets
                'drain_hose_m_per_indoor_unit'       => 5,
                'trunking_m_factor_of_pipe'          => 1.0,
                'cable_m_factor_of_pipe'             => 1.2,
                'wall_drills_per_room'               => 1,
            ],

            // ── Labor (PLACEHOLDERS — Martin must validate every value) ─────
            'labor' => [
                'base_installation_hours'   => 6.0,
                'extra_indoor_unit_hours'   => 3.0,
                'hours_per_pipe_m_over_5'   => 0.25,
                'condensate_pump_hours'     => 1.0,
                'drilling_hours_per_room'   => 0.5,
                'roof_access_surcharge_hours' => 2.0,
                'hourly_rate_excl_vat'      => 65.0,
                'travel_flat_excl_vat'      => 35.0,
            ],

            // ── Pricing (PLACEHOLDERS — Martin must validate) ───────────────
            'pricing' => [
                // Used when a product has no default sale price:
                'fallback_margin_pct_on_purchase' => 35.0,
                // Material lines without a catalog price get flagged, never priced.
                'material_markup_pct'             => 25.0,
                'default_vat_rate'                => 21.0,
                'reduced_vat_rate'                => 6.0,
                // 6% is only SUGGESTED (never auto-applied) when the dwelling
                // is older than 10 years and the customer is residential.
                'reduced_vat_requires_confirmation' => true,
            ],
        ],
    ],
];
