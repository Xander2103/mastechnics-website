@extends('layouts.app')

@section('title', 'Admin | Offerte bewerken')

@section('content')
    <style>
    /* ── Quote editor ─────────────────────────────── */
    .quote-edit-grid {
        display: grid;
        gap: 24px;
    }

    .quote-editor-section {
        background: var(--color-white);
        border: 1px solid var(--color-border);
        border-radius: 22px;
        padding: 28px;
        box-shadow: var(--shadow-soft);
    }

    .quote-editor-section h2 {
        margin-bottom: 18px;
        font-size: 1.15rem;
        color: var(--color-primary-dark);
    }

    .quote-fields-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .quote-fields-grid label {
        display: grid;
        gap: 6px;
        font-weight: 800;
        color: var(--color-primary-dark);
    }

    .quote-fields-grid label.field-full {
        grid-column: 1 / -1;
    }

    /* ── Item table ──────────────────────────────── */
    .quote-items-table {
        width: 100%;
        border-collapse: collapse;
    }

    .quote-items-table th {
        padding: 8px 10px;
        text-align: left;
        font-size: 0.78rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-primary);
        border-bottom: 2px solid var(--color-border);
    }

    .quote-items-table th.col-num  { width: 60px; }
    .quote-items-table th.col-qty  { width: 80px; }
    .quote-items-table th.col-price { width: 120px; }
    .quote-items-table th.col-vat  { width: 80px; }
    .quote-items-table th.col-total { width: 110px; }
    .quote-items-table th.col-del  { width: 44px; }

    .quote-item-row td {
        padding: 8px 6px;
        border-bottom: 1px solid var(--color-border);
        vertical-align: middle;
    }

    .quote-item-row input {
        width: 100%;
        border: 1px solid var(--color-border);
        border-radius: 10px;
        padding: 8px 10px;
        font: inherit;
        font-size: 0.92rem;
        background: #f8fbff;
        color: var(--color-text);
    }

    .quote-item-row input:focus {
        outline: none;
        border-color: rgba(15, 102, 194, 0.55);
        box-shadow: 0 0 0 3px rgba(15, 102, 194, 0.1);
    }

    .quote-item-row td.col-total-val {
        font-weight: 800;
        font-size: 0.92rem;
        color: var(--color-primary-dark);
        white-space: nowrap;
        padding-left: 10px;
    }

    .quote-item-del-btn {
        cursor: pointer;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 999px;
        background: #fee2e2;
        color: #991b1b;
        font-size: 1.1rem;
        font-weight: 900;
    }

    .quote-item-del-btn:hover {
        background: #fecaca;
    }

    .quote-add-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 12px;
    }

    /* ── Totals ──────────────────────────────────── */
    .quote-totals-box {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        margin-top: 18px;
        padding-top: 16px;
        border-top: 2px solid var(--color-border);
    }

    .quote-total-row {
        display: flex;
        gap: 24px;
        align-items: center;
        font-size: 0.92rem;
    }

    .quote-total-row span:first-child {
        min-width: 160px;
        text-align: right;
        color: var(--color-muted);
        font-weight: 700;
    }

    .quote-total-row span:last-child {
        min-width: 90px;
        text-align: right;
        font-weight: 800;
        color: var(--color-primary-dark);
    }

    .quote-total-row.quote-total-grand span:first-child {
        font-size: 1rem;
        font-weight: 900;
        color: var(--color-primary-dark);
    }

    .quote-total-row.quote-total-grand span:last-child {
        font-size: 1.15rem;
        color: var(--color-primary);
    }

    /* ── Mobile ──────────────────────────────────── */
    @media (max-width: 680px) {
        .quote-fields-grid {
            grid-template-columns: 1fr;
        }
        .quote-items-table { display: none; }
        .quote-items-mobile { display: block; }
    }

    .quote-items-mobile { display: none; }

    .quote-mobile-item {
        border: 1px solid var(--color-border);
        border-radius: 14px;
        padding: 14px;
        margin-bottom: 12px;
        background: #f8fbff;
    }

    .quote-mobile-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .quote-mobile-item-header strong {
        font-size: 0.82rem;
        color: var(--color-primary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .quote-mobile-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .quote-mobile-fields label {
        display: grid;
        gap: 4px;
        font-size: 0.82rem;
        font-weight: 800;
        color: var(--color-muted);
    }

    .quote-mobile-fields label.field-full {
        grid-column: 1 / -1;
    }
    </style>

    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>{{ $quote ? 'Offerte bewerken' : 'Offerte aanmaken' }}</h1>
            <p>{{ $customerRequest->customer_name }} — {{ $customerRequest->quote?->quote_number ?: 'Nieuw' }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">

            <div class="admin-back-row">
                <a class="button button-secondary admin-back-button"
                   href="{{ route('admin.requests.show', $customerRequest) }}">
                    ← Terug naar aanvraag
                </a>
            </div>

            @if (session('success') === 'quote_saved')
                <div class="form-success">Offerte werd opgeslagen.</div>
            @endif

            @if ($errors->any())
                <div class="form-error-list">
                    <strong>Controleer de ingevoerde gegevens.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('admin.requests.quote.store', $customerRequest) }}"
                  id="quoteForm">
                @csrf

                <div class="quote-edit-grid">

                    {{-- ── Header fields ────────────────────────────── --}}
                    <div class="quote-editor-section">
                        <h2>Offertegegevens</h2>

                        <div class="quote-fields-grid">
                            <label class="field-full">
                                <span>Titel</span>
                                <input type="text" name="title" maxlength="200"
                                       value="{{ old('title', $quote?->title) }}"
                                       placeholder="Bijv. Airco-installatie 3 kamers">
                                @error('title')
                                    <p class="field-error-text">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="field-full">
                                <span>Omschrijving / opmerkingen</span>
                                <textarea name="description" rows="3"
                                          placeholder="Verdere beschrijving, bijzondere voorwaarden...">{{ old('description', $quote?->description) }}</textarea>
                                @error('description')
                                    <p class="field-error-text">{{ $message }}</p>
                                @enderror
                            </label>

                            <label>
                                <span>Geldig tot</span>
                                <input type="date" name="valid_until"
                                       value="{{ old('valid_until', $quote?->valid_until?->format('Y-m-d')) }}">
                                @error('valid_until')
                                    <p class="field-error-text">{{ $message }}</p>
                                @enderror
                            </label>

                            @if ($quote?->quote_number)
                                <label style="justify-content: end;">
                                    <span>Offertenummer</span>
                                    <span style="padding: 9px 0; font-weight: 900; color: var(--color-primary-dark);">
                                        {{ $quote->quote_number }}
                                    </span>
                                </label>
                            @endif
                        </div>
                    </div>

                    {{-- ── Line items ───────────────────────────────── --}}
                    <div class="quote-editor-section">
                        <h2>Offerteregels</h2>

                        @php
                            $existingItems = old('items')
                                ? collect(old('items'))->values()
                                : ($quote ? $quote->items->values() : collect());

                            if ($existingItems->isEmpty()) {
                                $existingItems = collect([
                                    ['description' => '', 'quantity' => '1', 'unit_price_excl_vat' => '', 'vat_rate' => '21'],
                                ]);
                            }
                        @endphp

                        {{-- Desktop table --}}
                        <table class="quote-items-table" id="quoteItemsTable">
                            <thead>
                                <tr>
                                    <th class="col-num">#</th>
                                    <th>Omschrijving</th>
                                    <th class="col-qty">Aantal</th>
                                    <th class="col-price">Prijs excl.</th>
                                    <th class="col-vat">BTW%</th>
                                    <th class="col-total">Totaal excl.</th>
                                    <th class="col-del"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                @foreach ($existingItems as $idx => $item)
                                    <tr class="quote-item-row" data-row="{{ $idx }}">
                                        <td style="text-align:center; font-size: 0.82rem; color: var(--color-muted);">
                                            <span class="row-num">{{ $idx + 1 }}</span>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="items[{{ $idx }}][description]"
                                                   value="{{ is_array($item) ? ($item['description'] ?? '') : $item->description }}"
                                                   placeholder="Omschrijving van de post..."
                                                   required>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="items[{{ $idx }}][quantity]"
                                                   value="{{ is_array($item) ? ($item['quantity'] ?? '1') : $item->quantity }}"
                                                   min="0" step="0.01" class="js-qty"
                                                   required>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="items[{{ $idx }}][unit_price_excl_vat]"
                                                   value="{{ is_array($item) ? ($item['unit_price_excl_vat'] ?? '') : $item->unit_price_excl_vat }}"
                                                   min="0" step="0.01" placeholder="0.00" class="js-price"
                                                   required>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="items[{{ $idx }}][vat_rate]"
                                                   value="{{ is_array($item) ? ($item['vat_rate'] ?? '21') : $item->vat_rate }}"
                                                   min="0" step="0.01" class="js-vat"
                                                   required>
                                        </td>
                                        <td class="col-total-val js-line-total">
                                            @php
                                                $qty   = is_array($item) ? ($item['quantity'] ?? 1) : (float) $item->quantity;
                                                $price = is_array($item) ? ($item['unit_price_excl_vat'] ?? 0) : (float) $item->unit_price_excl_vat;
                                                $lt    = round($qty * $price, 2);
                                            @endphp
                                            € {{ number_format($lt, 2, ',', '.') }}
                                        </td>
                                        <td>
                                            <button type="button" class="quote-item-del-btn js-del-row"
                                                    aria-label="Rij verwijderen">×</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Mobile cards --}}
                        <div class="quote-items-mobile" id="itemsMobileContainer">
                            @foreach ($existingItems as $idx => $item)
                                <div class="quote-mobile-item" data-row="{{ $idx }}">
                                    <div class="quote-mobile-item-header">
                                        <strong>Regel <span class="row-num">{{ $idx + 1 }}</span></strong>
                                        <button type="button" class="quote-item-del-btn js-del-row-mobile"
                                                aria-label="Rij verwijderen">×</button>
                                    </div>
                                    <div class="quote-mobile-fields">
                                        <label class="field-full">
                                            <span>Omschrijving</span>
                                            <input type="text"
                                                   name="items[{{ $idx }}][description]"
                                                   value="{{ is_array($item) ? ($item['description'] ?? '') : $item->description }}"
                                                   placeholder="Omschrijving..." required>
                                        </label>
                                        <label>
                                            <span>Aantal</span>
                                            <input type="number"
                                                   name="items[{{ $idx }}][quantity]"
                                                   value="{{ is_array($item) ? ($item['quantity'] ?? '1') : $item->quantity }}"
                                                   min="0" step="0.01" class="js-qty-m" required>
                                        </label>
                                        <label>
                                            <span>Prijs excl. BTW (€)</span>
                                            <input type="number"
                                                   name="items[{{ $idx }}][unit_price_excl_vat]"
                                                   value="{{ is_array($item) ? ($item['unit_price_excl_vat'] ?? '') : $item->unit_price_excl_vat }}"
                                                   min="0" step="0.01" placeholder="0.00" class="js-price-m" required>
                                        </label>
                                        <label>
                                            <span>BTW (%)</span>
                                            <input type="number"
                                                   name="items[{{ $idx }}][vat_rate]"
                                                   value="{{ is_array($item) ? ($item['vat_rate'] ?? '21') : $item->vat_rate }}"
                                                   min="0" step="0.01" class="js-vat-m" required>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="quote-add-row">
                            <button type="button" id="addRowBtn" class="button button-secondary">
                                + Regel toevoegen
                            </button>
                        </div>

                        {{-- Totals preview (client-side, read-only) --}}
                        <div class="quote-totals-box" id="quoteTotals">
                            <div class="quote-total-row">
                                <span>Subtotaal excl. BTW</span>
                                <span id="totalExcl">€ 0,00</span>
                            </div>
                            <div class="quote-total-row">
                                <span>BTW</span>
                                <span id="totalVat">€ 0,00</span>
                            </div>
                            <div class="quote-total-row quote-total-grand">
                                <span>Totaal incl. BTW</span>
                                <span id="totalIncl">€ 0,00</span>
                            </div>
                        </div>
                    </div>

                    {{-- ── Save ─────────────────────────────────────── --}}
                    <div>
                        <button type="submit" class="button button-primary button-large">
                            Offerte opslaan
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </section>

    <script>
    (function () {
        'use strict';

        var tbody    = document.getElementById('itemsTableBody');
        var mobile   = document.getElementById('itemsMobileContainer');
        var addBtn   = document.getElementById('addRowBtn');
        var totalExcl = document.getElementById('totalExcl');
        var totalVat  = document.getElementById('totalVat');
        var totalIncl = document.getElementById('totalIncl');

        function fmt(n) {
            return '€ ' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function rowTotal(qty, price) {
            return Math.round(qty * price * 100) / 100;
        }

        function recalcAll() {
            var excl = 0, vat = 0, incl = 0;

            document.querySelectorAll('#itemsTableBody .quote-item-row').forEach(function (row) {
                var q = parseFloat(row.querySelector('.js-qty').value) || 0;
                var p = parseFloat(row.querySelector('.js-price').value) || 0;
                var v = parseFloat(row.querySelector('.js-vat').value) || 0;
                var lineExcl = Math.round(q * p * 100) / 100;
                var lineVat  = Math.round(lineExcl * v / 100 * 100) / 100;
                var lineIncl = Math.round((lineExcl + lineVat) * 100) / 100;
                excl += lineExcl;
                vat  += lineVat;
                incl += lineIncl;
                row.querySelector('.js-line-total').textContent = fmt(lineExcl);
            });

            totalExcl.textContent = fmt(Math.round(excl * 100) / 100);
            totalVat.textContent  = fmt(Math.round(vat  * 100) / 100);
            totalIncl.textContent = fmt(Math.round(incl * 100) / 100);
        }

        function reindex() {
            var rows = document.querySelectorAll('#itemsTableBody .quote-item-row');
            var mobileRows = document.querySelectorAll('#itemsMobileContainer .quote-mobile-item');

            rows.forEach(function (row, i) {
                row.dataset.row = i;
                row.querySelector('.row-num').textContent = i + 1;
                row.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
                });
            });

            mobileRows.forEach(function (card, i) {
                card.dataset.row = i;
                card.querySelector('.row-num').textContent = i + 1;
                card.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
                });
            });
        }

        function cloneRow(idx) {
            var tpl = document.querySelector('#itemsTableBody .quote-item-row');

            // Desktop
            var newRow = tpl.cloneNode(true);
            newRow.dataset.row = idx;
            newRow.querySelector('.row-num').textContent = idx + 1;
            newRow.querySelectorAll('input').forEach(function (inp) {
                if (inp.classList.contains('js-qty')) { inp.value = '1'; }
                else if (inp.classList.contains('js-vat')) { inp.value = '21'; }
                else { inp.value = ''; }
                inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
            });
            newRow.querySelector('.js-line-total').textContent = '€ 0,00';
            tbody.appendChild(newRow);

            // Mobile
            var tplM = document.querySelector('#itemsMobileContainer .quote-mobile-item');
            var newCard = tplM.cloneNode(true);
            newCard.dataset.row = idx;
            newCard.querySelector('.row-num').textContent = idx + 1;
            newCard.querySelectorAll('input').forEach(function (inp) {
                if (inp.classList.contains('js-qty-m')) { inp.value = '1'; }
                else if (inp.classList.contains('js-vat-m')) { inp.value = '21'; }
                else { inp.value = ''; }
                inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
            });
            mobile.appendChild(newCard);
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var count = document.querySelectorAll('#itemsTableBody .quote-item-row').length;
                cloneRow(count);
                recalcAll();
            });
        }

        if (tbody) {
            tbody.addEventListener('input', function (e) {
                if (e.target.matches('.js-qty, .js-price, .js-vat')) {
                    recalcAll();
                }
            });

            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-del-row');
                if (!btn) return;
                var rows = document.querySelectorAll('#itemsTableBody .quote-item-row');
                if (rows.length <= 1) { alert('Er moet minstens één regel zijn.'); return; }
                var row = btn.closest('.quote-item-row');
                var idx = row.dataset.row;
                row.remove();
                // Remove matching mobile card
                var mCard = document.querySelector('#itemsMobileContainer .quote-mobile-item[data-row="' + idx + '"]');
                if (mCard) mCard.remove();
                reindex();
                recalcAll();
            });
        }

        if (mobile) {
            mobile.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-del-row-mobile');
                if (!btn) return;
                var cards = document.querySelectorAll('#itemsMobileContainer .quote-mobile-item');
                if (cards.length <= 1) { alert('Er moet minstens één regel zijn.'); return; }
                var card = btn.closest('.quote-mobile-item');
                var idx = card.dataset.row;
                card.remove();
                // Remove matching desktop row
                var dRow = document.querySelector('#itemsTableBody .quote-item-row[data-row="' + idx + '"]');
                if (dRow) dRow.remove();
                reindex();
                recalcAll();
            });
        }

        // Initial totals calculation
        recalcAll();
    }());
    </script>
@endsection
