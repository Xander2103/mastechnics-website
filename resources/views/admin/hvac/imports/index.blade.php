@extends('layouts.app')

@section('title', 'Admin | Productbestand importeren')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Productbestand importeren</h1>
            <p>Upload het productbestand van uw leverancier. Het systeem herkent de kolommen — u kiest alleen wat u wilt importeren.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            @if (session('success') === 'hvac_profile_saved')
                <div class="form-success">
                    Instellingen bewaard. Volgende bestanden van deze leverancier worden automatisch herkend.
                </div>
            @endif

            @if (session('success') === 'hvac_import_done')
                @php $result = session('import_result', []); @endphp
                <div class="form-success">
                    Import afgerond: {{ $result['created'] ?? 0 }} toegevoegd,
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

            {{-- Primary flow: guided supplier import --}}
            <div class="admin-detail-card">
                <h2>Leveranciersbestand importeren</h2>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    CSV en Excel worden ondersteund (max. {{ $hvacMaxUploadMb }} MB).
                    Er wordt niets bewaard vóór u op &ldquo;Importeren&rdquo; klikt.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.guided.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="hvac-form-grid">
                        <label>
                            <span>Bestand *</span>
                            <input type="file" name="file" accept=".csv,.txt,.xlsx" required>
                        </label>
                        <label>
                            <span>Leverancier (optioneel)</span>
                            <input type="text" name="supplier_name" maxlength="120"
                                   placeholder="bv. Van Marcke" value="{{ old('supplier_name') }}">
                        </label>
                    </div>
                    <div style="margin-top:1rem;">
                        <button type="submit" class="button button-primary">Bestand analyseren</button>
                    </div>
                </form>

                @error('guided_file')
                    <p class="field-error-text" style="margin-top:0.6rem;">{{ $message }}</p>
                @enderror
            </div>

            @if (session('success') === 'hvac_compat_import_done')
                @php $compatResult = session('compat_import_result', []); @endphp
                <div class="form-success" style="margin-top:1.5rem;">
                    Compatibiliteitsimport afgerond: {{ $compatResult['created'] ?? 0 }} aangemaakt,
                    {{ $compatResult['updated'] ?? 0 }} bijgewerkt,
                    {{ $compatResult['skipped'] ?? 0 }} overgeslagen.
                </div>
            @endif

            {{-- Compatibility: deliberately separate from product import --}}
            <div class="admin-detail-card" style="margin-top:1.5rem;">
                <h2>Compatibiliteit binnen- en buitenunits</h2>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    Gebruik dit alleen om aan te geven welke binnen- en buitenunits technisch met
                    elkaar gecombineerd mogen worden. Beide producten moeten al in de catalogus
                    bestaan. Gebruik het
                    <a class="admin-link" href="{{ route('admin.hvac.import.compat.template') }}">compatibiliteitssjabloon</a>.
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

            {{-- Advanced: template import + saved profiles --}}
            <details class="admin-detail-card" style="margin-top:1.5rem;">
                <summary style="cursor:pointer;font-weight:600;">Geavanceerd</summary>

                <div style="margin-top:1rem;">
                    <h3>Importeren met MAS-sjabloon</h3>
                    <p class="hvac-muted" style="margin-bottom:1rem;">
                        Voor bestanden die exact ons
                        <a class="admin-link" href="{{ route('admin.hvac.import.template') }}">CSV-sjabloon</a>
                        volgen. Voor gewone leveranciersbestanden gebruikt u beter de import hierboven.
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

                @if ($mappingProfiles->isNotEmpty())
                    <div style="margin-top:1.5rem;">
                        <h3>Bewaarde leveranciersinstellingen</h3>
                        <p class="hvac-muted" style="margin-bottom:0.8rem;">
                            Deze worden automatisch toegepast wanneer een bestand herkend wordt.
                        </p>
                        <div class="admin-table-wrapper">
                            <table class="admin-table" style="font-size:0.82rem;">
                                <thead>
                                    <tr><th>Naam</th><th>Leverancier</th><th>Kolommen</th><th>Status</th><th></th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($mappingProfiles as $profile)
                                        <tr>
                                            <td>{{ $profile->name }}</td>
                                            <td>{{ $profile->supplier_name }}</td>
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
                    </div>
                @endif
            </details>
        </div>
    </section>
@endsection
