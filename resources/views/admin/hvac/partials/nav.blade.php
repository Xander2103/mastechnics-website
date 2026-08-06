<style>
.hvac-subnav { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; }
.hvac-subnav a {
    padding: 0.45rem 0.9rem; border: 1px solid #e5e7eb; border-radius: 999px;
    font-size: 0.85rem; text-decoration: none; color: #374151; background: #fff;
}
.hvac-subnav a.is-active { background: #0f66c2; border-color: #0f66c2; color: #fff; }
.hvac-muted { color: #6b7280; font-size: 0.82rem; }
.hvac-inactive-row { opacity: 0.55; }
.hvac-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.9rem 1.2rem; }
.hvac-form-grid label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.85rem; font-weight: 600; }
.hvac-form-grid input, .hvac-form-grid select { padding: 0.45rem 0.6rem; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; }
.hvac-form-section { margin-top: 1.4rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
.hvac-form-section h3 { font-size: 0.9rem; margin-bottom: 0.75rem; }
</style>
<nav class="hvac-subnav" aria-label="HVAC-navigatie">
    <a href="{{ route('admin.hvac.products.index') }}" @if (request()->routeIs('admin.hvac.products.*')) class="is-active" aria-current="page" @endif>Producten</a>
    <a href="{{ route('admin.hvac.brands.index') }}" @if (request()->routeIs('admin.hvac.brands.*')) class="is-active" aria-current="page" @endif>Merken</a>
    <a href="{{ route('admin.hvac.suppliers.index') }}" @if (request()->routeIs('admin.hvac.suppliers.*')) class="is-active" aria-current="page" @endif>Leveranciers</a>
    <a href="{{ route('admin.hvac.import.index') }}" @if (request()->routeIs('admin.hvac.import.*')) class="is-active" aria-current="page" @endif>Import</a>
    <a href="{{ route('admin.hvac.rules.index') }}" @if (request()->routeIs('admin.hvac.rules.*')) class="is-active" aria-current="page" @endif>Berekeningsregels</a>
    <a href="{{ route('admin.hvac.checklist') }}" @if (request()->routeIs('admin.hvac.checklist')) class="is-active" aria-current="page" @endif>Checklist</a>
</nav>

@php
    $hvacTestCatalogActive = \App\Models\HvacProduct::active()
        ->where(fn ($q) => $q->where('sku', 'like', 'TEST%')->orWhere('name', 'like', 'TEST%'))
        ->exists();
@endphp
@if ($hvacTestCatalogActive)
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:0.7rem 1rem;font-size:0.88rem;margin-bottom:1.25rem;font-weight:600;">
        Testcatalogus — niet gebruiken voor echte offertes.
    </div>
@endif
