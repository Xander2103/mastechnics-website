@extends('layouts.app')

@section('title', 'Admin | HVAC-leveranciers')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Leveranciers</h1>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.partials.catalog-tabs', ['activeTab' => 'suppliers'])

            @if (session('success'))
                <div class="form-success">Opgeslagen.</div>
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
                <form method="POST" action="{{ route('admin.hvac.suppliers.store') }}" class="hvac-form-grid" style="margin-bottom:1.25rem;">
                    @csrf
                    <label><span>Naam *</span><input type="text" name="name" required maxlength="150"></label>
                    <label><span>Code</span><input type="text" name="code" maxlength="50"></label>
                    <label><span>E-mail</span><input type="email" name="email" maxlength="255"></label>
                    <label><span>Telefoon</span><input type="text" name="phone" maxlength="50"></label>
                    <label><span>&nbsp;</span><button type="submit" class="button button-primary">+ Leverancier toevoegen</button></label>
                </form>

                @if ($suppliers->isEmpty())
                    <p class="admin-muted-text">Nog geen leveranciers.</p>
                @else
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Naam</th><th>Productlijsten</th><th>Producten</th><th>Laatste import</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($suppliers as $supplier)
                                    <tr class="{{ $supplier->is_active ? '' : 'hvac-inactive-row' }}">
                                        <td data-label="Naam">
                                            {{ $supplier->name }}
                                            @if ($supplier->code || $supplier->email || $supplier->phone)
                                                <div class="hvac-muted" style="font-size:0.8rem;">
                                                    {{ collect([$supplier->code, $supplier->email, $supplier->phone])->filter()->implode(' · ') }}
                                                </div>
                                            @endif
                                        </td>
                                        <td data-label="Productlijsten">{{ $supplier->active_catalogs_count }}</td>
                                        <td data-label="Producten">{{ $supplier->active_products_count }} actief ({{ $supplier->products_count }} totaal)</td>
                                        <td data-label="Laatste import">{{ $supplier->catalogs->max('imported_at')?->format('d/m/Y') ?? '—' }}</td>
                                        <td data-label="Status">{{ $supplier->is_active ? 'Actief' : 'Inactief' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.hvac.suppliers.toggle', $supplier) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="admin-link" style="border:0;background:none;cursor:pointer;">
                                                    {{ $supplier->is_active ? 'Deactiveren' : 'Activeren' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @if ($supplier->catalogs->isNotEmpty())
                                        <tr>
                                            <td colspan="6" style="padding:0.4rem 0.75rem 0.9rem;background:#f9fafb;">
                                                <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:0.3rem;font-size:0.86rem;">
                                                    @foreach ($supplier->catalogs as $catalog)
                                                        <li>
                                                            <a class="admin-link" href="{{ route('admin.hvac.catalogs.show', $catalog) }}">{{ $catalog->name }}</a>
                                                            — {{ number_format($catalog->product_count, 0, ',', '.') }} producten
                                                            · laatste import {{ $catalog->imported_at?->format('d/m/Y') ?? '—' }}
                                                            @if ($catalog->isArchived())
                                                                · <strong>gearchiveerd</strong>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
