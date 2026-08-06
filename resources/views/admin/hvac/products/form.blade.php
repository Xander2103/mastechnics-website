@extends('layouts.app')

@section('title', 'Admin | HVAC-product')

@section('content')
    @php $isEdit = $product->exists; @endphp

    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>{{ $isEdit ? 'Product bewerken' : 'Product toevoegen' }}</h1>
            @if ($isEdit)
                <p>{{ $product->brand?->name }} {{ $product->model }} — {{ $product->sku }}</p>
            @endif
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            @if (session('success'))
                <div class="form-success">Opgeslagen.</div>
            @endif

            @if ($errors->any())
                <div class="form-error-list">
                    <strong>Controleer de invoer.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="admin-detail-card">
                <form method="POST"
                      action="{{ $isEdit ? route('admin.hvac.products.update', $product) : route('admin.hvac.products.store') }}">
                    @csrf
                    @if ($isEdit)
                        @method('PATCH')
                    @endif

                    <div class="hvac-form-grid">
                        <label>
                            <span>Merk *</span>
                            <select name="hvac_brand_id" required>
                                <option value="">Kies een merk</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected((int) old('hvac_brand_id', $product->hvac_brand_id) === $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Leverancier</span>
                            <select name="hvac_supplier_id">
                                <option value="">Geen leverancier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((int) old('hvac_supplier_id', $product->hvac_supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>SKU *</span>
                            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" required maxlength="100">
                        </label>
                        <label>
                            <span>Model *</span>
                            <input type="text" name="model" value="{{ old('model', $product->model) }}" required maxlength="255">
                        </label>
                        <label>
                            <span>Naam *</span>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}" required maxlength="255">
                        </label>
                        <label>
                            <span>Producttype *</span>
                            <select name="product_type" required>
                                <option value="">Kies een type</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(old('product_type', $product->product_type) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="hvac-form-section">
                        <h3>Capaciteit</h3>
                        <div class="hvac-form-grid">
                            <label><span>Koelvermogen (kW)</span><input type="number" step="0.01" min="0" name="cooling_capacity_kw" value="{{ old('cooling_capacity_kw', $product->cooling_capacity_kw) }}"></label>
                            <label><span>Verwarmingsvermogen (kW)</span><input type="number" step="0.01" min="0" name="heating_capacity_kw" value="{{ old('heating_capacity_kw', $product->heating_capacity_kw) }}"></label>
                            <label><span>Min. aansluitbare capaciteit (kW)</span><input type="number" step="0.01" min="0" name="minimum_capacity_kw" value="{{ old('minimum_capacity_kw', $product->minimum_capacity_kw) }}"></label>
                            <label><span>Max. aansluitbare capaciteit (kW)</span><input type="number" step="0.01" min="0" name="maximum_capacity_kw" value="{{ old('maximum_capacity_kw', $product->maximum_capacity_kw) }}"></label>
                            <label><span>Max. aantal binnenunits</span><input type="number" min="1" max="20" name="maximum_connected_indoor_units" value="{{ old('maximum_connected_indoor_units', $product->maximum_connected_indoor_units) }}"></label>
                            <label><span>Geluidsniveau (dB)</span><input type="number" step="0.1" min="0" name="sound_level_db" value="{{ old('sound_level_db', $product->sound_level_db) }}"></label>
                        </div>
                    </div>

                    <div class="hvac-form-section">
                        <h3>Elektrisch & leidingen</h3>
                        <div class="hvac-form-grid">
                            <label><span>Voeding (bv. 230V mono)</span><input type="text" name="supported_voltage" value="{{ old('supported_voltage', $product->supported_voltage) }}" maxlength="50"></label>
                            <label><span>Fase</span><input type="text" name="supported_phase" value="{{ old('supported_phase', $product->supported_phase) }}" maxlength="20"></label>
                            <label><span>Zekering (A)</span><input type="number" min="0" max="200" name="required_breaker_a" value="{{ old('required_breaker_a', $product->required_breaker_a) }}"></label>
                            <label><span>Kabel</span><input type="text" name="required_cable" value="{{ old('required_cable', $product->required_cable) }}" maxlength="50"></label>
                            <label><span>Vloeistofleiding (Ø)</span><input type="text" name="liquid_pipe_diameter" value="{{ old('liquid_pipe_diameter', $product->liquid_pipe_diameter) }}" maxlength="20"></label>
                            <label><span>Gasleiding (Ø)</span><input type="text" name="gas_pipe_diameter" value="{{ old('gas_pipe_diameter', $product->gas_pipe_diameter) }}" maxlength="20"></label>
                            <label><span>Max. leidinglengte (m)</span><input type="number" step="0.1" min="0" name="maximum_pipe_length_m" value="{{ old('maximum_pipe_length_m', $product->maximum_pipe_length_m) }}"></label>
                            <label><span>Max. leidinglengte per unit (m)</span><input type="number" step="0.1" min="0" name="maximum_pipe_length_per_unit_m" value="{{ old('maximum_pipe_length_per_unit_m', $product->maximum_pipe_length_per_unit_m) }}"></label>
                            <label><span>Max. hoogteverschil (m)</span><input type="number" step="0.1" min="0" name="maximum_height_difference_m" value="{{ old('maximum_height_difference_m', $product->maximum_height_difference_m) }}"></label>
                        </div>
                    </div>

                    <div class="hvac-form-section">
                        <h3>Prijs, voorraad & opties</h3>
                        <div class="hvac-form-grid">
                            <label><span>Aankoopprijs excl. btw (€)</span><input type="number" step="0.01" min="0" name="purchase_price_excl_vat" value="{{ old('purchase_price_excl_vat', $product->purchase_price_excl_vat) }}"></label>
                            <label><span>Verkoopprijs excl. btw (€)</span><input type="number" step="0.01" min="0" name="default_sale_price_excl_vat" value="{{ old('default_sale_price_excl_vat', $product->default_sale_price_excl_vat) }}"></label>
                            <label><span>Voorraad</span><input type="number" min="0" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity) }}"></label>
                            <label><span>Levertijd (dagen)</span><input type="number" min="0" max="365" name="lead_time_days" value="{{ old('lead_time_days', $product->lead_time_days) }}"></label>
                            <label>
                                <span>Wi-Fi inbegrepen</span>
                                <select name="wifi_included">
                                    <option value="" @selected(old('wifi_included', $product->wifi_included) === null)>Onbekend</option>
                                    <option value="1" @selected((string) old('wifi_included', $product->wifi_included === null ? '' : (int) $product->wifi_included) === '1')>Ja</option>
                                    <option value="0" @selected((string) old('wifi_included', $product->wifi_included === null ? '' : (int) $product->wifi_included) === '0')>Nee</option>
                                </select>
                            </label>
                            <label>
                                <span>Status</span>
                                <select name="is_active">
                                    <option value="1" @selected((string) old('is_active', (int) ($product->is_active ?? true)) === '1')>Actief</option>
                                    <option value="0" @selected((string) old('is_active', (int) ($product->is_active ?? true)) === '0')>Inactief</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top: 1.4rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <button type="submit" class="button button-primary">Opslaan</button>
                        <a class="button button-secondary" href="{{ route('admin.hvac.products.index') }}">Terug naar overzicht</a>
                    </div>
                </form>

                @if ($isEdit)
                    <div class="hvac-form-section" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <form method="POST" action="{{ route('admin.hvac.products.toggle', $product) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button button-secondary">
                                {{ $product->is_active ? 'Deactiveren' : 'Activeren' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.hvac.products.duplicate', $product) }}">
                            @csrf
                            <button type="submit" class="button button-secondary">Dupliceren (als inactieve kopie)</button>
                        </form>
                    </div>
                @endif
            </div>

            @if ($isEdit)
                <div class="admin-detail-card" style="margin-top: 1.5rem;">
                    <h2>Compatibiliteit</h2>
                    <p class="hvac-muted">
                        Productselectie koppelt units uitsluitend via deze regels — nooit op basis van
                        gelijkaardige kW-waarden. Voeg hier de combinaties toe die de fabrikant ondersteunt.
                    </p>

                    @php
                        $allCompat = $product->compatibilities->map(fn ($c) => ['row' => $c, 'other' => $c->compatible, 'direction' => 'Dit product ondersteunt'])
                            ->concat($product->reverseCompatibilities->map(fn ($c) => ['row' => $c, 'other' => $c->parent, 'direction' => 'Ondersteund door']));
                    @endphp

                    @if ($allCompat->isEmpty())
                        <p class="admin-muted-text">Nog geen compatibiliteitsregels voor dit product.</p>
                    @else
                        <div class="admin-table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Richting</th>
                                        <th>Product</th>
                                        <th>Type</th>
                                        <th>Aansluitbereik</th>
                                        <th>Max. units</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($allCompat as $entry)
                                        <tr class="{{ $entry['row']->is_active ? '' : 'hvac-inactive-row' }}">
                                            <td class="hvac-muted">{{ $entry['direction'] }}</td>
                                            <td>{{ $entry['other']?->model }} ({{ $entry['other']?->sku }})</td>
                                            <td class="hvac-muted">{{ $entry['row']->compatibility_type }}</td>
                                            <td>
                                                @if ($entry['row']->minimum_connected_capacity_kw !== null || $entry['row']->maximum_connected_capacity_kw !== null)
                                                    {{ $entry['row']->minimum_connected_capacity_kw ?? '?' }} – {{ $entry['row']->maximum_connected_capacity_kw ?? '?' }} kW
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $entry['row']->maximum_units ?? '—' }}</td>
                                            <td>{{ $entry['row']->is_active ? 'Actief' : 'Inactief' }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('admin.hvac.compatibilities.toggle', $entry['row']) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="admin-link" style="border:0;background:none;cursor:pointer;">
                                                        {{ $entry['row']->is_active ? 'Deactiveren' : 'Activeren' }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.hvac.compatibilities.store') }}" class="hvac-form-section">
                        @csrf
                        <input type="hidden" name="parent_product_id" value="{{ $product->id }}">
                        <h3>Regel toevoegen (dit product als hoofdunit)</h3>
                        <div class="hvac-form-grid">
                            <label>
                                <span>Ondersteund product *</span>
                                <select name="compatible_product_id" required>
                                    <option value="">Kies een product</option>
                                    @foreach ($compatCandidates as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->model }} ({{ $candidate->sku }}, {{ $candidate->product_type }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Type *</span>
                                <select name="compatibility_type" required>
                                    <option value="indoor_outdoor">indoor_outdoor (single split)</option>
                                    <option value="multi_split_indoor">multi_split_indoor</option>
                                    <option value="required_accessory">required_accessory</option>
                                </select>
                            </label>
                            <label><span>Min. aansluitbaar (kW)</span><input type="number" step="0.01" min="0" name="minimum_connected_capacity_kw"></label>
                            <label><span>Max. aansluitbaar (kW)</span><input type="number" step="0.01" min="0" name="maximum_connected_capacity_kw"></label>
                            <label><span>Max. units</span><input type="number" min="1" max="20" name="maximum_units"></label>
                            <label><span>Notities</span><input type="text" name="notes" maxlength="1000"></label>
                        </div>
                        <div style="margin-top: 1rem;">
                            <button type="submit" class="button button-secondary">Compatibiliteit toevoegen</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </section>
@endsection
