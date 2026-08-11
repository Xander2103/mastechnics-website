@extends('layouts.app')

@section('title', 'Admin | ' . $catalog->name)

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Productlijst</span>
            <h1>{{ $catalog->name }}</h1>
            <p>
                Leverancier: {{ $catalog->supplier?->name ?? '—' }} ·
                {{ number_format($catalog->product_count, 0, ',', '.') }} producten ·
                Geïmporteerd: {{ $catalog->imported_at?->format('d/m/Y') ?? '—' }}
                @if ($catalog->isArchived()) · <strong>Gearchiveerd</strong> @endif
            </p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            <p style="margin-bottom:1rem;">
                <a class="admin-link" href="{{ route('admin.hvac.products.index', ['view' => 'lists']) }}">&larr; Alle productlijsten</a>
            </p>

            @if (session('success'))
                <div class="form-success">Opgeslagen.</div>
            @endif
            @if ($errors->any())
                <div class="form-error-list">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(11rem,100%),1fr));gap:0.8rem;margin-bottom:1.2rem;">
                <div class="admin-detail-card" style="text-align:center;">
                    <strong style="font-size:1.3rem;">{{ number_format($catalog->product_count, 0, ',', '.') }}</strong><br>producten
                </div>
                <div class="admin-detail-card" style="text-align:center;">
                    <strong style="font-size:1.3rem;color:#1a7f37;">{{ number_format($activeCount, 0, ',', '.') }}</strong><br>actief
                </div>
                <div class="admin-detail-card" style="text-align:center;">
                    <strong style="font-size:1.3rem;color:#9a6700;">{{ number_format($reviewCount, 0, ',', '.') }}</strong><br>
                    <a class="admin-link" href="{{ route('admin.hvac.catalogs.show', [$catalog, 'needs_review' => 1]) }}">controleren</a>
                </div>
            </div>

            {{-- Beheer --}}
            <details class="admin-detail-card" style="margin-bottom:1.2rem;">
                <summary style="cursor:pointer;font-weight:600;">Lijst beheren</summary>
                <div style="display:flex;gap:1.2rem;flex-wrap:wrap;margin-top:0.8rem;align-items:flex-end;">
                    <form method="POST" action="{{ route('admin.hvac.catalogs.rename', $catalog) }}" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;">
                        @csrf
                        @method('PATCH')
                        <label>Naam
                            <input type="text" name="name" value="{{ $catalog->name }}" maxlength="150" style="display:block;margin-top:0.3rem;min-width:16rem;">
                        </label>
                        <button type="submit" class="button">Hernoemen</button>
                    </form>
                    <form method="POST" action="{{ route('admin.hvac.catalogs.archive', $catalog) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="button">{{ $catalog->isArchived() ? 'Weer activeren' : 'Archiveren' }}</button>
                    </form>
                    <a class="button" href="{{ route('admin.hvac.import.index') }}">Bijwerken met nieuw bestand</a>
                </div>
                <p class="hvac-muted" style="margin-top:0.6rem;font-size:0.85rem;">
                    Archiveren verbergt de lijst uit het gewone overzicht. Producten en historiek blijven altijd bestaan.
                </p>
            </details>

            {{-- Import history --}}
            @if ($catalog->runs->isNotEmpty())
                <div class="admin-detail-card" style="margin-bottom:1.2rem;">
                    <h2>Importgeschiedenis</h2>
                    <div style="display:flex;flex-direction:column;gap:0.4rem;margin-top:0.6rem;">
                        @foreach ($catalog->runs as $run)
                            <div style="display:flex;gap:0.8rem;flex-wrap:wrap;font-size:0.88rem;">
                                <strong>{{ $run->created_at->format('d/m/Y H:i') }}</strong>
                                <span>{{ $run->created_count }} toegevoegd</span>
                                <span>{{ $run->updated_count }} bijgewerkt</span>
                                @if ($run->deactivated_count > 0)<span>{{ $run->deactivated_count }} inactief gezet</span>@endif
                                @if ($run->warning_count > 0)<span style="color:#9a6700;">{{ $run->warning_count }} waarschuwingen</span>@endif
                                @if ($run->skipped_count > 0)<span>{{ $run->skipped_count }} overgeslagen</span>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Products in this catalog --}}
            <div class="admin-detail-card">
                <form method="GET" action="{{ route('admin.hvac.catalogs.show', $catalog) }}" class="hvac-form-grid" style="margin-bottom:1rem;">
                    <label>
                        <span>Zoeken</span>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="SKU, model of naam">
                    </label>
                    <label>
                        <span>Type</span>
                        <select name="type">
                            <option value="">Alle types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Merk</span>
                        <select name="brand">
                            <option value="">Alle merken</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected(request('brand') == $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="active">
                            <option value="">Alle</option>
                            <option value="1" @selected(request('active') === '1')>Actief</option>
                            <option value="0" @selected(request('active') === '0')>Inactief</option>
                        </select>
                    </label>
                    <label style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="checkbox" name="missing_price" value="1" @checked(request()->boolean('missing_price'))>
                        <span>Zonder prijs</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="checkbox" name="needs_review" value="1" @checked(request()->boolean('needs_review'))>
                        <span>Te controleren</span>
                    </label>
                    <div style="align-self:end;">
                        <button type="submit" class="button">Filteren</button>
                    </div>
                </form>

                <div class="admin-table-wrapper">
                    <table class="admin-table" style="font-size:0.84rem;">
                        <thead>
                            <tr><th>SKU</th><th>Model</th><th>Merk</th><th>Type</th><th>Koelvermogen</th><th>Verkoopprijs</th><th>Voorraad</th><th>Levertijd</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td><a class="admin-link" href="{{ route('admin.hvac.products.edit', $product) }}">{{ $product->sku }}</a></td>
                                    <td>{{ $product->model }}</td>
                                    <td>{{ $product->brand?->name }}</td>
                                    <td>{{ $product->product_type }}</td>
                                    <td>{{ $product->cooling_capacity_kw !== null ? number_format($product->cooling_capacity_kw, 1, ',', '.') . ' kW' : '—' }}</td>
                                    <td>{{ $product->default_sale_price_excl_vat !== null ? '€ ' . number_format($product->default_sale_price_excl_vat, 2, ',', '.') : '—' }}</td>
                                    <td>{{ $product->stock_quantity ?? '—' }}</td>
                                    <td>{{ $product->lead_time_days !== null ? $product->lead_time_days . ' d' : '—' }}</td>
                                    <td>
                                        {{ $product->is_active ? 'actief' : 'inactief' }}
                                        @if ($product->metadata['import']['needs_review'] ?? false)
                                            · <span style="color:#9a6700;">controleren</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9">Geen producten gevonden met deze filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:1rem;">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </section>
@endsection
