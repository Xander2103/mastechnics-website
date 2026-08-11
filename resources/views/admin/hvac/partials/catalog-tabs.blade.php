{{-- Product browsing tabs. Pass $activeTab: 'lists' | 'all'. --}}
<div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:1.2rem;">
    @foreach ([
        ['key' => 'lists', 'label' => 'Productlijsten', 'url' => route('admin.hvac.products.index', ['view' => 'lists'])],
        ['key' => 'all', 'label' => 'Alle producten', 'url' => route('admin.hvac.products.index', ['view' => 'all'])],
        ['key' => 'brands', 'label' => 'Merken', 'url' => route('admin.hvac.brands.index')],
        ['key' => 'suppliers', 'label' => 'Leveranciers', 'url' => route('admin.hvac.suppliers.index')],
    ] as $tab)
        <a href="{{ $tab['url'] }}"
           style="padding:0.4rem 0.9rem;border-radius:999px;text-decoration:none;font-size:0.88rem;{{ ($activeTab ?? '') === $tab['key'] ? 'background:#111827;color:#fff;' : 'background:#f3f4f6;color:#111827;' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
