@extends('layouts.app')

@section('title', 'Admin | HVAC-producten')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>HVAC-producten</h1>
            <p>Catalogus voor de airco-voorcalculatie. Producten worden nooit verwijderd, enkel gedeactiveerd.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            @if (session('success'))
                <div class="form-success">Opgeslagen.</div>
            @endif

            {{-- Datakwaliteit --}}
            <div class="admin-stats-row" style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
                @foreach ([
                    ['label' => 'Merken', 'value' => $quality['brands'], 'filter' => null],
                    ['label' => 'Leveranciers', 'value' => $quality['suppliers'], 'filter' => null],
                    ['label' => 'Actieve producten', 'value' => $quality['active_products'], 'filter' => null],
                    ['label' => 'Zonder prijs', 'value' => $quality['missing_price'], 'filter' => 'missing_price'],
                    ['label' => 'Zonder voorraad', 'value' => $quality['missing_stock'], 'filter' => 'missing_stock'],
                    ['label' => 'Zonder elektrische data', 'value' => $quality['missing_electrical'], 'filter' => 'missing_electrical'],
                    ['label' => 'Zonder leidinglimieten', 'value' => $quality['missing_pipe'], 'filter' => 'missing_pipe'],
                    ['label' => 'Zonder compatibiliteit', 'value' => $quality['missing_compat'], 'filter' => 'missing_compat'],
                    ['label' => 'Klaar voor aanbeveling', 'value' => $quality['ready'], 'filter' => 'ready'],
                    ['label' => 'Geblokkeerd', 'value' => $quality['blocked'], 'filter' => null],
                ] as $card)
                    @php
                        $cardInner = '<span style="display:block;font-size:1.3rem;font-weight:700;color:' . ($card['label'] === 'Geblokkeerd' && $card['value'] > 0 ? '#dc2626' : '#111827') . ';">' . $card['value'] . '</span><span style="display:block;font-size:0.72rem;color:#6b7280;">' . e($card['label']) . '</span>';
                    @endphp
                    @if ($card['filter'] !== null && $card['value'] > 0)
                        <a href="{{ route('admin.hvac.products.index', ['quality' => $card['filter']]) }}"
                           style="flex:1 1 120px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:0.7rem;text-align:center;text-decoration:none;">
                            {!! $cardInner !!}
                        </a>
                    @else
                        <div style="flex:1 1 120px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:0.7rem;text-align:center;">
                            {!! $cardInner !!}
                        </div>
                    @endif
                @endforeach
            </div>

            @if (request('quality'))
                <p class="hvac-muted" style="margin-bottom:0.75rem;">
                    Gefilterd op datakwaliteit: <strong>{{ request('quality') }}</strong> —
                    <a class="admin-link" href="{{ route('admin.hvac.products.index') }}">filter wissen</a>
                </p>
            @endif

            <div class="admin-detail-card">
                <form method="GET" action="{{ route('admin.hvac.products.index') }}" class="hvac-form-grid" style="margin-bottom: 1rem;">
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
                                <option value="{{ $brand->id }}" @selected((int) request('brand') === $brand->id)>{{ $brand->name }}</option>
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
                    <label>
                        <span>&nbsp;</span>
                        <button type="submit" class="button button-secondary">Filteren</button>
                    </label>
                </form>

                <div style="margin-bottom: 1rem;">
                    <a class="button button-primary" href="{{ route('admin.hvac.products.create') }}">+ Product toevoegen</a>
                </div>

                @if ($products->isEmpty())
                    <p class="admin-muted-text">
                        Geen producten gevonden. De catalogus blijft leeg tot echte leveranciersdata wordt
                        toegevoegd of geïmporteerd — er worden nooit fictieve producten aangemaakt.
                    </p>
                @else
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Model</th>
                                    <th>Merk</th>
                                    <th>Type</th>
                                    <th>Koelvermogen</th>
                                    <th>Verkoopprijs</th>
                                    <th>Voorraad</th>
                                    <th>Levertijd</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    <tr class="{{ $product->is_active ? '' : 'hvac-inactive-row' }}">
                                        <td>{{ $product->sku }}</td>
                                        <td>{{ $product->model }}</td>
                                        <td>{{ $product->brand?->name }}</td>
                                        <td><span class="hvac-muted">{{ $product->product_type }}</span></td>
                                        <td>{{ $product->cooling_capacity_kw !== null ? number_format($product->cooling_capacity_kw, 1, ',', '.') . ' kW' : '—' }}</td>
                                        <td>{{ $product->default_sale_price_excl_vat !== null ? '€ ' . number_format($product->default_sale_price_excl_vat, 2, ',', '.') : '—' }}</td>
                                        <td>{{ $product->stock_quantity ?? '—' }}</td>
                                        <td>{{ $product->lead_time_days !== null ? $product->lead_time_days . ' d' : '—' }}</td>
                                        <td>{{ $product->is_active ? 'Actief' : 'Inactief' }}</td>
                                        <td>
                                            <a class="admin-link" href="{{ route('admin.hvac.products.edit', $product) }}">Bewerken</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 1rem;">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
