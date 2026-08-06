@extends('layouts.app')

@section('title', 'Admin | HVAC-berekeningsregels')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>HVAC-berekeningsregels</h1>
            <p>
                Actieve regelset: <strong>{{ $ruleSet->name }} — versie {{ $ruleSet->version }}</strong>
                (actief sinds {{ $ruleSet->effective_from?->format('d/m/Y') ?? '—' }},
                laatst gewijzigd {{ $ruleSet->updated_at->format('d/m/Y H:i') }}).
                Historische berekeningen bewaren altijd hun eigen momentopname en veranderen nooit.
            </p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            @if (session('success') === 'hvac_rule_validated')
                <div class="form-success">Regel gemarkeerd als gevalideerd.</div>
            @elseif (session('success') === 'hvac_rule_unvalidated')
                <div class="form-success">Validatie verwijderd.</div>
            @elseif (session('success') === 'hvac_rule_draft_created')
                <div class="form-success">Conceptversie {{ session('draft_version') }} aangemaakt. Waarden aanpassen gebeurt in overleg met de ontwikkelaar; activeren kan hieronder.</div>
            @elseif (session('success') === 'hvac_rule_set_activated')
                <div class="form-success">Regelset geactiveerd. Alleen nieuwe berekeningen gebruiken deze versie.</div>
            @endif

            @if ($errors->any())
                <div class="form-error-list">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="admin-detail-card">
                <p style="font-size:0.9rem;">
                    <strong>{{ $criticalDone }} van {{ $criticalTotal }}</strong> kritieke regels gevalideerd.
                    Zolang niet alle kritieke regels gevalideerd zijn, kunnen aanbevelingen met échte
                    (niet-TEST) producten niet goedgekeurd worden.
                </p>

                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.5rem;">
                    <form method="POST" action="{{ route('admin.hvac.rules.draft') }}">
                        @csrf
                        <button type="submit" class="button button-secondary">Nieuwe conceptversie aanmaken</button>
                    </form>
                </div>

                @if ($drafts->isNotEmpty())
                    <div style="margin-top:1rem;">
                        <h3 style="font-size:0.95rem;">Conceptversies</h3>
                        @foreach ($drafts as $draft)
                            <form method="POST" action="{{ route('admin.hvac.rules.activate', $draft) }}"
                                  style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;margin-top:0.5rem;">
                                @csrf
                                <span>Versie {{ $draft->version }} (aangemaakt door {{ $draft->created_by }})</span>
                                <label style="display:flex;gap:0.35rem;align-items:center;font-size:0.85rem;">
                                    <input type="checkbox" name="confirm" value="1">
                                    Ik bevestig de activering
                                </label>
                                <button type="submit" class="button button-primary" style="font-size:0.82rem;padding:0.35rem 0.9rem;">
                                    Activeren
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>

            @foreach ($entriesByCat as $category => $entries)
                <div class="admin-detail-card" style="margin-top:1.25rem;">
                    <h2>{{ $category }}</h2>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Regel</th><th>Waarde</th><th>Eenheid</th><th>Uitleg</th>
                                    <th>Status</th><th>Validatie</th><th>Actie</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    <tr>
                                        <td>
                                            {{ $entry['label'] }}
                                            @if ($entry['critical'])
                                                <span style="color:#dc2626;font-size:0.72rem;font-weight:700;"> KRITIEK</span>
                                            @endif
                                        </td>
                                        <td>{{ $entry['value'] }}</td>
                                        <td class="hvac-muted">{{ $entry['unit'] }}</td>
                                        <td style="font-size:0.8rem;color:#6b7280;max-width:20rem;">{{ $entry['explanation'] }}</td>
                                        <td>
                                            <span style="font-size:0.78rem;padding:0.15rem 0.6rem;border-radius:999px;{{ $entry['status'] === 'validated' ? 'background:#dcfce7;color:#166534;' : 'background:#fffbeb;color:#92400e;' }}">
                                                {{ \App\Services\Hvac\HvacRuleCatalog::statusLabel($entry['status']) }}
                                            </span>
                                        </td>
                                        <td style="font-size:0.76rem;color:#6b7280;">
                                            @if ($entry['validation'])
                                                {{ $entry['validation']->validated_by }}<br>
                                                {{ $entry['validation']->validated_at->format('d/m/Y') }}
                                                @if ($entry['validation']->note)
                                                    <br>“{{ $entry['validation']->note }}”
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if ($entry['validation'])
                                                <form method="POST" action="{{ route('admin.hvac.rules.unvalidate') }}">
                                                    @csrf
                                                    <input type="hidden" name="rule_key" value="{{ $entry['key'] }}">
                                                    <button type="submit" class="admin-link" style="border:0;background:none;cursor:pointer;font-size:0.78rem;">
                                                        Validatie intrekken
                                                    </button>
                                                </form>
                                            @else
                                                <details>
                                                    <summary style="cursor:pointer;font-size:0.78rem;">Valideren</summary>
                                                    <form method="POST" action="{{ route('admin.hvac.rules.validate') }}"
                                                          style="display:flex;flex-direction:column;gap:0.35rem;margin-top:0.35rem;">
                                                        @csrf
                                                        <input type="hidden" name="rule_key" value="{{ $entry['key'] }}">
                                                        <input type="text" name="note" placeholder="Interne notitie (optioneel)" maxlength="1000"
                                                               style="padding:0.3rem 0.5rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.8rem;">
                                                        <button type="submit" class="button button-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">
                                                            Markeer als gevalideerd
                                                        </button>
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
