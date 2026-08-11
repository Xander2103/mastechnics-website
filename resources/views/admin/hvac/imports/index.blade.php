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

            @if (request()->boolean('upload_too_large'))
                <div class="form-error-list">
                    <ul>
                        <li>
                            Het bestand is groter dan wat de server aanvaardt en werd geweigerd
                            vóór de verwerking kon starten. Verklein het bestand (bv. splits het
                            in delen) of vraag de beheerder om de serverlimiet te verhogen.
                        </li>
                    </ul>
                </div>
            @endif

            @php $hvacMaxUploadMb = (int) config('hvac.import.max_upload_mb', 25); @endphp

            <div class="admin-detail-card">
                <h2>Stap 1 — Bestand uploaden</h2>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    Gebruik het <a class="admin-link" href="{{ route('admin.hvac.import.template') }}">CSV-sjabloon</a>
                    (UTF-8, puntkomma of komma als scheidingsteken). Decimalen mogen met komma of punt.
                    Maximale bestandsgrootte: {{ $hvacMaxUploadMb }} MB.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="hvac-form-grid">
                        <label>
                            <span>Bestand (.csv, .txt of .xlsx) *</span>
                            <input type="file" name="file" accept=".csv,.txt,.xlsx" required>
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

            <div class="admin-detail-card" style="margin-top: 1.5rem;">
                <h2>Begeleide import (CSV of Excel, vrije indeling)</h2>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    Voor leveranciersbestanden die niet ons sjabloon volgen: kies het werkblad,
                    duid de koprij aan en koppel de kolommen aan onze velden. Niets wordt
                    weggeschreven vóór de bevestigingsstap. Eenmaal gekoppeld kunt u de indeling
                    bewaren als profiel per leverancier. Maximale bestandsgrootte: {{ $hvacMaxUploadMb }} MB.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.guided.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="hvac-form-grid">
                        <label>
                            <span>Bestand (.csv, .txt of .xlsx) *</span>
                            <input type="file" name="file" accept=".csv,.txt,.xlsx" required>
                        </label>
                        <label>
                            <span>Importmodus *</span>
                            <select name="mode" required>
                                <option value="create_and_update">Aanmaken én bijwerken</option>
                                <option value="create_only">Enkel nieuwe producten aanmaken</option>
                                <option value="update_only">Enkel bestaande producten bijwerken</option>
                            </select>
                        </label>
                        <label>
                            <span>Importprofiel (optioneel)</span>
                            <select name="profile_id">
                                <option value="">— zonder profiel (handmatig koppelen) —</option>
                                @foreach ($mappingProfiles->where('is_active', true) as $profile)
                                    <option value="{{ $profile->id }}">{{ $profile->name }} ({{ $profile->supplier_name }})</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div style="margin-top:1rem;">
                        <button type="submit" class="button button-primary">Begeleide import starten</button>
                    </div>
                </form>

                @error('guided_file')
                    <p class="field-error-text" style="margin-top:0.6rem;">{{ $message }}</p>
                @enderror

                @if ($mappingProfiles->isNotEmpty())
                    <h3 style="margin-top:1.2rem;">Bewaarde profielen</h3>
                    <div class="admin-table-wrapper">
                        <table class="admin-table" style="font-size:0.82rem;">
                            <thead>
                                <tr><th>Profiel</th><th>Leverancier</th><th>Koprij</th><th>Kolommen</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($mappingProfiles as $profile)
                                    <tr>
                                        <td>{{ $profile->name }}</td>
                                        <td>{{ $profile->supplier_name }}</td>
                                        <td>{{ $profile->header_row }}</td>
                                        <td>{{ count($profile->column_map ?? []) }}</td>
                                        <td>{{ $profile->is_active ? 'actief' : 'inactief' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.hvac.import.profiles.toggle', $profile) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="admin-link" style="background:none;border:none;cursor:pointer;padding:0;">
                                                    {{ $profile->is_active ? 'Deactiveren' : 'Activeren' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if (session('success') === 'hvac_compat_import_done')
                @php $compatResult = session('compat_import_result', []); @endphp
                <div class="form-success">
                    Compatibiliteitsimport afgerond: {{ $compatResult['created'] ?? 0 }} aangemaakt,
                    {{ $compatResult['updated'] ?? 0 }} bijgewerkt,
                    {{ $compatResult['skipped'] ?? 0 }} overgeslagen.
                </div>
            @endif

            <div class="admin-detail-card" style="margin-top: 1.5rem;">
                <h2>Compatibiliteit importeren (CSV)</h2>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    Koppel fabrikantcompatibiliteit via SKU's. Beide producten moeten al in de catalogus
                    bestaan — importeer dus eerst de producten. Gebruik het
                    <a class="admin-link" href="{{ route('admin.hvac.import.compat.template') }}">compatibiliteitssjabloon</a>.
                    Maximale bestandsgrootte: {{ $hvacMaxUploadMb }} MB.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.compat.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="hvac-form-grid">
                        <label>
                            <span>Bestand (.csv, .txt of .xlsx) *</span>
                            <input type="file" name="file" accept=".csv,.txt,.xlsx" required>
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
