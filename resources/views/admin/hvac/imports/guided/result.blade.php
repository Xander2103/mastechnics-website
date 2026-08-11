@extends('layouts.app')

@section('title', 'Admin | Import voltooid')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Productbestand importeren</span>
            <h1>Import voltooid</h1>
            <p>{{ $result['catalog_name'] }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.progress', ['current' => 5])

            <div class="admin-detail-card" style="max-width:38rem;">
                <div style="display:flex;flex-direction:column;gap:0.4rem;">
                    <div><span aria-hidden="true" style="color:#1a7f37;">&#10003;</span> {{ number_format($result['created'], 0, ',', '.') }} producten toegevoegd</div>
                    <div><span aria-hidden="true" style="color:#1a7f37;">&#10003;</span> {{ number_format($result['updated'], 0, ',', '.') }} producten bijgewerkt</div>
                    @if ($result['needs_review'] > 0)
                        <div><span aria-hidden="true" style="color:#9a6700;">!</span> {{ number_format($result['needs_review'], 0, ',', '.') }} producten vragen controle</div>
                    @endif
                    @if ($result['errors'] > 0)
                        <div><span aria-hidden="true">&mdash;</span> {{ number_format($result['errors'], 0, ',', '.') }} producten overgeslagen wegens fouten</div>
                    @endif
                    @if ($result['skipped'] > 0 && $result['errors'] === 0)
                        <div><span aria-hidden="true">&mdash;</span> {{ number_format($result['skipped'], 0, ',', '.') }} producten overgeslagen</div>
                    @endif
                    @if ($result['deactivated'] > 0)
                        <div><span aria-hidden="true">&mdash;</span> {{ number_format($result['deactivated'], 0, ',', '.') }} producten inactief gezet (stonden niet meer in het bestand)</div>
                    @elseif (($result['missing'] ?? 0) > 0)
                        <div><span aria-hidden="true">&mdash;</span> {{ number_format($result['missing'], 0, ',', '.') }} eerder geïmporteerde producten stonden niet in dit bestand (blijven ongewijzigd)</div>
                    @endif
                </div>

                <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:1.2rem;">
                    <a class="button button-primary" href="{{ route('admin.hvac.products.index') }}">Producten bekijken</a>
                    @if (! empty($result['error_token']))
                        <a class="button" href="{{ route('admin.hvac.import.errors', $result['error_token']) }}">Producten met problemen bekijken</a>
                    @endif
                    <a class="button" href="{{ route('admin.hvac.import.index') }}">Nieuwe import</a>
                </div>
            </div>

            @if ($canSaveProfile)
                <div class="admin-detail-card" style="max-width:38rem;margin-top:1.2rem;">
                    <h2>Deze instellingen onthouden voor volgende bestanden van deze leverancier?</h2>
                    <p class="hvac-muted" style="margin:0.4rem 0 0.8rem 0;">
                        Dan herkennen we het bestand de volgende keer automatisch en zijn deze stappen niet meer nodig.
                    </p>
                    <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
                        <form method="POST" action="{{ route('admin.hvac.import.guided.profile', $token) }}">
                            @csrf
                            <button type="submit" class="button button-primary">Ja, onthouden</button>
                        </form>
                        <form method="POST" action="{{ route('admin.hvac.import.guided.cancel', $token) }}">
                            @csrf
                            <button type="submit" class="button">Nee</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
