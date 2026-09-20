@extends('layouts.app')

@section('title', 'Admin | Offerte-instellingen — Voorbeeld')

@php
    use App\Services\Hvac\HvacSettingsGuide as Guide;
    $quick = $example['quick'];
    $labor = $example['labor'];
    $pricing = $example['pricing'];
    $totals = $pricing['totals'];
@endphp

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Offerte-instellingen</span>
            <h1>Bekijk voorbeeld</h1>
            <p>Zie wat uw huidige instellingen doen, van ruimte tot offerteprijs — zonder dat er iets aan een echte aanvraag of offerte verandert.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container qs-stack">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.settings.partials.subnav', ['current' => 'example', 'concept' => null])

            <div class="qs-callout qs-callout--demo" role="note">
                <strong>Demonstratie — fictieve gegevens</strong>
                Het toestel, de materialen en hun aankoopprijzen hieronder zijn verzonnen voor dit voorbeeld. Het zijn geen producten uit uw catalogus
                en geen tarieven van Mastechnics. Alleen de instellingen (uurtarief, verplaatsing, opslagen, btw, uren) zijn de waarden
                die nu in gebruik zijn (versie {{ $ruleSet->version }}). Deze pagina bewaart niets en wijzigt geen aanvraag, berekening of offerte.
            </div>

            {{-- 1. Quick estimate --}}
            <div class="qs-card">
                <h2>1. Koelvermogen — snelle inschatting</h2>
                @if ($quick['ok'])
                    <p>Ruimte: 5 × 4 × 2,5 m · situatie: {{ mb_strtolower($quick['situation_label']) }}.</p>
                    <ol class="qs-calc">
                        @foreach ($quick['steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                    <p style="margin-top:12px;">
                        <strong>Indicatief vermogen: {{ Guide::nl($quick['kw']) }} kW.</strong>
                        @if (! $quick['class_indication']['manual_review'])
                            Niet-bindende indicatie van de klasse: {{ Guide::nl($quick['class_indication']['class_kw']) }} kW.
                        @endif
                    </p>
                @else
                    <p><span class="qs-status qs-status--unavailable">Niet beschikbaar</span> De snelle inschatting is niet beschikbaar in deze versie van de instellingen.</p>
                @endif
                <p class="qs-muted">Een inschatting is nog geen toestelkeuze. Daarna volgen altijd deze stappen:</p>
                <ol class="qs-steps">
                    @foreach ($example['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </div>

            {{-- 2. Labour --}}
            <div class="qs-card">
                <h2>2. Werkuren volgens uw instellingen</h2>
                <p>Voorbeeld: één binnenunit en één buitenunit, standaard leidingtraject, geen dak- of zoldersituatie.</p>
                <div class="qs-table-wrap">
                    <table class="qs-table">
                        <thead><tr><th>Onderdeel</th><th class="qs-num">Uren</th></tr></thead>
                        <tbody>
                            @foreach ($labor['lines'] as $line)
                                <tr><td>{{ $line['reason'] }}</td><td class="qs-num">{{ Guide::nl($line['hours']) }} u</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td>Totaal</td><td class="qs-num">{{ Guide::nl($labor['total_hours']) }} u</td></tr>
                        </tfoot>
                    </table>
                </div>
                <p class="qs-example" style="margin-top:12px;">
                    {{ Guide::nl($labor['total_hours']) }} uur × {{ Guide::euro($labor['hourly_rate']) }} = <strong>{{ Guide::euro($labor['total_cost']) }}</strong> arbeid, exclusief btw.
                    Verplaatsing: <strong>{{ Guide::euro($labor['travel_cost']) }}</strong>.
                </p>
            </div>

            {{-- 3. Price --}}
            <div class="qs-card">
                <h2>3. Offerteprijs volgens uw instellingen</h2>
                <p>
                    Het fictieve toestel en de fictieve materialen hebben alleen een aankoopprijs.
                    Daardoor ziet u hier hoe uw <strong>opslagen</strong> werken. Staat er in uw catalogus wél een verkoopprijs, dan wordt die gebruikt en speelt de opslag geen rol.
                </p>
                <div class="qs-table-wrap">
                    <table class="qs-table qs-table--wide">
                        <thead>
                            <tr><th>Regel (fictief)</th><th class="qs-num">Aantal</th><th class="qs-num">Aankoop</th><th class="qs-num">Verkoop</th><th class="qs-num">Totaal excl. btw</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($pricing['items'] as $item)
                                <tr>
                                    <td>{{ $item['description'] }}</td>
                                    <td class="qs-num">{{ Guide::nl($item['quantity']) }} {{ $item['unit'] }}</td>
                                    <td class="qs-num">{{ $item['purchase_unit_price'] !== null ? Guide::euro($item['purchase_unit_price']) : '—' }}</td>
                                    <td class="qs-num">{{ $item['sale_unit_price'] !== null ? Guide::euro($item['sale_unit_price']) : '—' }}</td>
                                    <td class="qs-num">{{ Guide::euro($item['line_total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td colspan="4">Subtotaal excl. btw</td><td class="qs-num">{{ Guide::euro($totals['subtotal_excl_vat']) }}</td></tr>
                            <tr><td colspan="4">Btw {{ Guide::nl($totals['vat_rate']) }}%</td><td class="qs-num">{{ Guide::euro($totals['vat_amount']) }}</td></tr>
                            <tr><td colspan="4">Totaal incl. btw</td><td class="qs-num">{{ Guide::euro($totals['total_incl_vat']) }}</td></tr>
                        </tfoot>
                    </table>
                </div>

                <div class="qs-callout qs-callout--warn" style="margin-top:12px;">
                    <strong>Opslag en marge in dit voorbeeld</strong>
                    Opslag op het toestel: {{ Guide::nl($example['markup_pct']) }}% bovenop de aankoopprijs. Dat komt overeen met
                    {{ Guide::nl($example['margin_on_sale_pct'], 1) }}% marge op de verkoopprijs van dat toestel.
                    @if ($totals['margin_amount'] !== null)
                        Productmarge van het hele voorbeeld: {{ Guide::euro($totals['margin_amount']) }}
                        = {{ Guide::nl($totals['margin_percentage'], 1) }}% van het subtotaal excl. btw (arbeid en verplaatsing tellen mee in het subtotaal, niet in de marge).
                    @endif
                </div>
            </div>

            <p>
                <a class="qs-back" href="{{ route('admin.hvac.rules.index') }}">← Terug naar het overzicht</a>
            </p>
        </div>
    </section>
@endsection
