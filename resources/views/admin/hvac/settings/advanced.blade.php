@extends('layouts.app')

@section('title', 'Admin | Offerte-instellingen — Geavanceerd')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Offerte-instellingen</span>
            <h1>Geavanceerde instellingen</h1>
            <p>
                De volledige technische lijst van alle rekenregels in versie {{ $ruleSet->version }}
                ({{ $ruleSet->name }}, in gebruik sinds {{ $ruleSet->effective_from?->format('d/m/Y') ?? '—' }}).
                Voor dagelijks gebruik volstaan de vijf onderdelen van de Offerte-instellingen; deze pagina is bedoeld
                voor overleg met de ontwikkelaar.
            </p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container qs-stack">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.settings.partials.subnav', ['current' => 'advanced', 'concept' => null])
            @include('admin.hvac.settings.partials.flash')

            <div class="qs-card">
                <p>
                    <strong>{{ $criticalDone }} van {{ $criticalTotal }}</strong> belangrijke regels bevestigd.
                    Zolang niet alle belangrijke regels bevestigd zijn, kunnen aanbevelingen met echte (niet-TEST) producten niet goedgekeurd worden.
                </p>
                <p class="qs-muted">
                    Technische status per regel: <em>Startwaarde</em> = waarde van de software die nog door Mastechnics gekozen moet worden;
                    <em>Te bevestigen</em> = bedrijfsregel die nog bevestigd moet worden; <em>Fabrikantgegevens</em> = algemene schatting,
                    de gegevens op het product gaan voor. Historische berekeningen bewaren altijd hun eigen instellingen en veranderen nooit.
                </p>
                <p><a class="qs-back" href="{{ route('admin.hvac.rules.index') }}">← Terug naar het overzicht</a></p>
            </div>

            @php
                $technicalStatus = [
                    'placeholder'        => 'Startwaarde',
                    'te_valideren'       => 'Te bevestigen',
                    'fabrikantspecifiek' => 'Fabrikantgegevens',
                    'validated'          => 'Bevestigd',
                ];
            @endphp

            @foreach ($entriesByCat as $category => $entries)
                <div class="qs-card">
                    <h2>{{ $category }}</h2>
                    <div class="qs-table-wrap">
                        <table class="qs-table qs-table--full">
                            <thead>
                                <tr>
                                    <th>Regel</th><th>Waarde</th><th>Eenheid</th><th>Uitleg</th>
                                    <th>Status</th><th>Bevestiging</th><th>Actie</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    <tr>
                                        <td>
                                            {{ $entry['label'] }}
                                            @if ($entry['critical'])
                                                <span class="qs-tag">Belangrijk</span>
                                            @endif
                                            <br><code class="qs-muted" style="font-size:0.72rem;">{{ $entry['key'] }}</code>
                                        </td>
                                        <td style="min-width:9rem;">{{ $entry['value'] }}</td>
                                        <td class="qs-muted">{{ $entry['unit'] }}</td>
                                        <td class="qs-muted" style="min-width:16rem;">{{ $entry['explanation'] }}</td>
                                        <td>
                                            <span class="qs-status qs-status--{{ $entry['friendly']['key'] }}">{{ $technicalStatus[$entry['status']] ?? $entry['status'] }}</span>
                                            @if ($entry['value_changed'])
                                                <br><span class="qs-muted">waarde gewijzigd</span>
                                            @endif
                                        </td>
                                        <td class="qs-muted" style="min-width:9rem;">
                                            @if ($entry['validation'] && $entry['status'] === 'validated')
                                                {{ $entry['validation']->validated_by }}<br>
                                                {{ $entry['validation']->validated_at->format('d/m/Y') }}
                                                @if ($entry['validation']->note)
                                                    <br>“{{ $entry['validation']->note }}”
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td style="min-width:12rem;">
                                            @if ($entry['status'] === 'validated')
                                                <form method="POST" action="{{ route('admin.hvac.rules.unvalidate') }}">
                                                    @csrf
                                                    <input type="hidden" name="rule_key" value="{{ $entry['key'] }}">
                                                    <button type="submit" class="qs-linkbutton">Bevestiging intrekken</button>
                                                </form>
                                            @else
                                                <details>
                                                    <summary style="cursor:pointer;font-size:0.85rem;font-weight:700;">Bevestigen</summary>
                                                    <form method="POST" action="{{ route('admin.hvac.rules.validate') }}" class="qs-confirm" style="margin-top:8px;">
                                                        @csrf
                                                        <input type="hidden" name="rule_key" value="{{ $entry['key'] }}">
                                                        <label class="qs-check" style="font-size:0.85rem;">
                                                            <input type="checkbox" name="confirm" value="1" required>
                                                            <span>Ik bevestig deze waarde.</span>
                                                        </label>
                                                        <input type="text" name="note" placeholder="Notitie (optioneel)" maxlength="1000"
                                                               aria-label="Notitie bij {{ $entry['label'] }}"
                                                               style="padding:8px 10px;border:1px solid #c3cedb;border-radius:8px;font:inherit;font-size:0.85rem;">
                                                        <button type="submit" class="button button-secondary" style="min-height:40px;font-size:0.85rem;">Bevestigen</button>
                                                    </form>
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
