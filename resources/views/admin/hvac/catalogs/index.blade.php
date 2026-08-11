@extends('layouts.app')

@section('title', 'Admin | Productlijsten')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>HVAC-producten</h1>
            <p>Per geïmporteerde productlijst. Producten worden nooit verwijderd, enkel gedeactiveerd.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.partials.catalog-tabs', ['activeTab' => 'lists'])

            @if (session('success'))
                <div class="form-success">Opgeslagen.</div>
            @endif

            <div style="margin-bottom:1.2rem;">
                <a class="button button-primary" href="{{ route('admin.hvac.import.index') }}">Nieuwe lijst importeren</a>
            </div>

            @if ($catalogs->isEmpty())
                <div class="admin-detail-card">
                    <p>Er zijn nog geen productlijsten.
                        <a class="admin-link" href="{{ route('admin.hvac.import.index') }}">Importeer het eerste leveranciersbestand.</a>
                    </p>
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(20rem,100%),1fr));gap:1rem;">
                @foreach ($catalogs as $catalog)
                    <div class="admin-detail-card" style="{{ $catalog->isArchived() ? 'opacity:0.6;' : '' }}">
                        <h2 style="font-size:1.05rem;margin-bottom:0.3rem;">{{ $catalog->name }}</h2>
                        <div class="hvac-muted" style="display:flex;flex-direction:column;gap:0.15rem;font-size:0.86rem;">
                            <span>Leverancier: {{ $catalog->supplier?->name ?? '—' }}</span>
                            <span>{{ number_format($catalog->product_count, 0, ',', '.') }} producten
                                ({{ number_format($catalog->active_products_count, 0, ',', '.') }} actief@if ($catalog->review_products_count > 0), {{ number_format($catalog->review_products_count, 0, ',', '.') }} controleren@endif)</span>
                            <span>Laatste import: {{ $catalog->imported_at?->format('d/m/Y') ?? '—' }}</span>
                            @if ($catalog->isArchived())
                                <span><strong>Gearchiveerd</strong></span>
                            @endif
                        </div>
                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.8rem;">
                            <a class="button button-primary" href="{{ route('admin.hvac.catalogs.show', $catalog) }}">Openen</a>
                            <form method="POST" action="{{ route('admin.hvac.catalogs.archive', $catalog) }}"
                                  onsubmit="return confirm('{{ $catalog->isArchived() ? 'Deze lijst weer activeren?' : 'Deze lijst archiveren? Producten blijven bestaan.' }}');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="button">{{ $catalog->isArchived() ? 'Activeren' : 'Archiveren' }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
