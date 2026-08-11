@extends('layouts.app')

@section('title', 'Admin | Begeleide import — kolommen koppelen')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin — begeleide import</span>
            <h1>Stap 3 — Koppel de kolommen</h1>
            <p>{{ $state['original_name'] }}@if ($state['sheet'] && $state['sheet'] !== 'CSV') — werkblad "{{ $state['sheet'] }}"@endif — koprij {{ $state['header_row'] + 1 }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            <div class="admin-detail-card">
                <p class="hvac-muted" style="margin-bottom:0.6rem;">
                    Verplichte interne velden: <strong>{{ implode(', ', $required) }}</strong>.
                    Voorstellen met een ✓ zijn automatisch herkend; onzekere voorstellen staan als
                    hint en moeten handmatig bevestigd worden. Kolommen zonder koppeling worden genegeerd.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.guided.mapping', $token) }}">
                    @csrf
                    <div class="admin-table-wrapper">
                        <table class="admin-table" style="font-size:0.82rem;">
                            <thead>
                                <tr><th>Kolom in bestand</th><th>Voorbeeldwaarden</th><th>Intern veld</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($headerCells as $col => $header)
                                    <tr>
                                        <td>
                                            <strong>{{ $header !== null && trim((string) $header) !== '' ? $header : 'Kolom ' . ($col + 1) }}</strong>
                                            @if (($suggestions[$col]['confidence'] ?? 'none') === 'exact')
                                                <span style="color:#15803d;">✓ herkend</span>
                                            @elseif (($suggestions[$col]['confidence'] ?? 'none') === 'fuzzy')
                                                <br><span class="hvac-assumed">Lijkt op "{{ $suggestions[$col]['field'] }}" — bevestig handmatig</span>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach (array_filter($samples[$col] ?? [], fn ($v) => $v !== null && $v !== '') as $sample)
                                                <span style="display:block;">{{ \Illuminate\Support\Str::limit((string) $sample, 30) }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            <select name="mapping[{{ $col }}]">
                                                <option value="">— niet importeren —</option>
                                                @foreach ($fields as $field)
                                                    <option value="{{ $field }}" @selected(($preselected[$col] ?? null) === $field)>
                                                        {{ $field }}@if (in_array($field, $required, true)) *@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="hvac-form-grid" style="margin-top:1rem;">
                        <label>
                            <span>Decimaalformaat in het bestand</span>
                            <select name="decimal_format">
                                <option value="auto" @selected($state['decimal_format'] === 'auto')>Automatisch (komma of punt)</option>
                                <option value="comma" @selected($state['decimal_format'] === 'comma')>Komma als decimaal (1.234,56)</option>
                                <option value="point" @selected($state['decimal_format'] === 'point')>Punt als decimaal (1,234.56)</option>
                            </select>
                        </label>
                    </div>

                    <fieldset style="margin-top:1rem;border:1px solid #e5e7eb;border-radius:8px;padding:0.9rem;">
                        <legend style="font-size:0.85rem;">Bewaren als importprofiel (optioneel)</legend>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="checkbox" name="save_profile" value="1" @checked(old('save_profile'))>
                            <span>Deze koppeling bewaren zodat een volgend bestand van deze leverancier bijna automatisch kan</span>
                        </label>
                        <div class="hvac-form-grid" style="margin-top:0.6rem;">
                            <label><span>Profielnaam *</span><input type="text" name="profile_name" value="{{ old('profile_name') }}" placeholder="bv. Daikin leverancierslijst"></label>
                            <label><span>Leverancier *</span><input type="text" name="profile_supplier" value="{{ old('profile_supplier') }}" placeholder="bv. Daikin"></label>
                            <label><span>Werkbladpatroon (optioneel)</span><input type="text" name="profile_sheet_pattern" value="{{ old('profile_sheet_pattern') }}" placeholder="bv. prijslijst"></label>
                        </div>
                        <p class="hvac-assumed" style="margin:0.4rem 0 0;">Het profiel bewaart alleen de koppeling — nooit het bestand zelf.</p>
                    </fieldset>

                    <div style="margin-top:1.2rem;display:flex;gap:0.6rem;">
                        <button type="submit" class="button button-primary">Volgende: controle</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.hvac.import.guided.back', $token) }}" style="margin-top:0.8rem;">
                    @csrf
                    <input type="hidden" name="step" value="header">
                    <button type="submit" class="admin-link" style="background:none;border:none;cursor:pointer;padding:0;">← Terug naar koprij</button>
                </form>

                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>
@endsection
