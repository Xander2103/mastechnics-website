@extends('layouts.app')

@section('title', 'Admin | Offerte-instellingen — ' . $section['title'])

@php
    use App\Services\Hvac\HvacSettingsGuide as Guide;
@endphp

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Offerte-instellingen · onderdeel {{ $section['number'] }} van 5</span>
            <h1>{{ $section['title'] }}</h1>
            <p>{{ $section['intro'] }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container qs-stack">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.settings.partials.subnav', ['current' => $sectionKey, 'concept' => $concept])
            @include('admin.hvac.settings.partials.flash')

            @if ($concept)
                <div class="qs-concept-bar" role="note">
                    U bewerkt het concept (versie {{ $concept->version }}). Dit concept is niet in gebruik: nieuwe berekeningen rekenen nog met versie {{ $active->version }}.
                    <a class="admin-link" href="{{ route('admin.hvac.rules.index') }}#concept">Naar de samenvatting en activeren</a>
                </div>
            @else
                <div class="qs-card">
                    <div class="qs-setting__head">
                        <div>
                            <h2 style="margin-bottom:4px;">Status van dit onderdeel</h2>
                            <p class="qs-muted" style="margin:0;">
                                @if ($section['critical_total'] > 0)
                                    {{ $section['critical_done'] }} van {{ $section['critical_total'] }} belangrijke instellingen goedgekeurd.
                                @else
                                    Dit onderdeel bevat geen instellingen die het goedkeuren blokkeren. Bevestigen is hier een goede gewoonte, geen verplichting.
                                @endif
                            </p>
                        </div>
                        <span class="qs-status qs-status--{{ $section['status']['key'] }}">{{ $section['status']['label'] }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.hvac.rules.draft') }}" style="margin-top:14px;">
                        @csrf
                        <input type="hidden" name="section" value="{{ $sectionKey }}">
                        <p class="qs-muted">
                            Een waarde wijzigen doet u in een concept. De actieve instellingen en bestaande offertes veranderen daardoor niet.
                        </p>
                        <button type="submit" class="button button-secondary">
                            {{ $existingDraft ? 'Verder werken in het concept (versie ' . $existingDraft->version . ')' : 'Waarde wijzigen? Maak een concept' }}
                        </button>
                    </form>
                </div>
            @endif

            {{-- ── Section-specific context ─────────────────────────────────── --}}
            @if ($sectionKey === 'koelvermogen')
                <div class="qs-card">
                    <h2>Twee manieren om het koelvermogen te bepalen</h2>

                    <h3 style="margin-top:14px;">Snelle inschatting</h3>
                    <p>Volume van de ruimte × één vuistregel per m³. Handig voor een eerste idee aan de telefoon of bij een plaatsbezoek. Uitsluitend indicatief: er wordt geen toestel gekozen en geen offerte gemaakt.</p>
                    @if ($quickSituations !== [])
                        <div class="qs-table-wrap">
                            <table class="qs-table">
                                <thead><tr><th>Situatie van de ruimte</th><th class="qs-num">Vuistregel</th></tr></thead>
                                <tbody>
                                    @foreach ($quickSituations as $situation)
                                        <tr><td>{{ $situation['label'] }}</td><td class="qs-num">{{ Guide::nl($situation['w_per_m3']) }} W/m³</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p style="margin-top:12px;">
                            <a class="button button-secondary" style="margin-top:0;" href="{{ route('admin.hvac.rules.quick-estimate') }}">Snelle inschatting openen</a>
                        </p>
                    @else
                        <p><span class="qs-status qs-status--unavailable">Niet beschikbaar</span> Deze versie van de instellingen bevat geen vuistregels per m³.</p>
                    @endif

                    <h3 style="margin-top:20px;">Gedetailleerde berekening</h3>
                    <p>Gebruikt wat de klant in de aanvraag invult: afmetingen per kamer, isolatie van de woning, ligging, ramen en daktype. Dit is de methode achter de knop <strong>Voorcalculatie uitvoeren</strong> bij een airco-aanvraag, en de enige methode waarmee toestellen voorgesteld worden.</p>

                    <p>
                        <strong>Actieve rekenmethode:</strong>
                        @if ($loadMethod === 'engineering_v2')
                            uitgebreid model — warmte door muren en plafond, zon door het glas, personen, toestellen en ventilatie, met een veiligheidsmarge.
                        @else
                            berekening per m² — oppervlakte × vermogen per m² volgens isolatie, gecorrigeerd voor plafondhoogte, ligging en ramen, plus warmte van personen en toestellen.
                        @endif
                    </p>
                    @if ($loadMethod === 'simple_v1')
                        <p class="qs-muted">Een uitgebreider rekenmodel (op basis van het referentiewerkboek) kan door de ontwikkelaar als concept klaargezet worden. Het wordt nooit automatisch geactiveerd.</p>
                    @endif

                    <div class="qs-callout qs-callout--warn" style="margin-top:12px;">
                        <strong>Waar het systeem aannames maakt</strong>
                        <ul>
                            <li>Het formulier vraagt geen aantal personen of toestellen per kamer: er wordt gerekend met een aanname per kamertype, telkens gemeld als "(aanname)".</li>
                            <li>Voor kamers onder een plat dak of op zolder bestaat nog geen bevestigde correctie. U krijgt een waarschuwing en past het vermogen zo nodig zelf aan.</li>
                            @if ($loadMethod === 'engineering_v2')
                                <li>Zonwering, glasoppervlakte en luchtverversing worden niet bevraagd en zijn dus aannames.</li>
                            @endif
                            <li>Is de isolatie of ligging "onbekend", dan wordt de gemiddelde waarde genomen — met waarschuwing.</li>
                        </ul>
                    </div>

                    <p style="margin-top:12px;">
                        <a class="admin-link" href="{{ route('admin.hvac.rules.example') }}">Bekijk voorbeeld: van ruimte tot offerteprijs →</a>
                    </p>
                </div>
            @elseif ($sectionKey === 'toestellen' && $catalog)
                <div class="qs-card">
                    <h2>Capaciteitsklassen en uw catalogus</h2>
                    <p>
                        Per klasse ziet u hoeveel toestellen uit uw eigen catalogus vandaag in aanmerking kunnen komen.
                        Een toestel komt in aanmerking wanneer zijn koelvermogen de berekende koellast dekt en niet hoger is dan
                        de klasse × {{ Guide::nl($catalog['oversize_factor']) }}.
                    </p>

                    <div class="qs-table-wrap">
                        <table class="qs-table">
                            <thead>
                                <tr><th>Berekende koellast</th><th>Klasse</th><th>Toestel tot</th><th class="qs-num">Toestellen in catalogus</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($catalog['classes'] as $row)
                                    <tr>
                                        <td>{{ $row['load_from_kw'] > 0 ? 'boven ' . Guide::nl($row['load_from_kw']) . ' ' : '' }}tot {{ Guide::nl($row['load_to_kw']) }} kW</td>
                                        <td><strong>{{ Guide::nl($row['class_kw']) }} kW</strong></td>
                                        <td>{{ Guide::nl($row['max_capacity_kw']) }} kW</td>
                                        <td class="qs-num">
                                            @if ($row['products'] === 0)
                                                <span class="qs-status qs-status--unavailable">geen</span>
                                            @else
                                                {{ $row['products'] }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="qs-muted" style="margin-top:10px;">
                        Geteld: actieve binnenunits en single-split sets met een ingevuld koelvermogen, uit niet-gearchiveerde productlijsten.
                        @if ($catalog['manual_above_kw'])
                            Boven {{ Guide::nl($catalog['manual_above_kw']) }} kW stelt het systeem niets voor: handmatige keuze vereist.
                        @endif
                    </p>

                    @php
                        $gaps = array_filter([
                            'Zonder koelvermogen (onzichtbaar voor de selectie)' => $catalog['without_capacity'],
                            'Zonder prijs'                 => $catalog['quality']['missing_price'],
                            'Zonder leidinglimiet'         => $catalog['quality']['missing_pipe'],
                            'Zonder elektrische gegevens'  => $catalog['quality']['missing_electrical'],
                            'Zonder compatibiliteit'       => $catalog['quality']['missing_compat'],
                            'Na import nog te controleren' => $catalog['needs_review'],
                        ]);
                    @endphp
                    <h3 style="margin-top:18px;">Ontbrekende productgegevens</h3>
                    @if ($catalog['quality']['active_products'] === 0)
                        <p><span class="qs-status qs-status--unavailable">Niet beschikbaar</span> Er staan nog geen actieve producten in de catalogus.
                            <a class="admin-link" href="{{ route('admin.hvac.import.index') }}">Productbestand importeren</a></p>
                    @elseif ($gaps === [])
                        <p><span class="qs-status qs-status--approved">In orde</span> Geen ontbrekende gegevens gevonden bij de {{ $catalog['quality']['active_products'] }} actieve producten.</p>
                    @else
                        <ul>
                            @foreach ($gaps as $label => $count)
                                <li>{{ $label }}: <strong>{{ $count }}</strong></li>
                            @endforeach
                        </ul>
                        <p><a class="admin-link" href="{{ route('admin.hvac.products.index', ['view' => 'all']) }}">Producten bekijken en aanvullen</a></p>
                    @endif

                    <div class="qs-callout" style="margin-top:12px;">
                        <strong>Compatibiliteitscontroles</strong>
                        <ul>
                            <li>Een single-split set is één product: binnen- en buitenunit horen per definitie bij elkaar.</li>
                            <li>Een losse binnenunit wordt alleen voorgesteld met een buitenunit waarvoor u de compatibiliteit hebt ingevoerd. Het systeem raadt nooit een combinatie.</li>
                            <li>Onbekende compatibiliteit is géén bevestigde compatibiliteit: zo'n toestel krijgt de melding "handmatige controle vereist" en kan niet goedgekeurd worden.</li>
                            <li>Leidinglengte en hoogteverschil worden getoetst aan de limieten op het product. Ontbreekt een limiet, dan is handmatige controle vereist.</li>
                        </ul>
                    </div>

                    <div class="qs-callout qs-callout--warn" style="margin-top:12px;">
                        <strong>Wanneer kiest u zelf een toestel?</strong>
                        <ul>
                            <li>De koellast ligt boven het klassenbereik.</li>
                            <li>Er staat geen geschikt toestel in de catalogus voor de klasse.</li>
                            <li>Compatibiliteit of limieten ontbreken.</li>
                            <li>De ruimte is bijzonder (veel glas, veranda, praktijkruimte, keuken): beoordeel eerst zelf het vermogen.</li>
                        </ul>
                    </div>
                </div>
            @elseif ($sectionKey === 'installatie')
                <div class="qs-callout qs-callout--warn">
                    <strong>Wat wordt geschat en wat bevestigt u zelf?</strong>
                    Leidinglengte, bochten, hoogteverschil, muurmontage van de buitenunit en de condensafvoer zijn aannames: het formulier vraagt ze niet.
                    De hoeveelheden op de voorcalculatie volgen uit deze aannames. Bevestig ze aan de hand van de foto's of een plaatsbezoek en pas
                    hoeveelheden zo nodig aan via "Waarde aanpassen" in de voorcalculatie. Prijzen van materialen komen altijd uit uw catalogus.
                </div>
            @elseif ($sectionKey === 'werkuren')
                <div class="qs-callout">
                    <strong>Hoe komt het totaal aantal uren tot stand?</strong>
                    Basisinstallatie + extra binnenunits + extra leidingwerk boven 5 m + boringen per kamer + elektrische aansluiting,
                    en waar van toepassing een tweede technieker, een toeslag voor dak of zolder en een condensaatpomp.
                    Het totaal × uw uurtarief = de arbeidskost op de offerte. De verplaatsing staat als aparte regel (zie Verkoopprijzen).
                </div>
            @elseif ($sectionKey === 'verkoopprijzen')
                <div class="qs-card">
                    <h2>Aankoopprijs, verkoopprijs, opslag en marge</h2>
                    <dl class="qs-terms">
                        <div>
                            <dt>Aankoopprijs</dt>
                            <dd>Wat u de leverancier betaalt, excl. btw. <span class="qs-muted">Komt uit uw productcatalogus (import of handmatig).</span></dd>
                        </div>
                        <div>
                            <dt>Verkoopprijs</dt>
                            <dd>Wat de klant betaalt, excl. btw. <span class="qs-muted">Komt uit uw productcatalogus. Staat ze daar, dan wordt ze altijd gebruikt.</span></dd>
                        </div>
                        <div>
                            <dt>Opslag</dt>
                            <dd>Percentage bovenop de aankoopprijs. Alleen gebruikt als er géén verkoopprijs is. <span class="qs-muted">U stelt de twee opslagen hieronder in.</span></dd>
                        </div>
                        <div>
                            <dt>Marge</dt>
                            <dd>Verkoop min aankoop van toestellen en materialen, getoond in € en als % van het totaal excl. btw. <span class="qs-muted">Wordt berekend en getoond; u stelt ze niet in. Ontbreekt een aankoopprijs, dan toont het systeem geen marge.</span></dd>
                        </div>
                    </dl>
                    <div class="qs-callout qs-callout--warn" style="margin-top:12px;">
                        <strong>Een opslag is geen marge</strong>
                        Een opslag van 35% op € 1.000,00 geeft een verkoopprijs van € 1.350,00. De winst van € 350,00 is 25,9% van die verkoopprijs — niet 35%.
                        Arbeid en verplaatsing tellen niet mee in de marge: het marge­percentage op de voorcalculatie is de productmarge gedeeld door het volledige totaal excl. btw.
                    </div>
                    <p class="qs-muted" style="margin-top:10px;">
                        De klant ziet nooit aankoopprijzen of marges: die worden niet naar de offerte of de PDF gekopieerd.
                    </p>
                </div>
            @endif

            {{-- ── Settings per group ───────────────────────────────────────── --}}
            @forelse ($groups as $groupName => $groupEntries)
                @php
                    $important = $groupEntries->filter(fn ($e) => $e['critical'])->values();
                    $others = $groupEntries->reject(fn ($e) => $e['critical'])->values();
                    $collapseOthers = $important->isNotEmpty() && $others->isNotEmpty() && ! $concept;
                @endphp
                <div>
                    <h3 class="qs-group-title">{{ $groupName }}</h3>

                    @foreach ($important as $entry)
                        @include('admin.hvac.settings.partials.setting', ['entry' => $entry, 'ruleSet' => $ruleSet, 'concept' => $concept])
                    @endforeach

                    @if ($collapseOthers)
                        <details style="margin-top:14px;">
                            <summary class="qs-back" style="cursor:pointer;">Toon {{ $others->count() }} overige {{ $others->count() === 1 ? 'instelling' : 'instellingen' }} in deze groep</summary>
                            @foreach ($others as $entry)
                                @include('admin.hvac.settings.partials.setting', ['entry' => $entry, 'ruleSet' => $ruleSet, 'concept' => $concept])
                            @endforeach
                        </details>
                    @else
                        @foreach ($others as $entry)
                            @include('admin.hvac.settings.partials.setting', ['entry' => $entry, 'ruleSet' => $ruleSet, 'concept' => $concept])
                        @endforeach
                    @endif
                </div>
            @empty
                <div class="qs-card">
                    <p><span class="qs-status qs-status--unavailable">Niet beschikbaar</span> Dit onderdeel heeft geen instellingen in deze versie.</p>
                </div>
            @endforelse

            <p>
                <a class="qs-back" href="{{ route('admin.hvac.rules.index') }}">← Terug naar het overzicht</a>
            </p>
        </div>
    </section>
@endsection
