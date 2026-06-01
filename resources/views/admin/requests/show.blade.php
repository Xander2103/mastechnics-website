@extends('layouts.app')

@section('title', 'Admin | Aanvraag bekijken')

<style>
.admin-whatsapp-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #25d366;
    text-decoration: none;
    margin-left: 0.5rem;
    font-weight: 600;
}
.admin-whatsapp-link:hover {
    text-decoration: underline;
}
</style>

@section('content')
    @php
        $metadata = $customerRequest->metadata ?? [];
        $answers = $metadata['answers'] ?? [];

        $serviceTitle = $metadata['service']['title'] ?? $customerRequest->service_slug;
        $requestTypeLabel = $metadata['request_type']['label'] ?? $customerRequest->request_type;

        $customerTypeLabels = [
            'residential' => 'Particulier',
            'business' => 'Bedrijf',
        ];

        $urgencyLabels = [
            'urgent' => 'Dringend',
            'within_days' => 'Binnen enkele dagen',
            'not_urgent' => 'Niet dringend',
        ];

        $customerType = $answers['customer_type'] ?? null;
        $urgency = $answers['urgency'] ?? null;

        $serviceCategoryLabel = null;
        if ($customerRequest->service_category) {
            $serviceCategoryLabel = $serviceCategoryLabels[$customerRequest->service_category]
                ?? $customerRequest->service_category;
        }

        $urgencyLevelLabels = [
            'water_leaking' => 'Er staat water / ernstig lek',
            'small_leak'    => 'Klein lek',
            'no_heating'    => 'Geen verwarming',
            'no_hot_water'  => 'Geen warm water',
            'other'         => 'Andere urgentie',
            'urgent'        => 'Dringend (algemeen)',
            'within_days'   => 'Binnen enkele dagen',
            'not_urgent'    => 'Niet dringend',
        ];
        $urgencyLevel = $customerRequest->urgency_level ?? ($answers['urgency'] ?? null);
        $urgencyLabel = $urgencyLevelLabels[$urgencyLevel] ?? null;
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
            <div class="admin-back-row">
                <a class="button button-secondary admin-back-button" href="{{ route('admin.requests.index') }}">
                    ← Terug naar overzicht
                </a>
            </div>

            @if (session('success') === 'status_updated')
                <div class="form-success">
                    Status werd opgeslagen.
                </div>
            @endif

            @if (session('success') === 'note_created')
                <div class="form-success">
                    Notitie werd toegevoegd.
                </div>
            @endif

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
                            <dd>
                                {{ $customerRequest->customer_phone ?: '-' }}
                                @if ($customerRequest->customer_phone)
                                    @php
                                        $rawPhone = $customerRequest->customer_phone;
                                        // Step 1: trim whitespace
                                        $normalized = trim($rawPhone);
                                        // Step 2: remove spaces, slashes, dots, dashes, parentheses
                                        $normalized = preg_replace('/[\s\.\-\/\(\)]/', '', $normalized);
                                        // Step 3–6: normalize prefix to international format
                                        if (str_starts_with($normalized, '+')) {
                                            $normalized = substr($normalized, 1);
                                        } elseif (str_starts_with($normalized, '00')) {
                                            $normalized = substr($normalized, 2);
                                        } elseif (str_starts_with($normalized, '0')) {
                                            $normalized = '32' . substr($normalized, 1);
                                        }
                                        // Step 7: result is digits only starting with country code
                                        $waMessage = rawurlencode('Dag ' . $customerRequest->customer_name . ', bedankt voor uw aanvraag via Mastechnics. Ik contacteer u even over uw aanvraag.');
                                        $waUrl = 'https://wa.me/' . $normalized . '?text=' . $waMessage;
                                    @endphp
                                    <a class="admin-whatsapp-link" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#25d366" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        WhatsApp ↗
                                    </a>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt>Klanttype</dt>
                            <dd>{{ $customerTypeLabels[$customerType] ?? '-' }}</dd>
                        </div>

                        <div>
                            <dt>Urgentie</dt>
                            <dd>
                                <span class="admin-urgency admin-urgency-{{ $urgencyLevel ?: 'none' }}">
                                    {{ $urgencyLabel ?? '-' }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>Status</dt>
                            <dd>
                                <span class="admin-status admin-status-{{ $customerRequest->status }}">
                                    {{ $statuses[$customerRequest->status] ?? $customerRequest->status }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>Datum</dt>
                            <dd>{{ $customerRequest->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    </dl>

                    <form class="admin-status-form" method="POST"
                        action="{{ route('admin.requests.update-status', $customerRequest) }}">
                        @csrf
                        @method('PATCH')

                        <label>
                            <span>Status aanpassen</span>

                            <select name="status">
                                @foreach ($statuses as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}"
                                        {{ $customerRequest->status === $statusValue ? 'selected' : '' }}>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        @error('status')
                            <p class="field-error-text">{{ $message }}</p>
                        @enderror

                        <button class="button button-primary" type="submit">
                            Status opslaan
                        </button>
                    </form>
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

                            @if ($serviceCategoryLabel)
                                <div>
                                    <dt>Aanvraagcategorie</dt>
                                    <dd>{{ $serviceCategoryLabel }}</dd>
                                </div>
                            @endif

                            @if ($urgencyLevel)
                                <div>
                                    <dt>Urgentieniveau</dt>
                                    <dd>
                                        <span class="admin-urgency admin-urgency-{{ $urgencyLevel }}">
                                            {{ $urgencyLabel }}
                                        </span>
                                    </dd>
                                </div>
                            @endif

                            @if ($customerRequest->preferred_time)
                                <div>
                                    <dt>Gewenst moment</dt>
                                    <dd>{{ $customerRequest->preferred_time }}</dd>
                                </div>
                            @endif

                            <div>
                                <dt>Beschrijving</dt>
                                <dd>{{ $customerRequest->description }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="admin-detail-card">
                        <h2>Locatie en beschikbaarheid</h2>

                        <dl class="admin-detail-list">
                            <div>
                                <dt>Straat</dt>
                                <dd>{{ $answers['street'] ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Postcode</dt>
                                <dd>{{ $answers['postal_code'] ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Gemeente</dt>
                                <dd>{{ $answers['city'] ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Beschikbaarheid</dt>
                                <dd>{{ $answers['availability'] ?? '-' }}</dd>
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
                                <dt>Merk/model/serienummer onbekend</dt>
                                <dd>{{ $customerRequest->unknown_device_details ? 'Ja' : 'Nee' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="admin-detail-card">
                        <h2>Bijlagen</h2>

                        @if ($customerRequest->attachments->isEmpty())
                            <p>Geen bijlagen toegevoegd.</p>
                        @else
                            <div class="admin-attachments-grid">
                                @foreach ($customerRequest->attachments as $attachment)
                                    <a class="admin-attachment-card" href="{{ asset('storage/' . $attachment->path) }}"
                                        target="_blank" rel="noopener">
                                        @if (str_starts_with($attachment->mime_type ?? '', 'image/'))
                                            <img src="{{ asset('storage/' . $attachment->path) }}"
                                                alt="{{ $attachment->original_name }}">
                                        @else
                                            <div class="admin-attachment-file">
                                                Bestand
                                            </div>
                                        @endif

                                        <span>{{ $attachment->original_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="admin-detail-card admin-notes-card">
                <h2>Interne notities</h2>

                <form class="admin-note-form" method="POST"
                    action="{{ route('admin.requests.notes.store', $customerRequest) }}">
                    @csrf

                    <label>
                        <span>Nieuwe notitie</span>

                        <textarea name="body" rows="4"
                            placeholder="Bijvoorbeeld: klant gebeld, offerte doorgestuurd, wacht op extra foto...">{{ old('body') }}</textarea>
                    </label>

                    @error('body')
                        <p class="field-error-text">{{ $message }}</p>
                    @enderror

                    <button class="button button-primary" type="submit">
                        Notitie toevoegen
                    </button>
                </form>

                @if ($customerRequest->notes->isEmpty())
                    <p class="admin-muted-text">Er zijn nog geen notities voor deze aanvraag.</p>
                @else
                    <div class="admin-notes-list">
                        @foreach ($customerRequest->notes as $note)
                            <article class="admin-note-item">
                                <div class="admin-note-meta">
                                    <strong>{{ $note->author_email ?: 'Admin' }}</strong>
                                    <span>{{ $note->created_at->format('d/m/Y H:i') }}</span>
                                </div>

                                <p>{{ $note->body }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="admin-detail-card admin-answers-card">
                <h2>Alle antwoorden</h2>

                <dl class="admin-answers-grid">
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
        </div>
    </section>
@endsection
