@extends('layouts.app')

@section('title', 'Admin | HVAC-import')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Productimport (CSV)</h1>
            <p>Producten worden herkend op leverancier + SKU. Niets wordt weggeschreven vóór de bevestigingsstap.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            @if (session('success') === 'hvac_import_done')
                @php $result = session('import_result', []); @endphp
                <div class="form-success">
                    Import afgerond: {{ $result['created'] ?? 0 }} aangemaakt,
                    {{ $result['updated'] ?? 0 }} bijgewerkt,
                    {{ $result['skipped'] ?? 0 }} overgeslagen,
                    {{ $result['errors'] ?? 0 }} rijen met fouten.
                    @if (session('import_error_token'))
                        <a class="admin-link" href="{{ route('admin.hvac.import.errors', session('import_error_token')) }}">
                            Foutenrapport downloaden
                        </a>
                    @endif
                </div>
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
                <h2>Stap 1 — Bestand uploaden</h2>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    Gebruik het <a class="admin-link" href="{{ route('admin.hvac.import.template') }}">CSV-sjabloon</a>
                    (UTF-8, puntkomma of komma als scheidingsteken). Decimalen mogen met komma of punt.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="hvac-form-grid">
                        <label>
                            <span>CSV-bestand *</span>
                            <input type="file" name="file" accept=".csv,.txt" required>
                        </label>
                        <label>
                            <span>Importmodus *</span>
                            <select name="mode" required>
                                <option value="create_and_update">Aanmaken én bijwerken</option>
                                <option value="create_only">Enkel nieuwe producten aanmaken</option>
                                <option value="update_only">Enkel bestaande producten bijwerken</option>
                            </select>
                        </label>
                    </div>
                    <div style="margin-top:1rem;">
                        <button type="submit" class="button button-primary">Voorbeeld bekijken</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
