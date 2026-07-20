@extends('layouts.app')

@section('title', 'Admin | Aanvragen')

@section('content')
    <style>
    .admin-stats-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .admin-stat-card {
        flex: 1 1 140px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.25rem 1rem;
        text-decoration: none;
        text-align: center;
        color: inherit;
        transition: box-shadow 0.15s;
    }
    .admin-stat-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .admin-stat-card-urgent {
        border-color: #fca5a5;
        background: #fff7f7;
        cursor: default;
    }
    .admin-stat-card-urgent:hover {
        box-shadow: none;
    }
    .admin-stat-number {
        display: block;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        color: #111827;
    }
    .admin-stat-card-urgent .admin-stat-number {
        color: #dc2626;
    }
    .admin-stat-label {
        display: block;
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }

    /* Notification center */
    .admin-notification-center {
        display: grid;
        gap: 8px;
        margin-bottom: 1.5rem;
    }
    .admin-notification {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 0.7rem 1rem;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        font-size: 0.88rem;
        color: #92400e;
    }
    .admin-notification-dismiss {
        border: 0;
        background: transparent;
        cursor: pointer;
        font-size: 1.1rem;
        line-height: 1;
        color: #92400e;
    }

    /* Dashboard widget cards */
    .admin-dashboard-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .admin-dashboard-card {
        flex: 1 1 160px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
        text-decoration: none;
        text-align: center;
        color: inherit;
    }
    .admin-dashboard-number {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
    }
    .admin-dashboard-label {
        display: block;
        font-size: 0.76rem;
        color: #6b7280;
        margin-top: 0.2rem;
    }

    /* Recent activity */
    .admin-activity-list {
        display: grid;
        gap: 10px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .admin-activity-list li {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: baseline;
        font-size: 0.88rem;
        padding-bottom: 10px;
        border-bottom: 1px solid #f0f2f5;
    }
    .admin-activity-date {
        color: #9ca3af;
        font-size: 0.78rem;
        white-space: nowrap;
    }
    .admin-activity-note {
        color: #6b7280;
        font-size: 0.82rem;
        flex-basis: 100%;
    }

    /* Statistics */
    .admin-stats-summary-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .admin-stats-summary-grid div {
        min-width: 130px;
    }
    .admin-stats-summary-grid strong {
        display: block;
        font-size: 1.3rem;
        color: #111827;
    }
    .admin-stats-summary-grid span {
        font-size: 0.76rem;
        color: #6b7280;
    }
    .admin-chart-heading {
        margin: 1.5rem 0 0.75rem;
        font-size: 0.95rem;
        color: #111827;
    }
    .admin-bar-chart {
        display: flex;
        align-items: flex-end;
        gap: 14px;
        min-height: 120px;
        padding-top: 10px;
    }
    .admin-bar-chart-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    .admin-bar-chart-bar {
        width: 28px;
        min-height: 4px;
        background: var(--color-primary, #0f66c2);
        border-radius: 4px 4px 0 0;
    }
    .admin-bar-chart-label {
        font-size: 0.72rem;
        color: #6b7280;
    }
    .admin-bar-chart-value {
        font-size: 0.72rem;
        font-weight: 700;
        color: #111827;
    }
    .admin-hbar-chart {
        display: grid;
        gap: 8px;
    }
    .admin-hbar-row {
        display: grid;
        grid-template-columns: 140px 1fr 32px;
        align-items: center;
        gap: 10px;
        font-size: 0.82rem;
    }
    .admin-hbar-track {
        height: 10px;
        background: #f0f2f5;
        border-radius: 999px;
        overflow: hidden;
    }
    .admin-hbar-fill {
        height: 100%;
        background: var(--color-primary, #0f66c2);
        border-radius: 999px;
    }
    </style>

    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Aanvragen</h1>
            <p>Overzicht van alle aanvragen die via het slimme aanvraagformulier zijn binnengekomen.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            <div class="admin-stats-row">
                <a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'new']) }}">
                    <span class="admin-stat-number">{{ $stats['new'] }}</span>
                    <span class="admin-stat-label">Nieuwe aanvragen</span>
                </a>
                <div class="admin-stat-card admin-stat-card-urgent">
                    <span class="admin-stat-number">{{ $stats['urgent'] }}</span>
                    <span class="admin-stat-label">Dringend</span>
                </div>
                <a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'contacted']) }}">
                    <span class="admin-stat-number">{{ $stats['contacted'] }}</span>
                    <span class="admin-stat-label">Gecontacteerd</span>
                </a>
                <a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'quote_sent']) }}">
                    <span class="admin-stat-number">{{ $stats['quote_sent'] }}</span>
                    <span class="admin-stat-label">Offerte verstuurd</span>
                </a>
            </div>

            {{-- Notification center --}}
            @php
                $notifications = collect([
                    $stats['new'] > 0 ? "{$stats['new']} nieuwe aanvraag/aanvragen" : null,
                    $stats['urgent'] > 0 ? "{$stats['urgent']} dringende aanvraag/aanvragen" : null,
                    $todaysFollowUps > 0 ? "{$todaysFollowUps} opvolging(en) vandaag nodig" : null,
                    $quotesAwaitingAnswer > 0 ? "{$quotesAwaitingAnswer} offerte(s) wachten op antwoord" : null,
                ])->filter()->values();
            @endphp

            @if ($notifications->isNotEmpty())
                <div class="admin-notification-center" id="adminNotificationCenter">
                    @foreach ($notifications as $i => $message)
                        <div class="admin-notification" data-notification-id="notif-{{ $i }}">
                            <span>{{ $message }}</span>
                            <button type="button" class="admin-notification-dismiss" aria-label="Melding sluiten">×</button>
                        </div>
                    @endforeach
                </div>
                <script>
                (function () {
                    'use strict';
                    var center = document.getElementById('adminNotificationCenter');
                    if (!center) return;

                    // Dismissals are per-browser-session only (sessionStorage) —
                    // no server round-trip needed for a purely visual dismiss.
                    center.querySelectorAll('.admin-notification').forEach(function (item) {
                        var id = item.dataset.notificationId;
                        if (sessionStorage.getItem('dismissed-' + id)) {
                            item.remove();
                        }
                    });

                    center.addEventListener('click', function (e) {
                        var btn = e.target.closest('.admin-notification-dismiss');
                        if (!btn) return;
                        var item = btn.closest('.admin-notification');
                        sessionStorage.setItem('dismissed-' + item.dataset.notificationId, '1');
                        item.remove();
                    });
                })();
                </script>
            @endif

            {{-- Dashboard widgets --}}
            <div class="admin-dashboard-grid">
                <a class="admin-dashboard-card" href="{{ route('admin.requests.index') }}">
                    <span class="admin-dashboard-number">{{ $todaysFollowUps }}</span>
                    <span class="admin-dashboard-label">Opvolgingen vandaag</span>
                </a>
                <span class="admin-dashboard-card">
                    <span class="admin-dashboard-number">{{ $quotesAwaitingAnswer }}</span>
                    <span class="admin-dashboard-label">Offertes wachten op antwoord</span>
                </span>
                <span class="admin-dashboard-card">
                    <span class="admin-dashboard-number">{{ $openQuotes }}</span>
                    <span class="admin-dashboard-label">Open offertes</span>
                </span>
                <a class="admin-dashboard-card" href="{{ route('admin.requests.index', ['status' => 'won']) }}">
                    <span class="admin-dashboard-number">{{ $wonThisMonth }}</span>
                    <span class="admin-dashboard-label">Gewonnen deze maand</span>
                </a>
                <a class="admin-dashboard-card" href="{{ route('admin.requests.index', ['status' => 'lost']) }}">
                    <span class="admin-dashboard-number">{{ $lostThisMonth }}</span>
                    <span class="admin-dashboard-label">Verloren deze maand</span>
                </a>
            </div>

            {{-- Recent activity --}}
            @if (!empty($recentActivity))
                <div class="admin-panel">
                    <div class="admin-panel-header">
                        <div><h2>Recente activiteit</h2></div>
                    </div>
                    <ul class="admin-activity-list">
                        @foreach ($recentActivity as $item)
                            <li>
                                <span class="admin-activity-date">{{ $item['date']->format('d/m/Y H:i') }}</span>
                                <span class="admin-activity-action">
                                    <a href="{{ route('admin.requests.show', $item['request']) }}">{{ $item['action'] }}</a>
                                </span>
                                @if ($item['note'])
                                    <span class="admin-activity-note">{{ \Illuminate\Support\Str::limit($item['note'], 120) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Statistics --}}
            <div class="admin-panel">
                <div class="admin-panel-header">
                    <div><h2>Statistieken</h2></div>
                </div>

                <div class="admin-stats-summary-grid">
                    <div><strong>{{ $statistics['conversionRate'] !== null ? $statistics['conversionRate'] . '%' : '—' }}</strong><span>Conversie (gewonnen/verloren)</span></div>
                    <div><strong>{{ $statistics['wonTotal'] }}</strong><span>Totaal gewonnen</span></div>
                    <div><strong>{{ $statistics['lostTotal'] }}</strong><span>Totaal verloren</span></div>
                    <div><strong>{{ $statistics['avgResponseHours'] !== null ? $statistics['avgResponseHours'] . ' u' : '—' }}</strong><span>Gem. reactietijd</span></div>
                    <div><strong>{{ $statistics['quotesCreated'] }}</strong><span>Offertes aangemaakt</span></div>
                </div>

                @php $maxMonth = max(array_merge($statistics['requestsPerMonth'], [1])); @endphp
                <h3 class="admin-chart-heading">Aanvragen per maand</h3>
                <div class="admin-bar-chart">
                    @foreach ($statistics['requestsPerMonth'] as $month => $count)
                        <div class="admin-bar-chart-col">
                            <div class="admin-bar-chart-bar" style="height: {{ $maxMonth > 0 ? max(4, ($count / $maxMonth) * 100) : 4 }}px;" title="{{ $count }}"></div>
                            <span class="admin-bar-chart-label">{{ $month }}</span>
                            <span class="admin-bar-chart-value">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($statistics['requestsByService']->isNotEmpty())
                    @php $maxService = $statistics['requestsByService']->max(); @endphp
                    <h3 class="admin-chart-heading">Aanvragen per dienst</h3>
                    <div class="admin-hbar-chart">
                        @foreach ($statistics['requestsByService'] as $label => $count)
                            <div class="admin-hbar-row">
                                <span class="admin-hbar-label">{{ $label }}</span>
                                <div class="admin-hbar-track">
                                    <div class="admin-hbar-fill" style="width: {{ $maxService > 0 ? ($count / $maxService) * 100 : 0 }}%;"></div>
                                </div>
                                <span class="admin-hbar-value">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <h2>Nieuwe aanvragen</h2>
                        <p>{{ $customerRequests->count() }} aanvraag/aanvragen gevonden.</p>
                    </div>
                    <div>
                        <a class="button button-secondary"
                           href="{{ route('admin.requests.export', request()->query()) }}"
                           title="Exporteer huidige weergave als CSV">
                            ↓ CSV exporteren
                        </a>
                    </div>
                </div>

                <details class="admin-filter-details" {{ collect($filters)->filter()->isNotEmpty() ? 'open' : '' }}>
                    <summary>
                        Filters

                        @if (collect($filters)->filter()->isNotEmpty())
                            <span>actief</span>
                        @endif
                    </summary>

                    <form class="admin-filter-form" method="GET" action="{{ route('admin.requests.index') }}">
                        <label>
                            <span>Zoeken</span>
                            <input type="text" name="search" value="{{ $filters['search'] }}"
                                placeholder="Naam, e-mail of telefoon">
                        </label>

                        <label>
                            <span>Status</span>
                            <select name="status">
                                <option value="">Alle statussen</option>

                                @foreach ($statuses as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}"
                                        {{ $filters['status'] === $statusValue ? 'selected' : '' }}>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Dienst</span>
                            <select name="service_slug">
                                <option value="">Alle diensten</option>

                                @foreach ($services as $service)
                                    <option value="{{ $service['slug'] }}"
                                        {{ $filters['service_slug'] === $service['slug'] ? 'selected' : '' }}>
                                        {{ $service['title'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Categorie</span>
                            <select name="service_category">
                                <option value="">Alle categorieën</option>

                                @foreach ($serviceCategoryLabels as $catValue => $catLabel)
                                    <option value="{{ $catValue }}"
                                        {{ $filters['service_category'] === $catValue ? 'selected' : '' }}>
                                        {{ $catLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Type aanvraag</span>
                            <select name="request_type">
                                <option value="">Alle types</option>

                                @foreach ($requestTypes as $requestTypeValue => $requestTypeLabel)
                                    <option value="{{ $requestTypeValue }}"
                                        {{ $filters['request_type'] === $requestTypeValue ? 'selected' : '' }}>
                                        {{ $requestTypeLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Urgentie</span>
                            <select name="urgency">
                                <option value="">Alle urgenties</option>

                                @foreach ($urgencies as $urgencyValue => $urgencyLabel)
                                    <option value="{{ $urgencyValue }}"
                                        {{ $filters['urgency'] === $urgencyValue ? 'selected' : '' }}>
                                        {{ $urgencyLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Klanttype</span>
                            <select name="customer_type">
                                <option value="">Alle klanttypes</option>

                                @foreach ($customerTypes as $customerTypeValue => $customerTypeLabel)
                                    <option value="{{ $customerTypeValue }}"
                                        {{ $filters['customer_type'] === $customerTypeValue ? 'selected' : '' }}>
                                        {{ $customerTypeLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Offerte</span>
                            <select name="has_quote">
                                <option value="">Alle</option>
                                <option value="yes" {{ $filters['has_quote'] === 'yes' ? 'selected' : '' }}>Heeft offerte</option>
                                <option value="no" {{ $filters['has_quote'] === 'no' ? 'selected' : '' }}>Geen offerte</option>
                            </select>
                        </label>

                        <label>
                            <span>Van datum</span>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                        </label>

                        <label>
                            <span>Tot datum</span>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                        </label>

                        <div class="admin-filter-actions">
                            <button class="button button-primary" type="submit">
                                Filteren
                            </button>

                            <a class="button button-secondary" href="{{ route('admin.requests.index') }}">
                                Reset
                            </a>
                        </div>
                    </form>
                </details>

                @if ($customerRequests->isEmpty())
                    <div class="admin-empty">
                        Geen aanvragen gevonden met deze filters.
                    </div>
                @else
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Naam</th>
                                    <th>E-mail</th>
                                    <th>Telefoon</th>
                                    <th>Aanvraag</th>
                                    <th>Urgentie</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($customerRequests as $request)
                                    @php
                                        $metadata = $request->metadata ?? [];
                                        $answers  = $metadata['answers'] ?? [];

                                        // Service category label: prefer new DB column, fall back to old metadata
                                        $categoryLabel = null;
                                        if ($request->service_category) {
                                            $categoryLabel = $serviceCategoryLabels[$request->service_category] ?? $request->service_category;
                                        } else {
                                            $categoryLabel = $metadata['service']['title'] ?? $request->service_slug;
                                        }

                                        // Urgency: prefer new urgency_level column, fall back to old answers.urgency
                                        $urgencyLevel = $request->urgency_level ?? ($answers['urgency'] ?? null);

                                        $urgencyLevelLabels = [
                                            'water_leaking' => 'Water / lek',
                                            'small_leak'    => 'Klein lek',
                                            'no_heating'    => 'Geen verwarming',
                                            'no_hot_water'  => 'Geen warm water',
                                            'other'         => 'Andere urgentie',
                                            'urgent'        => 'Dringend',
                                            'within_days'   => 'Enkele dagen',
                                            'not_urgent'    => 'Niet dringend',
                                        ];
                                        $urgencyLabel = $urgencyLevelLabels[$urgencyLevel] ?? null;
                                    @endphp

                                    <tr>
                                        <td data-label="Datum">{{ $request->created_at->format('d/m/Y H:i') }}</td>
                                        <td data-label="Naam">{{ $request->customer_name }}</td>
                                        <td data-label="E-mail">{{ $request->customer_email }}</td>
                                        <td data-label="Telefoon">{{ $request->customer_phone ?: '-' }}</td>
                                        <td data-label="Aanvraag">{{ $categoryLabel }}</td>
                                        <td data-label="Urgentie">
                                            @if ($urgencyLevel)
                                                <span class="admin-urgency admin-urgency-{{ $urgencyLevel }}">
                                                    {{ $urgencyLabel }}
                                                </span>
                                            @else
                                                <span class="admin-urgency admin-urgency-none">-</span>
                                            @endif
                                        </td>
                                        <td data-label="Status">
                                            <span class="admin-status admin-status-{{ $request->status }}">
                                                {{ $statuses[$request->status] ?? $request->status }}
                                            </span>
                                        </td>
                                        <td data-label="">
                                            <a class="admin-link" href="{{ route('admin.requests.show', $request) }}">
                                                Bekijken
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
