@extends('layouts.app')

@section('title', 'Admin | Snelle inschatting koelvermogen')

@php
    use App\Services\Hvac\HvacSettingsGuide as Guide;
    $fieldLabels = ['length_m' => 'Lengte van de ruimte', 'width_m' => 'Breedte van de ruimte', 'height_m' => 'Hoogte van de ruimte'];
    $errorsByField = $result['errors'] ?? [];
@endphp

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Offerte-instellingen · Koelvermogen</span>
            <h1>Snelle inschatting</h1>
            <p>
                Een eerste idee van het koelvermogen op basis van het volume van de ruimte en één vuistregel per m³.
                Uitsluitend indicatief — geen toestelkeuze, geen offerte.
            </p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container qs-stack">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.settings.partials.subnav', ['current' => 'quick-estimate', 'concept' => null])

            @if (! $available)
                <div class="qs-card">
                    <p><span class="qs-status qs-status--unavailable">Niet beschikbaar</span></p>
                    <p>De instellingen die nu in gebruik zijn (versie {{ $ruleSet->version }}) bevatten geen vuistregels per m³. Neem contact op met de ontwikkelaar.</p>
                </div>
            @else
                @if ($submitted && $errorsByField !== [])
                    <div class="form-error-list" role="alert" id="qs-errors">
                        <strong>De inschatting kon niet berekend worden:</strong>
                        <ul>
                            @foreach ($errorsByField as $field => $message)
                                <li><a href="#qe-{{ $field }}">{{ $message }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="GET" action="{{ route('admin.hvac.rules.quick-estimate') }}" class="qs-card qs-stack" novalidate>
                    <h2>Gegevens van de ruimte</h2>

                    <div class="qs-dimensions">
                        @foreach ($fieldLabels as $field => $label)
                            <label class="qs-field @if (isset($errorsByField[$field])) qs-field--error @endif" for="qe-{{ $field }}">
                                {{ $label }} (m)
                                <input type="text" inputmode="decimal" name="{{ $field }}" id="qe-{{ $field }}"
                                       value="{{ $input[$field] }}" autocomplete="off" required
                                       placeholder="{{ $field === 'height_m' ? 'bv. 2,5' : 'bv. 5' }}"
                                       @if (isset($errorsByField[$field])) aria-invalid="true" aria-describedby="qe-{{ $field }}-error" @endif>
                                @if (isset($errorsByField[$field]))
                                    <span class="qs-error" id="qe-{{ $field }}-error">{{ $errorsByField[$field] }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    <fieldset class="qs-situations" id="qe-situation"
                              @if (isset($errorsByField['situation'])) aria-describedby="qe-situation-error" @endif>
                        <legend>Situatie van de ruimte — kies er één</legend>
                        @foreach ($situations as $key => $situation)
                            <label class="qs-situation">
                                <input type="radio" name="situation" value="{{ $key }}" @checked($input['situation'] === $key) required>
                                <span class="qs-situation__label">{{ $situation['label'] }}</span>
                                <span class="qs-situation__value">{{ Guide::nl($situation['w_per_m3']) }} W/m³</span>
                            </label>
                        @endforeach
                        @if (isset($errorsByField['situation']))
                            <span class="qs-error" id="qe-situation-error">{{ $errorsByField['situation'] }}</span>
                        @endif
                        <span class="qs-muted">De situaties zijn afzonderlijke keuzes. Ze worden niet opgeteld of met elkaar vermenigvuldigd.</span>
                    </fieldset>

                    <div>
                        <button type="submit" class="button button-primary" style="margin-top:0;">Inschatting berekenen</button>
                    </div>
                </form>

                @if ($result && $result['ok'])
                    <div class="qs-result" id="resultaat" role="status">
                        <span class="qs-result__label">Indicatief koelvermogen</span>
                        <p class="qs-result__value">{{ Guide::nl($result['kw']) }} kW</p>
                        <span class="qs-result__sub">{{ number_format($result['watts'], 0, ',', '.') }} W · {{ $result['situation_label'] }}</span>

                        <ol class="qs-calc">
                            @foreach ($result['steps'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    </div>

                    @if (! $valuesConfirmed)
                        <div class="qs-callout qs-callout--warn">
                            <strong>Vuistregels nog niet bevestigd</strong>
                            De vier waarden per m³ zijn door u aangeleverd maar nog niet bevestigd in de
                            <a class="admin-link" href="{{ route('admin.hvac.rules.section', 'koelvermogen') }}">Offerte-instellingen → Koelvermogen</a>.
                        </div>
                    @endif

                    <div class="qs-card">
                        <h2>Niet-bindende indicatie van de klasse</h2>
                        @if ($result['class_indication']['manual_review'])
                            <p>Dit vermogen ligt boven het klassenbereik van de instellingen. Een handmatige, technische beoordeling is vereist.</p>
                        @else
                            <p>
                                Een vermogen van {{ Guide::nl($result['kw']) }} kW valt in de capaciteitsklasse
                                <strong>{{ Guide::nl($result['class_indication']['class_kw']) }} kW</strong>.
                            </p>
                        @endif
                        <p class="qs-muted">
                            Dit is uitsluitend een indicatie{{ $classesConfirmed ? '' : ' — de klassentabel zelf is nog niet door u bevestigd' }}.
                            Er wordt geen toestel gekozen. Een toestelvoorstel ontstaat alleen via de gedetailleerde voorcalculatie
                            bij een airco-aanvraag, en wordt pas definitief nadat u het zelf controleert en goedkeurt.
                        </p>
                    </div>
                @endif

                <div class="qs-callout qs-callout--warn">
                    <strong>Waarvoor dient deze inschatting wel en niet?</strong>
                    <ul>
                        @foreach ($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                        <li>De gedetailleerde berekening bij een aanvraag blijft volledig bestaan en wordt hierdoor niet vervangen.</li>
                        <li>Deze pagina bewaart niets: het resultaat verdwijnt wanneer u de pagina verlaat.</li>
                    </ul>
                </div>
            @endif

            <p><a class="qs-back" href="{{ route('admin.hvac.rules.section', 'koelvermogen') }}">← Terug naar Koelvermogen</a></p>
        </div>
    </section>
@endsection
