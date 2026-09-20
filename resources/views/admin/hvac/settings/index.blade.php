@extends('layouts.app')

@section('title', 'Admin | Offerte-instellingen')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Offerte-instellingen</h1>
            <p>
                Hier stelt u in hoe Mastechnics aanvragen analyseert en offertes voorbereidt.
                Controleer de instellingen stap voor stap voordat u automatische aanbevelingen gebruikt.
            </p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container qs-stack">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.settings.partials.flash')

            {{-- ── Where do we stand? ───────────────────────────────────────── --}}
            <div class="qs-card">
                <h2>Waar staat u nu?</h2>

                <div class="qs-tiles">
                    <div class="qs-tile">
                        <span class="qs-tile__label">Belangrijke instellingen goedgekeurd</span>
                        <span class="qs-tile__value">{{ $progress['critical_done'] }} van {{ $progress['critical_total'] }}</span>
                        <div class="qs-progress" role="progressbar"
                             aria-label="Goedgekeurde belangrijke instellingen"
                             aria-valuemin="0" aria-valuemax="{{ $progress['critical_total'] }}" aria-valuenow="{{ $progress['critical_done'] }}">
                            <span style="width: {{ $progress['critical_total'] > 0 ? round($progress['critical_done'] / $progress['critical_total'] * 100) : 0 }}%"></span>
                        </div>
                    </div>
                    <div class="qs-tile">
                        <span class="qs-tile__label">Nog te controleren</span>
                        <span class="qs-tile__value">{{ $progress['critical_open'] }}</span>
                    </div>
                    <div class="qs-tile">
                        <span class="qs-tile__label">Instellingen in gebruik</span>
                        <span class="qs-tile__value qs-tile__value--small">
                            Versie {{ $ruleSet->version }}<br>
                            <span class="qs-muted">sinds {{ $ruleSet->effective_from?->format('d/m/Y') ?? '—' }}</span>
                        </span>
                    </div>
                    <div class="qs-tile">
                        <span class="qs-tile__label">Automatische aanbevelingen</span>
                        <span class="qs-tile__value qs-tile__value--small">
                            <span class="qs-status qs-status--{{ $recommendationStatus['key'] }}">{{ $recommendationStatus['label'] }}</span>
                        </span>
                    </div>
                </div>

                <p class="qs-muted" style="margin-top:14px;">{{ $recommendationStatus['text'] }}</p>

                @php
                    $gaps = array_filter([
                        'zonder prijs'             => $catalog['quality']['missing_price'],
                        'zonder leidinglimiet'     => $catalog['quality']['missing_pipe'],
                        'zonder elektrische gegevens' => $catalog['quality']['missing_electrical'],
                        'zonder compatibiliteit'   => $catalog['quality']['missing_compat'],
                        'zonder koelvermogen'      => $catalog['without_capacity'],
                        'na import nog te controleren' => $catalog['needs_review'],
                    ]);
                @endphp
                @if ($catalog['quality']['active_products'] === 0)
                    <div class="qs-callout qs-callout--warn" style="margin-top:14px;">
                        <strong>Productcatalogus is leeg</strong>
                        Zonder uw eigen producten kan het systeem geen toestel voorstellen.
                        <a class="admin-link" href="{{ route('admin.hvac.import.index') }}">Productbestand importeren</a>
                    </div>
                @elseif ($gaps !== [])
                    <div class="qs-callout qs-callout--warn" style="margin-top:14px;">
                        <strong>Ontbrekende productgegevens</strong>
                        Van de {{ $catalog['quality']['active_products'] }} actieve producten:
                        @foreach ($gaps as $label => $count)
                            {{ $count }} {{ $label }}{{ $loop->last ? '.' : ',' }}
                        @endforeach
                        Producten met ontbrekende gegevens worden niet of alleen met een waarschuwing voorgesteld.
                        <a class="admin-link" href="{{ route('admin.hvac.products.index', ['view' => 'all']) }}">Producten bekijken</a>
                    </div>
                @endif
            </div>

            {{-- ── Next action ──────────────────────────────────────────────── --}}
            <div class="qs-next">
                <div>
                    <span class="qs-next__eyebrow">Volgende stap</span>
                    <p>{{ $nextAction['text'] }}</p>
                </div>
                <a class="button button-primary" href="{{ $nextAction['url'] }}">{{ $nextAction['label'] }}</a>
            </div>

            {{-- ── Five sections ────────────────────────────────────────────── --}}
            <div>
                <h2 style="margin-bottom:14px;">De vijf onderdelen</h2>
                <div class="qs-sections">
                    @foreach ($sections as $section)
                        <a class="qs-section-card" href="{{ route('admin.hvac.rules.section', $section['key']) }}">
                            <div class="qs-section-card__head">
                                <span class="qs-section-card__number" aria-hidden="true">{{ $section['number'] }}</span>
                                <h3 class="qs-section-card__title">{{ $section['title'] }}</h3>
                            </div>
                            <p class="qs-muted" style="margin:0;">{{ $section['summary'] }}</p>
                            <div class="qs-section-card__foot">
                                <span class="qs-status qs-status--{{ $section['status']['key'] }}">{{ $section['status']['label'] }}</span>
                                <span class="qs-muted">
                                    @if ($section['critical_total'] > 0)
                                        {{ $section['critical_done'] }} van {{ $section['critical_total'] }} belangrijke goedgekeurd
                                    @else
                                        {{ $section['confirmed'] }} van {{ $section['total'] }} bevestigd
                                    @endif
                                </span>
                            </div>
                            <span class="qs-section-card__more">Openen →</span>
                        </a>
                    @endforeach

                    <a class="qs-section-card" href="{{ route('admin.hvac.rules.example') }}">
                        <div class="qs-section-card__head">
                            <span class="qs-section-card__number" aria-hidden="true" style="background:#7c3aed;">?</span>
                            <h3 class="qs-section-card__title">Bekijk voorbeeld</h3>
                        </div>
                        <p class="qs-muted" style="margin:0;">Zie met een fictief voorbeeld wat uw instellingen doen — zonder een echte aanvraag of offerte te wijzigen.</p>
                        <span class="qs-section-card__more">Voorbeeld openen →</span>
                    </a>
                </div>
            </div>

            {{-- ── Concept and activation ───────────────────────────────────── --}}
            <div class="qs-card" id="concept">
                <h2>Instellingen wijzigen</h2>
                <p>
                    Wanneer u instellingen wijzigt, maakt u eerst een concept. Bestaande offertes veranderen hierdoor niet.
                    Nieuwe berekeningen gebruiken de nieuwe instellingen pas nadat u het concept hebt gecontroleerd en geactiveerd.
                </p>

                <ol class="qs-steps">
                    <li><strong>Huidige actieve instellingen</strong> — versie {{ $ruleSet->version }}. Hiermee rekent elke nieuwe voorcalculatie vandaag.</li>
                    <li><strong>Concept met wijzigingen</strong> — een kopie waarin u waarden aanpast. Een concept doet niets tot u het activeert.</li>
                    <li><strong>Nog te controleren wijzigingen</strong> — elke gewijzigde waarde bevestigt u opnieuw.</li>
                    <li><strong>Klaar om te activeren</strong> — u bekijkt de samenvatting en bevestigt uitdrukkelijk. Er wordt nooit iets automatisch geactiveerd.</li>
                </ol>

                @forelse ($drafts as $draft)
                    @php
                        $summary = $draft['summary'];
                        $draftSet = $draft['ruleSet'];
                    @endphp
                    <div class="qs-setting" style="margin-top:18px;">
                        <div class="qs-setting__head">
                            <h3 class="qs-setting__name">
                                Concept — versie {{ $draftSet->version }}
                                @if ($draftSet->name !== $ruleSet->name)
                                    <span class="qs-muted">({{ $draftSet->name }})</span>
                                @endif
                            </h3>
                            @if ($summary['ready'])
                                <span class="qs-status qs-status--approved">Klaar om te activeren</span>
                            @else
                                <span class="qs-status qs-status--review">Nog te controleren</span>
                            @endif
                        </div>
                        <p class="qs-muted">Aangemaakt door {{ $draftSet->created_by ?: 'onbekend' }} op {{ $draftSet->created_at->format('d/m/Y') }}.</p>

                        <h4 style="margin-top:12px;">Wat verandert er?</h4>
                        @if ($summary['changes'] === [] && $summary['other_changes'] === 0)
                            <p class="qs-muted">Nog geen wijzigingen: dit concept is gelijk aan de actieve instellingen.</p>
                        @else
                            @foreach ($summary['changes'] as $change)
                                <div class="qs-change">
                                    <strong>
                                        {{ $change['name'] }}
                                        @if ($change['critical']) <span class="qs-tag">Belangrijk</span> @endif
                                    </strong>
                                    <div class="qs-change__values">
                                        <span class="qs-change__old">{{ implode(' · ', $change['old_lines']) }}</span>
                                        <span aria-hidden="true">→</span>
                                        <span class="qs-change__new">{{ implode(' · ', $change['new_lines']) }}</span>
                                    </div>
                                    <span class="qs-muted">
                                        {{ $change['confirmed'] ? '✓ Nieuwe waarde bevestigd.' : 'Nieuwe waarde nog niet bevestigd.' }}
                                    </span>
                                </div>
                            @endforeach
                            @if ($summary['other_changes'] > 0)
                                <p class="qs-muted">
                                    Daarnaast {{ $summary['other_changes'] }} technische wijziging(en) die niet in de lijst met instellingen voorkomen
                                    (bijvoorbeeld een ander rekenmodel). Overleg met de ontwikkelaar wat dit inhoudt.
                                </p>
                            @endif
                        @endif

                        @if ($summary['open_confirmations']->isNotEmpty())
                            <div class="qs-callout qs-callout--warn" style="margin-top:12px;">
                                <strong>Nog {{ $summary['open_confirmations']->count() }} belangrijke instelling(en) niet bevestigd in dit concept</strong>
                                <ul>
                                    @foreach ($summary['open_confirmations'] as $open)
                                        <li>
                                            <a class="admin-link" href="{{ route('admin.hvac.rules.section', ['section' => $open['section'], 'concept' => $draftSet->id]) }}">{{ $open['guide']['name'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                                Gevolg als u toch activeert: voorcalculaties blijven werken, maar u kunt geen aanbeveling goedkeuren
                                of omzetten naar een offerte tot deze instellingen bevestigd zijn.
                            </div>
                        @endif

                        <div class="qs-callout" style="margin-top:12px;">
                            <strong>Gevolgen van activeren</strong>
                            <ul>
                                <li>Nieuwe voorcalculaties rekenen vanaf dan met versie {{ $draftSet->version }}.</li>
                                <li>Bestaande berekeningen en offertes bewaren hun eigen instellingen en veranderen niet.</li>
                                <li>Lopende, nog niet goedgekeurde opties berekent u opnieuw als u de nieuwe waarden wilt gebruiken.</li>
                                <li>Versie {{ $ruleSet->version }} wordt gearchiveerd, niet verwijderd.</li>
                            </ul>
                        </div>

                        <div class="qs-actions">
                            <a class="button button-secondary" href="{{ route('admin.hvac.rules.section', ['section' => 'verkoopprijzen', 'concept' => $draftSet->id]) }}">Waarden aanpassen in dit concept</a>

                            <form method="POST" action="{{ route('admin.hvac.rules.activate', $draftSet) }}" class="qs-confirm">
                                @csrf
                                <label class="qs-check" for="activate-{{ $draftSet->id }}">
                                    <input type="checkbox" name="confirm" value="1" id="activate-{{ $draftSet->id }}" required>
                                    <span>Ik heb de wijzigingen gecontroleerd en wil dat nieuwe berekeningen versie {{ $draftSet->version }} gebruiken.</span>
                                </label>
                                <button type="submit" class="button button-primary">Concept activeren</button>
                            </form>

                            <form method="POST" action="{{ route('admin.hvac.rules.discard', $draftSet) }}"
                                  onsubmit="return confirm('Dit concept annuleren? De actieve instellingen blijven ongewijzigd.');">
                                @csrf
                                <button type="submit" class="qs-linkbutton">Concept annuleren</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <form method="POST" action="{{ route('admin.hvac.rules.draft') }}" style="margin-top:16px;">
                        @csrf
                        <input type="hidden" name="section" value="verkoopprijzen">
                        <button type="submit" class="button button-secondary">Concept aanmaken om waarden te wijzigen</button>
                    </form>
                @endforelse
            </div>

            <p class="qs-muted">
                Zoekt u de volledige technische lijst met alle regels?
                <a class="admin-link" href="{{ route('admin.hvac.rules.advanced') }}">Geavanceerde instellingen</a>
                ·
                <a class="admin-link" href="{{ route('admin.hvac.rules.quick-estimate') }}">Snelle inschatting koelvermogen</a>
            </p>
        </div>
    </section>
@endsection
