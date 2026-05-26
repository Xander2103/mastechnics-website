@extends('layouts.app')

@section('title', 'Admin | Aanvragen')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Aanvragen</h1>
            <p>Overzicht van alle aanvragen die via het slimme aanvraagformulier zijn binnengekomen.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
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
                            <span>Van datum</span>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                        </label>

                        <label>
                            <span>Tot datum</span>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                        </label>

                        <div class="admin-filter-actions">
                            <button class="button button-primary" type="submit">
                                Zoeken
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
                                    <th>Dienst</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($customerRequests as $request)
                                    @php
                                        $metadata = $request->metadata ?? [];
                                        $serviceTitle = $metadata['service']['title'] ?? $request->service_slug;
                                        $requestTypeLabel =
                                            $metadata['request_type']['label'] ?? $request->request_type;
                                    @endphp

                                    <tr>
                                        <td>{{ $request->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ $request->customer_name }}</td>
                                        <td>{{ $request->customer_email }}</td>
                                        <td>{{ $request->customer_phone ?: '-' }}</td>
                                        <td>{{ $serviceTitle }}</td>
                                        <td>{{ $requestTypeLabel }}</td>
                                        <td>
                                            <span class="admin-status admin-status-{{ $request->status }}">
                                                {{ $statuses[$request->status] ?? $request->status }}
                                            </span>
                                        </td>
                                        <td>
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
