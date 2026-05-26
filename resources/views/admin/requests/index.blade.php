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

                @if ($customerRequests->isEmpty())
                    <div class="admin-empty">
                        Er zijn nog geen aanvragen.
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
                                        $requestTypeLabel = $metadata['request_type']['label'] ?? $request->request_type;
                                    @endphp

                                    <tr>
                                        <td>{{ $request->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ $request->customer_name }}</td>
                                        <td>{{ $request->customer_email }}</td>
                                        <td>{{ $request->customer_phone ?: '-' }}</td>
                                        <td>{{ $serviceTitle }}</td>
                                        <td>{{ $requestTypeLabel }}</td>
                                        <td>
                                            <span class="admin-status admin-status-new">
                                                {{ $request->status }}
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