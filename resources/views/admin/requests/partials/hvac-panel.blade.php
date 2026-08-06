{{--
    Automatische airco-voorcalculatie — only rendered for airco_offerte
    requests. Shows the full deterministic calculation (never raw JSON),
    product options, pricing and audit trail. Nothing here ever reaches the
    customer without Martin's explicit approval + conversion + send.
--}}
@php
    $hvacCalculation = $customerRequest->hvacCalculations()
        ->with(['ruleSet', 'overrides', 'recommendations' => fn ($q) => $q->whereNotIn('status', ['superseded'])->with('items.product')])
        ->first();

    $hvacStatusLabels = [
        'draft'         => 'Concept — klaar voor beoordeling',
        'manual_review' => 'Handmatige controle vereist',
        'approved'      => 'Goedgekeurd',
        'rejected'      => 'Afgewezen',
        'converted'     => 'Omgezet naar offerte',
    ];
    $hvacOptionLabels = [
        'budget'      => 'Budget',
        'recommended' => 'Aanbevolen',
        'premium'     => 'Premium',
    ];
    $hvacWarningList = [];
    if ($hvacCalculation) {
        $hvacWarningList = array_merge(
            $hvacCalculation->warnings['warnings'] ?? [],
            $hvacCalculation->warnings['selection'] ?? [],
        );
    }
    // One query for the product-change dropdowns instead of one per item row.
    $hvacAltProducts = $hvacCalculation
        ? \App\Models\HvacProduct::active()
            ->whereIn('product_type', ['single_split_set', 'indoor_unit', 'outdoor_unit', 'multi_split_outdoor'])
            ->orderBy('model')
            ->get(['id', 'model', 'sku', 'product_type'])
            ->groupBy('product_type')
        : collect();

    $hvacRooms = $hvacCalculation?->normalized_input['rooms'] ?? [];
    $hvacRoomResults = $hvacCalculation?->result['rooms'] ?? [];
    // Admin load-overrides produce a parallel system block; the original stays intact.
    $hvacSystem = $hvacCalculation?->result['system_with_overrides']
        ?? $hvacCalculation?->result['system']
        ?? null;
    $hvacSystemIsOverridden = isset($hvacCalculation?->result['system_with_overrides']);
@endphp

<style>
.hvac-panel h3 { font-size: 0.95rem; margin: 1.1rem 0 0.5rem; }
.hvac-disclaimer { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 8px; padding: 0.7rem 1rem; font-size: 0.85rem; margin: 0.75rem 0; }
.hvac-warning-list { margin: 0; padding-left: 1.2rem; color: #b45309; font-size: 0.85rem; }
.hvac-warning-list li { margin-bottom: 0.2rem; }
.hvac-blocker-list { margin: 0; padding-left: 1.2rem; color: #dc2626; font-size: 0.88rem; }
.hvac-room-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 0.9rem; }
.hvac-room-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.9rem; font-size: 0.85rem; }
.hvac-room-card h4 { margin: 0 0 0.5rem; font-size: 0.9rem; }
.hvac-room-card dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 0.15rem 0.6rem; }
.hvac-room-card dt { color: #6b7280; }
.hvac-room-card dd { margin: 0; }
.hvac-assumed { color: #b45309; font-size: 0.78rem; }
.hvac-option { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
.hvac-option-header { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; align-items: center; justify-content: space-between; }
.hvac-option-status { font-size: 0.8rem; padding: 0.2rem 0.7rem; border-radius: 999px; background: #eef2f7; }
.hvac-option-status.is-approved { background: #dcfce7; color: #166534; }
.hvac-option-status.is-manual { background: #fffbeb; color: #92400e; }
.hvac-option-status.is-rejected { background: #fee2e2; color: #991b1b; }
.hvac-price-summary { display: grid; grid-template-columns: auto auto; gap: 0.15rem 1.5rem; width: fit-content; font-size: 0.88rem; margin-top: 0.6rem; }
.hvac-price-summary .is-total { font-weight: 700; }
.hvac-margin { color: #166534; font-size: 0.82rem; }
.hvac-inline-form { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; margin-top: 0.4rem; }
.hvac-inline-form input, .hvac-inline-form select { padding: 0.3rem 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; }
.hvac-item-row td { font-size: 0.84rem; }
.hvac-audit { font-size: 0.8rem; color: #6b7280; margin-top: 0.9rem; border-top: 1px solid #e5e7eb; padding-top: 0.6rem; }
</style>

<div class="admin-detail-card hvac-panel">
    <h2>Automatische airco-voorcalculatie</h2>

    <p class="hvac-disclaimer">
        Automatische voorcalculatie. Controleer vermogen, compatibiliteit, leidinglengtes,
        elektrische vereisten en plaatsingssituatie vóór verzending.
    </p>

    @if (session('success') === 'hvac_calculated')
        <div class="form-success">Voorcalculatie uitgevoerd.</div>
    @elseif (session('success') === 'hvac_calculation_blocked')
        <div class="form-error-list">De berekening kon niet uitgevoerd worden — zie de blokkerende punten hieronder.</div>
    @elseif (session('success') === 'hvac_calculation_in_progress')
        <div class="form-error-list">Er loopt al een berekening. Probeer zo dadelijk opnieuw.</div>
    @elseif (session('success') === 'hvac_recommendation_approved')
        <div class="form-success">Optie goedgekeurd.</div>
    @elseif (session('success') === 'hvac_recommendation_rejected')
        <div class="form-success">Optie afgewezen.</div>
    @elseif (session('success') === 'hvac_warnings_acknowledged')
        <div class="form-success">Waarschuwingen bevestigd — de optie kan nu goedgekeurd worden.</div>
    @elseif (session('success') === 'hvac_override_applied')
        <div class="form-success">Aanpassing opgeslagen en totalen herrekend.</div>
    @elseif (session('success') === 'hvac_already_approved')
        <div class="form-error-list">Deze optie werd eerder al goedgekeurd.</div>
    @elseif (session('success') === 'hvac_not_approvable')
        <div class="form-error-list">Deze optie kan niet goedgekeurd worden in de huidige status. Bevestig eerst de waarschuwingen.</div>
    @elseif (session('success') === 'hvac_converted')
        <div class="form-success">Optie omgezet naar een conceptofferte. Controleer de offerte en de PDF vóór verzending — er wordt nooit automatisch gemaild.</div>
    @elseif (session('success') === 'hvac_conversion_in_progress')
        <div class="form-error-list">Er loopt al een omzetting. Probeer zo dadelijk opnieuw.</div>
    @endif

    @error('hvac_convert')
        <div class="form-error-list">{{ $message }}</div>
    @enderror

    @error('hvac_approve')
        <div class="form-error-list">Goedkeuren geblokkeerd: {{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('admin.requests.hvac.calculate', $customerRequest) }}" style="margin-bottom: 0.5rem;">
        @csrf
        <button type="submit" class="button button-primary">
            {{ $hvacCalculation ? 'Opnieuw berekenen' : 'Voorcalculatie uitvoeren' }}
        </button>
    </form>

    @if (! $hvacCalculation)
        <p class="admin-muted-text">Nog geen voorcalculatie uitgevoerd voor deze aanvraag.</p>
    @elseif ($hvacCalculation->status === 'blocked')
        <h3>Berekening geblokkeerd</h3>
        <ul class="hvac-blocker-list">
            @foreach ($hvacCalculation->warnings['blockers'] ?? [] as $blocker)
                <li>{{ $blocker['message'] }}</li>
            @endforeach
        </ul>
    @else
        {{-- A. Input per kamer --}}
        <h3>Invoer per kamer</h3>
        <div class="hvac-room-cards">
            @foreach ($hvacRooms as $i => $room)
                @php $roomResult = $hvacRoomResults[$i] ?? null; @endphp
                <div class="hvac-room-card">
                    <h4>{{ $room['name'] }}</h4>
                    <dl>
                        <dt>Afmetingen</dt>
                        <dd>{{ $room['width_m'] }} × {{ $room['length_m'] }} × {{ $room['height_m'] }} m</dd>
                        <dt>Oppervlakte</dt>
                        <dd>{{ $room['area_m2'] }} m²</dd>
                        <dt>Isolatie</dt>
                        <dd>{{ $room['insulation'] }}</dd>
                        <dt>Ligging</dt>
                        <dd>{{ $room['orientation'] }}</dd>
                        <dt>Dak</dt>
                        <dd>{{ $room['roof_type'] }}</dd>
                        <dt>Ramen</dt>
                        <dd>{{ $room['window_type'] }}</dd>
                        <dt>Personen</dt>
                        <dd>{{ $room['occupants'] }} @if ($room['occupants_assumed'])<span class="hvac-assumed">(aanname)</span>@endif</dd>
                        <dt>Toestellen</dt>
                        <dd>{{ $room['equipment'] !== [] ? implode(', ', $room['equipment']) : 'geen' }} @if ($room['equipment_assumed'])<span class="hvac-assumed">(aanname)</span>@endif</dd>
                        <dt>Leiding</dt>
                        <dd>{{ $room['pipe_length_m'] }} m, {{ $room['pipe_bends'] }} bochten, {{ $room['pipe_rise_m'] }} m stijging @if ($room['pipe_assumed'])<span class="hvac-assumed">(aanname)</span>@endif</dd>
                        <dt>Afvoer</dt>
                        <dd>{{ $room['drainage'] === 'unknown' ? 'onbekend' : $room['drainage'] }}</dd>
                        @if ($roomResult)
                            <dt>Equiv. leiding</dt>
                            <dd>{{ $roomResult['pipe']['equivalent_length_m'] }} m</dd>
                        @endif
                    </dl>
                </div>
            @endforeach
        </div>

        @php $hvacPhotosCount = (int) ($hvacCalculation->normalized_input['photos_count'] ?? 0); @endphp
        @if ($hvacPhotosCount > 0)
            <p class="hvac-muted" style="margin-top:0.5rem;">
                {{ $hvacPhotosCount }} foto('s) van de klant beschikbaar in de kaart "Bijlagen" hieronder.
            </p>
        @endif

        {{-- B. Berekening --}}
        <h3>Berekening</h3>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Kamer</th><th>W/m²</th><th>Hoogtefactor</th><th>Ligging</th><th>Ramen</th><th>Dak</th>
                        <th>Basis (W)</th><th>Personen (W)</th><th>Toestellen (W)</th><th>Totaal (W)</th><th>kW</th><th>Doelklasse</th>
                        <th>Zekering</th><th>Kabel</th><th>Aanpassen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hvacRoomResults as $ri => $roomResult)
                        @php
                            $load = $roomResult['load'];
                            $wattsOverride = $load['final_watts_override'] ?? null;
                            $classShown = $roomResult['capacity_class_kw_override'] ?? $roomResult['capacity_class_kw'];
                        @endphp
                        <tr>
                            <td>{{ $load['room_name'] }}</td>
                            <td>{{ $load['insulation_w_per_m2'] }}</td>
                            <td>{{ $load['ceiling_factor'] }}</td>
                            <td>{{ $load['orientation_factor'] }}</td>
                            <td>{{ $load['window_factor'] }}</td>
                            <td>{{ $load['roof_factor'] }}</td>
                            <td>{{ number_format($load['base_watts'], 0, ',', '.') }}</td>
                            <td>{{ $load['occupancy_heat_w'] }}</td>
                            <td>{{ $load['equipment_heat_w'] }}</td>
                            <td>
                                @if ($wattsOverride !== null)
                                    <strong>{{ number_format($wattsOverride, 0, ',', '.') }}</strong>
                                    <br><span class="hvac-assumed">handmatig — origineel {{ number_format($load['final_watts'], 0, ',', '.') }} W</span>
                                @else
                                    <strong>{{ number_format($load['final_watts'], 0, ',', '.') }}</strong>
                                @endif
                            </td>
                            <td><strong>{{ number_format(($load['final_kw_override'] ?? $load['final_kw']), 2, ',', '.') }}</strong></td>
                            <td>
                                @if ($classShown !== null)
                                    {{ number_format($classShown, 1, ',', '.') }} kW
                                    @if (isset($roomResult['capacity_class_kw_override']))
                                        <span class="hvac-assumed">(handmatig)</span>
                                    @endif
                                @else
                                    <span class="hvac-assumed">handmatig</span>
                                @endif
                            </td>
                            <td>{{ $roomResult['electrical']['breaker_a'] !== null ? $roomResult['electrical']['breaker_a'] . ' A' : '—' }}</td>
                            <td>{{ $roomResult['electrical']['cable'] ?? '—' }}</td>
                            <td>
                                <details>
                                    <summary style="cursor:pointer;font-size:0.78rem;">Vermogen aanpassen</summary>
                                    <form class="hvac-inline-form" method="POST"
                                          action="{{ route('admin.requests.hvac.rooms.override-load', $customerRequest) }}">
                                        @csrf
                                        <input type="hidden" name="room_index" value="{{ $ri }}">
                                        <label style="display:flex;flex-direction:column;font-size:0.72rem;gap:0.1rem;">
                                            Watt
                                            <input type="number" step="1" min="100" max="20000" name="watts"
                                                   value="{{ $wattsOverride ?? $load['final_watts'] }}" style="width:6rem;">
                                        </label>
                                        <input type="text" name="reason" placeholder="Reden (verplicht)" required minlength="5" style="width:10rem;">
                                        <button type="submit" class="button button-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">Opslaan</button>
                                    </form>
                                    <p class="hvac-assumed" style="margin:0.2rem 0 0;">Opties worden herrekend met de nieuwe doelklasse.</p>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('watts')
            <p class="field-error-text">{{ $message }}</p>
        @enderror
        @error('room_index')
            <p class="field-error-text">{{ $message }}</p>
        @enderror

        @if ($hvacSystem)
            <p style="font-size: 0.88rem; margin-top: 0.6rem;">
                <strong>Systeem:</strong>
                @if ($hvacSystemIsOverridden)
                    <span class="hvac-assumed">(met handmatige aanpassingen)</span>
                @endif
                {{ $hvacSystem['split_type'] === 'multi_split' ? 'Multi split' : 'Single split' }}
                — totale koellast {{ number_format($hvacSystem['total_load_kw'], 2, ',', '.') }} kW
                @if ($hvacSystem['diversity_factor'] !== null)
                    — som klassen {{ number_format($hvacSystem['sum_of_classes_kw'], 1, ',', '.') }} kW ×
                    gelijktijdigheid {{ $hvacSystem['diversity_factor'] }} =
                    <strong>{{ number_format($hvacSystem['estimated_outdoor_kw'], 2, ',', '.') }} kW buitenunit</strong>
                @endif
                — btw-voorstel: {{ number_format($hvacSystem['vat']['suggested_rate'], 0) }}%
                <span class="hvac-assumed">({{ $hvacSystem['vat']['note'] }})</span>
            </p>
        @endif

        {{-- F. Waarschuwingen --}}
        @if ($hvacWarningList !== [])
            <h3>Waarschuwingen</h3>
            <ul class="hvac-warning-list">
                @foreach ($hvacWarningList as $warning)
                    <li>{{ $warning['message'] }}</li>
                @endforeach
            </ul>
        @endif

        {{-- C/D/E. Opties --}}
        @if ($hvacCalculation->recommendations->isEmpty())
            <h3>Aanbevelingen</h3>
            <p class="admin-muted-text">
                Geen geldige productcombinaties gevonden in de catalogus — handmatige productkeuze vereist.
                Vul de catalogus of compatibiliteitsdata aan en bereken opnieuw.
            </p>
        @else
            @foreach ($hvacCalculation->recommendations as $recommendation)
                @php
                    $recWarnings = $recommendation->metadata['warnings'] ?? [];
                    $laborDetail = $recommendation->metadata['labor'] ?? null;
                    $candidate   = $recommendation->metadata['candidate'] ?? null;
                    $requestLocale = in_array($customerRequest->locale, ['nl', 'fr', 'en'], true) ? $customerRequest->locale : 'nl';
                    $aiExplanation = $recommendation->{'explanation_' . $requestLocale}
                        ?? $recommendation->explanation_nl;
                    // Internal cost view (admin-only): purchase totals + labor at cost.
                    $purchaseTotal = $recommendation->items
                        ->whereIn('item_type', ['equipment', 'material'])
                        ->filter(fn ($i) => $i->purchase_unit_price !== null)
                        ->sum(fn ($i) => (float) $i->purchase_unit_price * (float) $i->quantity);
                    $purchaseComplete = $recommendation->items
                        ->whereIn('item_type', ['equipment', 'material'])
                        ->every(fn ($i) => $i->purchase_unit_price !== null
                            || (($i->metadata['mandatory'] ?? true) === false && (float) $i->sale_unit_price === 0.0));
                    $internalCost = $purchaseTotal
                        + (float) $recommendation->labor_total_excl_vat
                        + (float) $recommendation->travel_total_excl_vat;
                    $readinessEval = app(\App\Services\Hvac\HvacRecommendationReadiness::class)->evaluate($recommendation);
                @endphp
                <div class="hvac-option">
                    <div class="hvac-option-header">
                        <h3 style="margin: 0;">Optie: {{ $hvacOptionLabels[$recommendation->option_type] ?? ucfirst($recommendation->option_type) }}</h3>
                        <span class="hvac-option-status {{ $recommendation->status === 'approved' || $readinessEval['ready'] ? 'is-approved' : ($recommendation->status === 'rejected' ? 'is-rejected' : 'is-manual') }}">
                            {{ $readinessEval['state_label'] }}
                        </span>
                    </div>

                    @if ($readinessEval['demo'])
                        <p class="hvac-disclaimer" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin:0.5rem 0 0;">
                            Testcatalogus — niet gebruiken voor echte offertes.
                        </p>
                    @endif

                    @if ($readinessEval['blockers'] !== [] && in_array($recommendation->status, ['draft', 'manual_review'], true))
                        <ul class="hvac-warning-list" style="margin-top:0.5rem;">
                            @foreach ($readinessEval['blockers'] as $blocker)
                                <li>{{ $blocker }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($candidate)
                        <p style="font-size: 0.82rem; color: #6b7280; margin: 0.4rem 0 0;">
                            Compatibiliteit: {{ $candidate['compatibility'] === 'set' ? 'fabrieksset' : 'catalogusregels' }}
                            — leidingcontrole: {{ $candidate['checks']['pipe'] ?? '—' }}
                            — hoogtecontrole: {{ $candidate['checks']['height'] ?? '—' }}
                            — elektrisch: voeding onbekend, controle vereist
                        </p>
                    @endif

                    <div class="admin-table-wrapper" style="margin-top: 0.6rem;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Type</th><th>Omschrijving</th><th>SKU</th><th>Aantal</th>
                                    <th>Verkoopprijs</th><th>Regeltotaal</th><th>Aankoop</th><th>Reden / bron</th><th>Aanpassen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recommendation->items as $item)
                                    <tr class="hvac-item-row">
                                        <td>{{ ['equipment' => 'Toestel', 'material' => 'Materiaal', 'labor' => 'Arbeid', 'travel' => 'Verplaatsing', 'discount' => 'Korting'][$item->item_type] ?? $item->item_type }}</td>
                                        <td>
                                            {{ $item->description }}
                                            @if ($item->product && $item->item_type === 'equipment')
                                                <br><span class="hvac-muted" style="font-size:0.76rem;">
                                                    {{ $item->product->cooling_capacity_kw !== null ? number_format($item->product->cooling_capacity_kw, 1, ',', '.') . ' kW · ' : '' }}
                                                    voorraad: {{ $item->product->stock_quantity ?? '?' }} ·
                                                    levertijd: {{ $item->product->lead_time_days !== null ? $item->product->lead_time_days . ' d' : '?' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ $item->sku ?? '—' }}</td>
                                        <td>{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }} {{ $item->unit }}</td>
                                        <td>€ {{ number_format($item->sale_unit_price, 2, ',', '.') }}</td>
                                        <td>€ {{ number_format($item->line_total, 2, ',', '.') }}</td>
                                        <td>{{ $item->purchase_unit_price !== null ? '€ ' . number_format($item->purchase_unit_price, 2, ',', '.') : '—' }}</td>
                                        <td style="font-size: 0.78rem; color: #6b7280;">
                                            {{ $item->metadata['reason'] ?? '' }}
                                            @if (($item->metadata['price_source'] ?? '') === 'missing')
                                                <strong style="color:#dc2626;">Prijs ontbreekt</strong>
                                            @endif
                                            @if ($item->metadata['manually_selected'] ?? false)
                                                <strong>Handmatig gekozen</strong>
                                            @endif
                                        </td>
                                        <td>
                                            @if (in_array($recommendation->status, ['draft', 'manual_review'], true))
                                                <details>
                                                    <summary style="cursor:pointer;font-size:0.78rem;">Waarde aanpassen</summary>
                                                    <form class="hvac-inline-form" method="POST"
                                                          action="{{ route('admin.requests.hvac.items.override', [$customerRequest, $item]) }}">
                                                        @csrf
                                                        <input type="number" step="0.01" min="0.01" name="quantity" placeholder="Aantal" value="{{ $item->quantity }}" style="width: 5.5rem;">
                                                        <input type="number" step="0.01" min="0" name="sale_unit_price" placeholder="Prijs" value="{{ $item->sale_unit_price }}" style="width: 6.5rem;">
                                                        <input type="text" name="reason" placeholder="Reden (verplicht)" required minlength="5" style="width: 11rem;">
                                                        <button type="submit" class="button button-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">Opslaan</button>
                                                    </form>
                                                    @if ($item->item_type === 'equipment')
                                                        <form class="hvac-inline-form" method="POST"
                                                              action="{{ route('admin.requests.hvac.items.change-product', [$customerRequest, $item]) }}">
                                                            @csrf
                                                            <select name="product_id" required>
                                                                <option value="">Ander product…</option>
                                                                @foreach ($hvacAltProducts[$item->product?->product_type ?? 'single_split_set'] ?? [] as $alt)
                                                                    <option value="{{ $alt->id }}">{{ $alt->model }} ({{ $alt->sku }})</option>
                                                                @endforeach
                                                            </select>
                                                            <input type="text" name="reason" placeholder="Reden (verplicht)" required minlength="5" style="width: 11rem;">
                                                            <button type="submit" class="button button-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">Product wijzigen</button>
                                                        </form>
                                                    @endif
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($laborDetail)
                        <details style="margin-top: 0.5rem;">
                            <summary style="cursor:pointer;font-size:0.82rem;">Arbeidsdetail ({{ $laborDetail['total_hours'] }} u × € {{ number_format($laborDetail['hourly_rate'], 2, ',', '.') }})</summary>
                            <ul class="hvac-warning-list" style="color:#374151;">
                                @foreach ($laborDetail['lines'] as $line)
                                    <li>{{ $line['hours'] }} u — {{ $line['reason'] }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @if ($aiExplanation)
                        <div class="hvac-disclaimer" style="background:#eef6ff;border-color:#bfdbfe;color:#1e3a8a;">
                            <strong>Gevalideerde AI-uitleg ({{ strtoupper($requestLocale) }}):</strong> {{ $aiExplanation }}
                        </div>
                    @endif

                    <div class="hvac-price-summary">
                        <span class="hvac-muted">Aankoopkost toestellen + materiaal</span>
                        <span class="hvac-muted">
                            € {{ number_format($purchaseTotal, 2, ',', '.') }}{{ $purchaseComplete ? '' : ' (onvolledig)' }}
                        </span>
                        <span class="hvac-muted">Totale interne kost (incl. arbeid & verplaatsing)</span>
                        <span class="hvac-muted">
                            € {{ number_format($internalCost, 2, ',', '.') }}{{ $purchaseComplete ? '' : ' (onvolledig)' }}
                        </span>
                        <span>Toestellen excl. btw</span><span>€ {{ number_format($recommendation->equipment_total_excl_vat, 2, ',', '.') }}</span>
                        <span>Materialen excl. btw</span><span>€ {{ number_format($recommendation->materials_total_excl_vat, 2, ',', '.') }}</span>
                        <span>Arbeid excl. btw</span><span>€ {{ number_format($recommendation->labor_total_excl_vat, 2, ',', '.') }}</span>
                        <span>Verplaatsing excl. btw</span><span>€ {{ number_format($recommendation->travel_total_excl_vat, 2, ',', '.') }}</span>
                        <span>Subtotaal excl. btw</span><span>€ {{ number_format($recommendation->subtotal_excl_vat, 2, ',', '.') }}</span>
                        <span>Btw ({{ number_format($recommendation->vat_rate, 0) }}%)</span><span>€ {{ number_format($recommendation->vat_amount, 2, ',', '.') }}</span>
                        <span class="is-total">Totaal incl. btw</span><span class="is-total">€ {{ number_format($recommendation->total_incl_vat, 2, ',', '.') }}</span>
                        @if ($recommendation->margin_amount !== null)
                            <span class="hvac-margin">Marge</span>
                            <span class="hvac-margin">€ {{ number_format($recommendation->margin_amount, 2, ',', '.') }} ({{ $recommendation->margin_percentage }}%)</span>
                        @else
                            <span class="hvac-margin">Marge</span><span class="hvac-margin">onvolledig — aankoopprijzen ontbreken</span>
                        @endif
                    </div>

                    {{-- Option warnings --}}
                    @if ($recWarnings !== [])
                        <details style="margin-top: 0.5rem;">
                            <summary style="cursor:pointer;font-size:0.82rem;color:#b45309;">Waarschuwingen bij deze optie ({{ count($recWarnings) }})</summary>
                            <ul class="hvac-warning-list">
                                @foreach ($recWarnings as $warning)
                                    <li>{{ $warning['message'] }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    {{-- Actions --}}
                    <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.9rem;">
                        @if ($recommendation->status === 'manual_review')
                            <form class="hvac-inline-form" method="POST"
                                  action="{{ route('admin.requests.hvac.acknowledge', [$customerRequest, $recommendation]) }}">
                                @csrf
                                <input type="text" name="reason" placeholder="Reden bevestiging (verplicht)" required minlength="5" style="width: 16rem;">
                                <button type="submit" class="button button-secondary">Waarschuwing bevestigen</button>
                            </form>
                        @endif

                        @if ($recommendation->status === 'draft')
                            <form method="POST" action="{{ route('admin.requests.hvac.approve', [$customerRequest, $recommendation]) }}">
                                @csrf
                                <button type="submit" class="button button-primary">Optie goedkeuren</button>
                            </form>
                        @endif

                        @if ($recommendation->status === 'approved')
                            <form method="POST" action="{{ route('admin.requests.hvac.convert', [$customerRequest, $recommendation]) }}"
                                  onsubmit="return confirm('Deze goedgekeurde optie omzetten naar een conceptofferte?');">
                                @csrf
                                <button type="submit" class="button button-primary">Omzetten naar offerte</button>
                            </form>
                        @endif

                        @if ($recommendation->status === 'converted' && $recommendation->converted_quote_id)
                            <span class="hvac-option-status is-approved">
                                Offerte {{ $recommendation->convertedQuote?->quote_number }} aangemaakt — verzending blijft een aparte handmatige stap.
                            </span>
                        @endif

                        @if (in_array($recommendation->status, ['draft', 'manual_review', 'approved'], true))
                            <form method="POST" action="{{ route('admin.requests.hvac.reject', [$customerRequest, $recommendation]) }}"
                                  onsubmit="return confirm('Deze optie afwijzen?');">
                                @csrf
                                <button type="submit" class="button button-secondary">Optie afwijzen</button>
                            </form>
                        @endif

                        @if (in_array($recommendation->status, ['draft', 'manual_review'], true))
                            <form class="hvac-inline-form" method="POST"
                                  action="{{ route('admin.requests.hvac.discount', [$customerRequest, $recommendation]) }}">
                                @csrf
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Korting €" required style="width:6.5rem;">
                                <input type="text" name="reason" placeholder="Reden (verplicht)" required minlength="5" style="width:12rem;">
                                <button type="submit" class="button button-secondary">Korting toevoegen</button>
                            </form>

                            <form class="hvac-inline-form" method="POST"
                                  action="{{ route('admin.requests.hvac.vat', [$customerRequest, $recommendation]) }}">
                                @csrf
                                <select name="vat_rate" required>
                                    <option value="21" @selected((float) $recommendation->vat_rate === 21.0)>21% btw</option>
                                    <option value="6" @selected((float) $recommendation->vat_rate === 6.0)>6% btw</option>
                                </select>
                                <input type="text" name="reason" placeholder="Reden (verplicht)" required minlength="5" style="width: 13rem;">
                                <button type="submit" class="button button-secondary">Btw aanpassen</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        {{-- G. Audit --}}
        <div class="hvac-audit">
            Regelset: {{ $hvacCalculation->ruleSet?->name }} v{{ $hvacCalculation->ruleSet?->version }}
            · Berekend op {{ $hvacCalculation->calculated_at->format('d/m/Y H:i') }}
            @if ($hvacCalculation->calculated_by) door {{ $hvacCalculation->calculated_by }} @endif
            @if ($hvacCalculation->manually_overridden_at)
                · Handmatig aangepast op {{ $hvacCalculation->manually_overridden_at->format('d/m/Y H:i') }}
                door {{ $hvacCalculation->manually_overridden_by }}
            @endif

            @if ($hvacCalculation->overrides->isNotEmpty())
                <details style="margin-top: 0.4rem;">
                    <summary style="cursor:pointer;">Handmatige aanpassingen ({{ $hvacCalculation->overrides->count() }})</summary>
                    <ul class="hvac-warning-list" style="color:#374151;">
                        @foreach ($hvacCalculation->overrides as $override)
                            <li>
                                {{ $override->created_at->format('d/m/Y H:i') }} — {{ $override->overridden_by }}:
                                {{ $override->field }} van "{{ $override->original_value }}" naar "{{ $override->overridden_value }}"
                                — reden: {{ $override->reason }}
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif
</div>
