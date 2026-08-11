@extends('layouts.app')

@section('title', 'Admin | Import — klaar om te importeren')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Productbestand importeren</span>
            <h1>Klaar om te importeren</h1>
            <p>{{ $state['original_name'] }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.progress', ['current' => 4])
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            <div class="admin-detail-card" style="max-width:38rem;">
                <div style="display:flex;flex-direction:column;gap:0.35rem;margin-bottom:1.2rem;">
                    <div><strong>Leverancier:</strong> {{ $state['supplier_name'] !== '' ? $state['supplier_name'] : '—' }}</div>
                    <div><strong>Producten:</strong> {{ number_format($totalRows, 0, ',', '.') }}</div>
                    <div><strong>Nieuwe producten:</strong> {{ number_format($createCount, 0, ',', '.') }}</div>
                    <div><strong>Bijwerken:</strong> {{ number_format($updateCount, 0, ',', '.') }}</div>
                    <div><strong>Overgeslagen (fouten):</strong> {{ number_format($errorCount, 0, ',', '.') }}</div>
                    <div><strong>Met waarschuwing:</strong> {{ number_format($reviewCount, 0, ',', '.') }}</div>
                </div>

                <form method="POST" action="{{ route('admin.hvac.import.guided.confirm', $token) }}">
                    @csrf

                    <fieldset style="border:none;padding:0;margin:0 0 1rem 0;">
                        <legend style="font-weight:600;margin-bottom:0.4rem;">Nieuwe productlijst of bestaande lijst bijwerken?</legend>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="catalog_choice" value="new" checked
                                   onchange="document.getElementById('new-catalog').style.display='';document.getElementById('existing-catalog').style.display='none';">
                            <span>Nieuwe productlijst</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="catalog_choice" value="existing" @disabled($catalogs->isEmpty())
                                   onchange="document.getElementById('new-catalog').style.display='none';document.getElementById('existing-catalog').style.display='';">
                            <span>Bestaande productlijst bijwerken</span>
                        </label>
                    </fieldset>

                    <div id="new-catalog" style="margin-bottom:1rem;max-width:24rem;">
                        <label style="font-weight:600;">Naam van deze productlijst
                            <input type="text" name="catalog_name" value="{{ old('catalog_name', $suggestedName) }}"
                                   style="width:100%;margin-top:0.3rem;">
                        </label>
                    </div>

                    <div id="existing-catalog" style="display:none;margin-bottom:1rem;max-width:24rem;">
                        <label style="font-weight:600;">Welke lijst bijwerken?
                            <select name="catalog_id" style="width:100%;margin-top:0.3rem;">
                                @foreach ($catalogs as $catalog)
                                    <option value="{{ $catalog->id }}">{{ $catalog->name }} ({{ $catalog->product_count }} producten)</option>
                                @endforeach
                            </select>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:0.5rem;margin-top:0.6rem;">
                            <input type="checkbox" name="deactivate_missing" value="1" style="margin-top:0.2rem;">
                            <span>Producten die niet meer in dit bestand staan inactief zetten
                                <span class="hvac-muted">(alleen als ze in geen enkele andere actieve lijst zitten; er wordt nooit iets verwijderd)</span>
                            </span>
                        </label>
                    </div>

                    <fieldset style="border:none;padding:0;margin:0 0 1.2rem 0;">
                        <legend style="font-weight:600;margin-bottom:0.4rem;">Bestaande producten</legend>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="mode" value="create_and_update" checked>
                            <span>Ook bijwerken (aanbevolen)</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="mode" value="create_only">
                            <span>Enkel nieuwe producten toevoegen</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="mode" value="update_only">
                            <span>Enkel bestaande producten bijwerken</span>
                        </label>
                    </fieldset>

                    <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
                        <button type="submit" class="button button-primary">Importeren</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.hvac.import.guided.back', $token) }}" style="margin-top:0.8rem;">
                    @csrf
                    <input type="hidden" name="step" value="review">
                    <button type="submit" class="admin-link" style="background:none;border:none;cursor:pointer;padding:0;">
                        &larr; Terug
                    </button>
                </form>

                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>
@endsection
