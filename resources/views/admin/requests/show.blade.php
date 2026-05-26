@extends('layouts.app')

@section('title', 'Admin | Aanvraag bekijken')

@section('content')
    @php
        $metadata = $customerRequest->metadata ?? [];
        $answers = $metadata['answers'] ?? [];
        $serviceTitle = $metadata['service']['title'] ?? $customerRequest->service_slug;
        $requestTypeLabel = $metadata['request_type']['label'] ?? $customerRequest->request_type;
    @endphp

    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Aanvraag bekijken</h1>
            <p>Detail van de aanvraag van {{ $customerRequest->customer_name }}.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            <div class="admin-detail-layout">
                <aside class="admin-detail-card">
                    <h2>Klantgegevens</h2>

                    <dl class="admin-detail-list">
                        <div>
                            <dt>Naam</dt>
                            <dd>{{ $customerRequest->customer_name }}</dd>
                        </div>

                        <div>
                            <dt>E-mail</dt>
                            <dd>
                                <a href="mailto:{{ $customerRequest->customer_email }}">
                                    {{ $customerRequest->customer_email }}
                                </a>
                            </dd>
                        </div>

                        <div>
                            <dt>Telefoon</dt>
                            <dd>{{ $customerRequest->customer_phone ?: '-' }}</dd>
                        </div>

                        <div>
                            <dt>Status</dt>
                            <dd>
                                <span class="admin-status admin-status-new">
                                    {{ $customerRequest->status }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>Datum</dt>
                            <dd>{{ $customerRequest->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    </dl>
                </aside>

                <div class="admin-detail-main">
                    <div class="admin-detail-card">
                        <h2>Aanvraag</h2>

                        <dl class="admin-detail-list">
                            <div>
                                <dt>Dienst</dt>
                                <dd>{{ $serviceTitle }}</dd>
                            </div>

                            <div>
                                <dt>Type aanvraag</dt>
                                <dd>{{ $requestTypeLabel }}</dd>
                            </div>

                            <div>
                                <dt>Beschrijving</dt>
                                <dd>{{ $customerRequest->description }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="admin-detail-card">
                        <h2>Technische gegevens</h2>

                        <dl class="admin-detail-list">
                            <div>
                                <dt>Merk</dt>
                                <dd>{{ $customerRequest->brand ?: '-' }}</dd>
                            </div>

                            <div>
                                <dt>Model</dt>
                                <dd>{{ $customerRequest->device_model ?: '-' }}</dd>
                            </div>

                            <div>
                                <dt>Serienummer</dt>
                                <dd>{{ $customerRequest->serial_number ?: '-' }}</dd>
                            </div>

                            <div>
                                <dt>Ik weet dit niet</dt>
                                <dd>{{ $customerRequest->unknown_device_details ? 'Ja' : 'Nee' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="admin-detail-card">
                        <h2>Alle antwoorden</h2>

                        <dl class="admin-detail-list">
                            @foreach ($answers as $key => $value)
                                <div>
                                    <dt>{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                                    <dd>
                                        @if (is_bool($value))
                                            {{ $value ? 'Ja' : 'Nee' }}
                                        @elseif (is_array($value))
                                            <pre>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @else
                                            {{ $value ?: '-' }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="button-row">
                        <a class="button button-secondary" href="{{ route('admin.requests.index') }}">
                            Terug naar overzicht
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection