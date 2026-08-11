@extends('layouts.app')

@section('title', 'Admin | Import — gegevens controleren')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Productbestand importeren</span>
            <h1>Gegevens controleren</h1>
            <p>{{ $state['original_name'] }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.progress', ['current' => 3])
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            @if (! empty($state['profile_notice']))
                <div class="admin-detail-card" style="margin-bottom:1rem;background:#f0f7f1;">
                    <p style="margin:0;">{{ $state['profile_notice'] }}</p>
                </div>
            @endif

            {{-- Summary cards --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:0.8rem;margin-bottom:1.2rem;">
                <div class="admin-detail-card" style="text-align:center;">
                    <strong style="font-size:1.4rem;">{{ number_format($totalRows, 0, ',', '.') }}</strong><br>geselecteerd
                </div>
                <div class="admin-detail-card" style="text-align:center;">
                    <strong style="font-size:1.4rem;color:#1a7f37;">{{ number_format($okCount, 0, ',', '.') }}</strong><br>klaar
                </div>
                <div class="admin-detail-card" style="text-align:center;">
                    <strong style="font-size:1.4rem;color:#9a6700;">{{ number_format($reviewCount, 0, ',', '.') }}</strong><br>controleren
                </div>
                <div class="admin-detail-card" style="text-align:center;">
                    <strong style="font-size:1.4rem;color:#b42318;">{{ number_format($blockedCount, 0, ',', '.') }}</strong><br>geblokkeerd
                </div>
            </div>

            <form method="POST" action="{{ route('admin.hvac.import.guided.review', $token) }}">
                @csrf

                {{-- Recognized fields --}}
                <div class="admin-detail-card" style="margin-bottom:1.2rem;">
                    <h2>Herkende gegevens</h2>
                    <div style="display:flex;flex-direction:column;gap:0.45rem;margin-top:0.8rem;">
                        @foreach ($summary as $row)
                            <div style="display:flex;gap:0.8rem;align-items:baseline;flex-wrap:wrap;">
                                <span style="flex:0 0 9rem;font-weight:600;">{{ $row['label'] }}</span>
                                <span style="flex:1;">
                                    @if ($row['status'] === 'ok')
                                        <span aria-hidden="true" style="color:#1a7f37;">&#10003;</span>
                                        <span class="sr-only">Herkend:</span>
                                    @elseif ($row['status'] === 'attention')
                                        <span aria-hidden="true" style="color:#9a6700;">!</span>
                                        <span class="sr-only">Controleren:</span>
                                    @else
                                        <span aria-hidden="true">&mdash;</span>
                                        <span class="sr-only">Niet beschikbaar:</span>
                                    @endif
                                    {{ $row['source'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Open questions: only what genuinely needs the admin --}}
                @if ($questions !== [])
                    <div class="admin-detail-card" style="margin-bottom:1.2rem;border-left:4px solid #9a6700;">
                        <h2>Nog te beantwoorden</h2>

                        @if (isset($questions['price']))
                            <div style="margin-top:0.8rem;">
                                <p style="font-weight:600;margin-bottom:0.4rem;">Wat betekent &ldquo;{{ $questions['price']['header'] }}&rdquo; in dit bestand?</p>
                                <div style="display:flex;flex-direction:column;gap:0.35rem;">
                                    <label><input type="radio" name="price_meaning" value="gross"> Brutoprijs van de leverancier (catalogusprijs)</label>
                                    <label><input type="radio" name="price_meaning" value="net_purchase"> Netto aankoopprijs (wat wij betalen)</label>
                                    <label><input type="radio" name="price_meaning" value="sale"> Verkoopprijs (wat de klant betaalt)</label>
                                    <label><input type="radio" name="price_meaning" value="unknown"> Weet ik niet</label>
                                </div>
                                <p class="hvac-muted" style="margin-top:0.4rem;font-size:0.85rem;">
                                    Bij &ldquo;weet ik niet&rdquo; of een brutoprijs wordt de prijs wel bewaard,
                                    maar niet gebruikt voor automatische offertes.
                                </p>
                            </div>
                        @endif

                        @if (isset($questions['supplier']))
                            <div style="margin-top:0.8rem;max-width:22rem;">
                                <label style="font-weight:600;">Leverancier van dit bestand
                                    <input type="text" name="supplier_name" value="{{ old('supplier_name') }}"
                                           placeholder="bv. TestSupplier BV" style="width:100%;margin-top:0.3rem;">
                                </label>
                            </div>
                        @endif

                        @if (isset($questions['brand']))
                            <div style="margin-top:0.8rem;max-width:22rem;">
                                <label style="font-weight:600;">Merk van deze producten
                                    <input type="text" name="brand_name" value="{{ old('brand_name') }}"
                                           placeholder="bv. TestBrand" style="width:100%;margin-top:0.3rem;">
                                </label>
                                <p class="hvac-muted" style="font-size:0.85rem;margin-top:0.3rem;">
                                    Staat het merk in een kolom? Koppel die dan bij &ldquo;Geavanceerde koppelingen&rdquo;.
                                </p>
                            </div>
                        @endif

                        @if (isset($questions['type']))
                            <div style="margin-top:0.8rem;max-width:22rem;">
                                <p style="font-weight:600;margin-bottom:0.3rem;">
                                    Voor {{ $questions['type']['count'] }} producten konden we het type niet herkennen.
                                </p>
                                <label>Type voor die producten
                                    <select name="type_fallback" style="width:100%;margin-top:0.3rem;">
                                        <option value="">— per product nakijken na de import —</option>
                                        @foreach ($productTypes as $type)
                                            <option value="{{ $type }}" @selected($state['type_fallback'] === $type)>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Advanced: full mapping, collapsed by default --}}
                <details class="admin-detail-card" style="margin-bottom:1.2rem;">
                    <summary style="cursor:pointer;font-weight:600;">Geavanceerde koppelingen</summary>
                    <p class="hvac-muted" style="margin:0.6rem 0;">
                        Hier kunt u elke kolom handmatig aan een veld koppelen. Normaal is dit niet nodig.
                    </p>
                    <div class="admin-table-wrapper">
                        <table class="admin-table" style="font-size:0.82rem;">
                            <thead>
                                <tr><th>Kolom in bestand</th><th>Veld</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($headerCells as $col => $header)
                                    <tr>
                                        <td>{{ $header ?? 'kolom ' . ($col + 1) }}</td>
                                        <td>
                                            <select name="mapping[{{ $col }}]">
                                                <option value="">— niet importeren —</option>
                                                @foreach ($fields as $field)
                                                    <option value="{{ $field }}"
                                                        @selected(($state['column_map'][$col] ?? null) === $field)>{{ $field }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <label style="display:block;margin-top:0.8rem;max-width:16rem;">Decimalen
                        <select name="decimal_format" style="width:100%;margin-top:0.3rem;">
                            <option value="auto" @selected($state['decimal_format'] === 'auto')>Automatisch</option>
                            <option value="comma" @selected($state['decimal_format'] === 'comma')>Komma (1.234,56)</option>
                            <option value="point" @selected($state['decimal_format'] === 'point')>Punt (1,234.56)</option>
                        </select>
                    </label>
                </details>

                <div style="margin-bottom:1.2rem;">
                    <button type="submit" class="button button-primary">Verder naar importeren</button>
                </div>
            </form>

            {{-- Preview --}}
            <div class="admin-detail-card">
                <h2>Voorbeeld ({{ $previewRows->count() }} van {{ number_format($totalRows, 0, ',', '.') }} producten)</h2>
                @if ($truncated)
                    <p class="hvac-muted">Het bestand is erg groot; alleen de eerste rijen zijn ingelezen.</p>
                @endif
                <div class="admin-table-wrapper">
                    <table class="admin-table" style="font-size:0.82rem;">
                        <thead>
                            <tr><th>Importeren</th><th>Artikel</th><th>Product</th><th>Type</th><th>Prijs</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($previewRows as $row)
                                @php
                                    $p = $provenanceByLine[$row['line']] ?? [];
                                    $price = $row['data']['purchase_price_excl_vat'] ?? $row['data']['sale_price_excl_vat'] ?? ($p['price']['raw'] ?? null);
                                    $problems = $row['errors'];
                                    if ($problems === [] && ($p['needs_review'] ?? false)) {
                                        $problems = [];
                                        if (($row['data']['product_type'] ?? '') === '') $problems[] = 'Type controleren';
                                        if (in_array($p['price']['meaning'] ?? '', ['gross', 'unknown'], true)) $problems[] = 'Prijs niet bruikbaar voor offertes';
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        @if ($row['errors'] === [])
                                            <span aria-hidden="true" style="color:#1a7f37;">&#10003;</span><span class="sr-only">ja</span>
                                        @else
                                            <span aria-hidden="true" style="color:#b42318;">&#10007;</span><span class="sr-only">nee</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['data']['sku'] ?? '' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($row['data']['name'] ?? '', 46) }}</td>
                                    <td>{{ ($row['data']['product_type'] ?? '') !== '' ? $row['data']['product_type'] : '—' }}</td>
                                    <td>{{ $price !== null ? '€ ' . $price : '—' }}</td>
                                    <td>
                                        @if ($problems === [])
                                            Klaar
                                        @else
                                            {{ implode(' · ', array_slice($problems, 0, 2)) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top:0.8rem;display:flex;gap:1rem;flex-wrap:wrap;">
                @if (! ($state['category']['skipped'] ?? true))
                    <form method="POST" action="{{ route('admin.hvac.import.guided.back', $token) }}">
                        @csrf
                        <input type="hidden" name="step" value="categories">
                        <button type="submit" class="admin-link" style="background:none;border:none;cursor:pointer;padding:0;">
                            &larr; Terug naar productkeuze
                        </button>
                    </form>
                @endif
                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>
@endsection
