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
                    <span class="admin-stat-label">Te contacteren</span>
                </a>
                <a class="admin-stat-card" href="{{ route('admin.requests.index', ['status' => 'planned']) }}">
                    <span class="admin-stat-number">{{ $stats['planned'] }}</span>
                    <span class="admin-stat-label">Ingepland</span>
                </a>
            </div>

            <div class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <h2>Nieuwe aanvragen</h2>
                        <p>{{ $customerRequests->count() }} aanvraag/aanvragen gevonden.</p>
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
