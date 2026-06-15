<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offerte {{ $quote->quote_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.5;
        }

        /* ── Header bar ─────────────────────────── */
        .header-bar {
            background: #0f3557;
            color: #ffffff;
            padding: 22px 30px;
        }

        .header-bar-inner {
            display: table;
            width: 100%;
        }

        .header-company {
            display: table-cell;
            vertical-align: middle;
        }

        .header-company-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #ffffff;
        }

        .header-company-tagline {
            font-size: 10px;
            color: #93c5fd;
            margin-top: 3px;
        }

        .header-doc-info {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }

        .header-doc-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #93c5fd;
        }

        .header-doc-number {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }

        .header-doc-date {
            font-size: 10px;
            color: #bfdbfe;
            margin-top: 2px;
        }

        /* ── Body padding ───────────────────────── */
        .body-wrap {
            padding: 28px 30px 20px;
        }

        /* ── Two-column address/contact block ───── */
        .address-row {
            display: table;
            width: 100%;
            margin-bottom: 24px;
        }

        .address-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .address-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #0f66c2;
            margin-bottom: 6px;
            border-bottom: 1.5px solid #0f66c2;
            padding-bottom: 3px;
        }

        .address-value {
            font-size: 11px;
            line-height: 1.6;
            color: #1e293b;
        }

        .address-value strong {
            font-weight: 700;
        }

        /* ── Quote title / validity ─────────────── */
        .quote-header-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            background: #f0f7ff;
            border-left: 4px solid #0f66c2;
            padding: 12px 16px;
        }

        .quote-title-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .quote-title-text {
            font-size: 13px;
            font-weight: 700;
            color: #0f3557;
        }

        .quote-validity-cell {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }

        .quote-validity-text {
            font-size: 10px;
            color: #475569;
        }

        .quote-validity-date {
            font-size: 11px;
            font-weight: 700;
            color: #0f3557;
        }

        /* ── Items table ────────────────────────── */
        .items-section-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #0f66c2;
            margin-bottom: 6px;
            border-bottom: 1.5px solid #0f66c2;
            padding-bottom: 3px;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        table.items-table thead tr {
            background: #0f3557;
            color: #ffffff;
        }

        table.items-table thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
        }

        table.items-table thead th.right {
            text-align: right;
        }

        table.items-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }

        table.items-table tbody tr.alt {
            background: #f8fbff;
        }

        table.items-table tbody td {
            padding: 8px 10px;
            font-size: 10.5px;
            color: #1e293b;
            vertical-align: middle;
        }

        table.items-table tbody td.right {
            text-align: right;
        }

        table.items-table tbody td.muted {
            color: #64748b;
        }

        /* ── Totals ─────────────────────────────── */
        .totals-wrap {
            display: table;
            width: 100%;
            margin-bottom: 24px;
        }

        .totals-spacer {
            display: table-cell;
            width: 55%;
        }

        .totals-box {
            display: table-cell;
            width: 45%;
            vertical-align: top;
        }

        table.totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.totals-table td {
            padding: 6px 10px;
            font-size: 10.5px;
        }

        table.totals-table td:first-child {
            color: #475569;
        }

        table.totals-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: #0f3557;
        }

        table.totals-table tr.total-grand {
            border-top: 2px solid #0f3557;
        }

        table.totals-table tr.total-grand td {
            padding-top: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #0f3557;
        }

        table.totals-table tr.total-grand td:last-child {
            color: #0f66c2;
            font-size: 13px;
        }

        /* ── Notes section ──────────────────────── */
        .notes-section {
            margin-bottom: 20px;
        }

        .notes-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #0f66c2;
            margin-bottom: 6px;
            border-bottom: 1.5px solid #0f66c2;
            padding-bottom: 3px;
        }

        .notes-text {
            font-size: 10.5px;
            color: #475569;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* ── Terms ──────────────────────────────── */
        .terms-section {
            background: #f8fbff;
            border: 1px solid #dbeafe;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .terms-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #0f66c2;
            margin-bottom: 4px;
        }

        .terms-text {
            font-size: 10px;
            color: #475569;
            line-height: 1.6;
        }

        /* ── Footer bar ─────────────────────────── */
        .footer-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #0f3557;
            color: #93c5fd;
            font-size: 9px;
            padding: 8px 30px;
            display: table;
            width: 100%;
        }

        .footer-left {
            display: table-cell;
            vertical-align: middle;
        }

        .footer-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            color: #bfdbfe;
        }
    </style>
</head>
<body>

    {{-- ── Header ─────────────────────────────────────── --}}
    <div class="header-bar">
        <div class="header-bar-inner">
            <div class="header-company">
                <div class="header-company-name">MAS Technics</div>
                <div class="header-company-tagline">
                    Verwarming · Airco · Sanitair · Ventilatie · Waterverzachters · Koeling
                </div>
            </div>
            <div class="header-doc-info">
                <div class="header-doc-label">Offerte</div>
                <div class="header-doc-number">{{ $quote->quote_number ?? '—' }}</div>
                <div class="header-doc-date">
                    Datum: {{ $quote->created_at->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </div>

    <div class="body-wrap">

        {{-- ── Company + Customer address row ─────────── --}}
        <div class="address-row">
            <div class="address-cell">
                <div class="address-label">Van</div>
                <div class="address-value">
                    <strong>MAS Technics</strong><br>
                    {{ config('site.contact.phone_display') }}<br>
                    {{ config('site.contact.email') }}
                </div>
            </div>
            <div class="address-cell" style="padding-left: 24px;">
                <div class="address-label">Aan</div>
                <div class="address-value">
                    <strong>{{ $customerRequest->customer_name }}</strong><br>
                    @if ($customerRequest->customer_email)
                        {{ $customerRequest->customer_email }}<br>
                    @endif
                    @if ($customerRequest->customer_phone)
                        {{ $customerRequest->customer_phone }}<br>
                    @endif
                    @php
                        $answers = $customerRequest->metadata['answers'] ?? [];
                        $addressParts = array_filter([
                            $answers['street']      ?? null,
                            trim(($answers['postal_code'] ?? '') . ' ' . ($answers['city'] ?? '')),
                        ]);
                    @endphp
                    @foreach ($addressParts as $part)
                        {{ $part }}<br>
                    @endforeach
                    @if (!empty($answers['city']) && empty($answers['street']))
                        {{ $answers['city'] }}<br>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Quote title + validity ───────────────────── --}}
        @if ($quote->title || $quote->valid_until)
            <div class="quote-header-row">
                <div class="quote-title-cell">
                    @if ($quote->title)
                        <div class="quote-title-text">{{ $quote->title }}</div>
                    @else
                        <div class="quote-title-text">Offerte {{ $quote->quote_number }}</div>
                    @endif
                    @php
                        $serviceCatKey = $customerRequest->service_category;
                        $serviceCats = collect(config('request-flow.service_categories', []));
                        $catLabel = $serviceCats->firstWhere('value', $serviceCatKey);
                        $catText  = $catLabel['labels']['nl'] ?? $serviceCatKey;
                    @endphp
                    @if ($catText)
                        <div style="font-size:10px; color:#475569; margin-top:3px;">{{ $catText }}</div>
                    @endif
                </div>
                @if ($quote->valid_until)
                    <div class="quote-validity-cell">
                        <div class="quote-validity-text">Geldig tot</div>
                        <div class="quote-validity-date">{{ $quote->valid_until->format('d/m/Y') }}</div>
                    </div>
                @endif
            </div>
        @endif

        {{-- ── Items table ──────────────────────────────── --}}
        <div class="items-section-label">Offerteregels</div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Omschrijving</th>
                    <th class="right" style="width: 55px;">Aantal</th>
                    <th class="right" style="width: 90px;">Prijs excl.</th>
                    <th class="right" style="width: 50px;">BTW%</th>
                    <th class="right" style="width: 90px;">Totaal excl.</th>
                    <th class="right" style="width: 90px;">Totaal incl.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quote->items as $i => $item)
                    <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
                        <td class="muted">{{ $item->position }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format((float)$item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="right">€ {{ number_format((float)$item->unit_price_excl_vat, 2, ',', '.') }}</td>
                        <td class="right muted">{{ rtrim(rtrim(number_format((float)$item->vat_rate, 2, ',', '.'), '0'), ',') }}%</td>
                        <td class="right">€ {{ number_format((float)$item->line_total_excl_vat, 2, ',', '.') }}</td>
                        <td class="right" style="font-weight:700;">€ {{ number_format((float)$item->line_total_incl_vat, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ── Totals ────────────────────────────────────── --}}
        <div class="totals-wrap">
            <div class="totals-spacer"></div>
            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td>Subtotaal excl. BTW</td>
                        <td>€ {{ number_format((float)$quote->amount_excl_vat, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>BTW</td>
                        <td>€ {{ number_format((float)$quote->amount_vat, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="total-grand">
                        <td>Totaal incl. BTW</td>
                        <td>€ {{ number_format((float)$quote->amount_incl_vat, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- ── Description / notes ─────────────────────── --}}
        @if ($quote->description)
            <div class="notes-section">
                <div class="notes-label">Opmerkingen</div>
                <div class="notes-text">{{ $quote->description }}</div>
            </div>
        @endif

        {{-- ── Terms ────────────────────────────────────── --}}
        <div class="terms-section">
            <div class="terms-label">Voorwaarden</div>
            <div class="terms-text">
                Deze offerte is geldig tot de vermelde datum. Prijzen zijn inclusief BTW waar aangegeven.
                Na akkoord plannen we samen een geschikt uitvoeringsmoment.
            </div>
        </div>

    </div>

    {{-- ── Footer ───────────────────────────────────────── --}}
    <div class="footer-bar">
        <div class="footer-left">
            MAS Technics &mdash; {{ config('site.contact.phone_display') }} &mdash; {{ config('site.contact.email') }}
        </div>
        <div class="footer-right">
            {{ $quote->quote_number }}
        </div>
    </div>

</body>
</html>
