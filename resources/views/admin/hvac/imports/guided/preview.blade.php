@extends('layouts.app')

@section('title', 'Admin | Begeleide import — controle')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin — begeleide import</span>
            <h1>Stap 4 — Controleer en bevestig</h1>
            <p>{{ $state['original_name'] }}@if ($state['sheet'] && $state['sheet'] !== 'CSV') — werkblad "{{ $state['sheet'] }}"@endif</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            @if ($profile)
                <div class="form-success">
                    Profiel "{{ $profile->name }}" ({{ $profile->supplier_name }}) is toegepast.
                    Controleer de voorbeeldrijen hieronder vóór u bevestigt.
                </div>
            @endif

            <div class="admin-detail-card">
                <h2>Koppeling</h2>
                <div class="admin-table-wrapper">
                    <table class="admin-table" style="font-size:0.82rem;">
                        <thead><tr><th>Kolom in bestand</th><th>Intern veld</th></tr></thead>
                        <tbody>
                            @foreach ($columnLabels as $source => $field)
                                <tr><td>{{ $source }}</td><td>{{ $field }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h2 style="margin-top:1.2rem;">Resultaat van de controle</h2>
                <p style="font-size:0.9rem;">
                    {{ $totalRows }} rijen gelezen —
                    <strong>{{ $createCount }}</strong> nieuw,
                    <strong>{{ $updateCount }}</strong> bij te werken,
                    <strong>{{ $errorCount }}</strong> met fouten (worden overgeslagen).
                    @if ($truncated)
                        <br><span class="hvac-assumed">Het bestand bevat meer rijen dan het maximum; alleen de eerste rijen zijn gelezen.</span>
                    @endif
                </p>

                <h3>Eerste {{ $rows->count() }} genormaliseerde rijen</h3>
                <div class="admin-table-wrapper">
                    <table class="admin-table" style="font-size:0.78rem;">
                        <thead>
                            <tr>
                                <th>Rij</th><th>Actie</th><th>Leverancier</th><th>Merk</th><th>SKU</th>
                                <th>Model</th><th>Naam</th><th>Type</th><th>Koelverm. kW</th><th>Netto prijs</th><th>Fouten</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['line'] }}</td>
                                    <td>{{ $row['errors'] !== [] ? '—' : ($row['action'] === 'update' ? 'bijwerken' : 'nieuw') }}</td>
                                    <td>{{ $row['data']['supplier'] ?? '' }}</td>
                                    <td>{{ $row['data']['brand'] ?? '' }}</td>
                                    <td>{{ $row['data']['sku'] ?? '' }}</td>
                                    <td>{{ $row['data']['model'] ?? '' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit((string) ($row['data']['name'] ?? ''), 30) }}</td>
                                    <td>{{ $row['data']['product_type'] ?? '' }}</td>
                                    <td>{{ $row['data']['cooling_capacity_kw'] ?? '' }}</td>
                                    <td>{{ $row['data']['purchase_price_excl_vat'] ?? '' }}</td>
                                    <td style="color:#b91c1c;">{{ implode(' | ', $row['errors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('admin.hvac.import.guided.confirm', $token) }}" style="margin-top:1.2rem;">
                    @csrf
                    <button type="submit" class="button button-primary">
                        Import bevestigen ({{ $createCount + $updateCount }} rijen wegschrijven)
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.hvac.import.guided.back', $token) }}" style="margin-top:0.8rem;">
                    @csrf
                    <input type="hidden" name="step" value="mapping">
                    <button type="submit" class="admin-link" style="background:none;border:none;cursor:pointer;padding:0;">← Terug naar koppeling</button>
                </form>

                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>
@endsection
