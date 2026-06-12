@extends('layouts.app')

@section('title', 'Admin | Offerte bewerken')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>{{ $quote ? 'Offerte bewerken' : 'Offerte aanmaken' }}</h1>
            <p>
                {{ $quote
                    ? 'Offertegegevens voor ' . $customerRequest->customer_name . ' aanpassen.'
                    : 'Nieuwe offerte aanmaken voor ' . $customerRequest->customer_name . '.' }}
            </p>
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
                <div class="form-success">
                    Offerte werd opgeslagen.
                </div>
            @endif

            <div class="admin-detail-card admin-quote-form-card">
                <h2>Offertegegevens</h2>

                <form class="admin-quote-form" method="POST"
                    action="{{ route('admin.requests.quote.store', $customerRequest) }}">
                    @csrf

                    <label>
                        <span>Titel</span>
                        <input type="text" name="title" maxlength="200"
                            value="{{ old('title', $quote?->title) }}"
                            placeholder="Bijv. Airco-installatie 3 kamers">
                        @error('title')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                    </label>

                    <label>
                        <span>Omschrijving</span>
                        <textarea name="description" rows="4"
                            placeholder="Verdere beschrijving van de offerte...">{{ old('description', $quote?->description) }}</textarea>
                        @error('description')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="admin-quote-amounts-row">
                        <label>
                            <span>Bedrag excl. BTW (€)</span>
                            <input type="number" name="amount_excl_vat" id="amount_excl_vat"
                                step="0.01" min="0"
                                value="{{ old('amount_excl_vat', $quote?->amount_excl_vat) }}"
                                placeholder="0.00">
                            @error('amount_excl_vat')
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>

                        <label>
                            <span>BTW-tarief (%)</span>
                            <input type="number" name="vat_rate" id="vat_rate"
                                step="0.01" min="0"
                                value="{{ old('vat_rate', $quote?->vat_rate ?? '21') }}"
                                placeholder="21">
                            @error('vat_rate')
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    {{-- Live preview (client-side, read-only) --}}
                    <div class="admin-quote-preview" id="quote-preview"
                        style="{{ old('amount_excl_vat', $quote?->amount_excl_vat) !== null ? '' : 'display:none' }}">
                        <div class="admin-quote-amount-row">
                            <span>BTW</span>
                            <span id="preview-vat">
                                €&nbsp;{{ $quote?->amount_vat !== null ? number_format((float) $quote->amount_vat, 2, ',', '.') : '0,00' }}
                            </span>
                        </div>
                        <div class="admin-quote-amount-row admin-quote-amount-total">
                            <span>Incl. BTW</span>
                            <span id="preview-incl">
                                €&nbsp;{{ $quote?->amount_incl_vat !== null ? number_format((float) $quote->amount_incl_vat, 2, ',', '.') : '0,00' }}
                            </span>
                        </div>
                    </div>

                    <label>
                        <span>Geldig tot</span>
                        <input type="date" name="valid_until"
                            value="{{ old('valid_until', $quote?->valid_until?->format('Y-m-d')) }}">
                        @error('valid_until')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror
                    </label>

                    @if ($quote?->quote_number)
                        <p class="admin-muted-text" style="font-size: 0.82rem;">
                            Offertenummer: {{ $quote->quote_number }}
                        </p>
                    @endif

                    <div>
                        <button class="button button-primary" type="submit">
                            Offerte opslaan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>

    <script>
    (function () {
        var exclInput = document.getElementById('amount_excl_vat');
        var vatInput  = document.getElementById('vat_rate');
        var preview   = document.getElementById('quote-preview');
        var vatEl     = document.getElementById('preview-vat');
        var inclEl    = document.getElementById('preview-incl');

        if (! exclInput || ! vatInput) return;

        function fmt(n) {
            return '€ ' + n.toFixed(2).replace('.', ',');
        }

        function update() {
            var excl = parseFloat(exclInput.value);
            var rate = parseFloat(vatInput.value);
            if (isNaN(excl) || isNaN(rate) || excl < 0) {
                preview.style.display = 'none';
                return;
            }
            var vat  = Math.round(excl * (rate / 100) * 100) / 100;
            var incl = Math.round((excl + vat) * 100) / 100;
            vatEl.textContent  = fmt(vat);
            inclEl.textContent = fmt(incl);
            preview.style.display = '';
        }

        exclInput.addEventListener('input', update);
        vatInput.addEventListener('input', update);
    }());
    </script>
@endsection
