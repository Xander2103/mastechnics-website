@extends('layouts.app')

@section('title', 'Admin | Aanvraag bekijken')

@section('content')
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
    .admin-missing-list {
        margin: 0;
        padding: 0 0 0 1.25rem;
        color: #b45309;
    }
    .admin-missing-list li {
        margin-bottom: 0.25rem;
    }
    .admin-missing-info-card h2 {
        margin-bottom: 0.5rem;
    }
    .admin-snel-bericht {
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }
    .admin-snel-bericht h3 {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }
    .admin-snel-bericht-content {
        font-size: 0.9rem;
        line-height: 1.5;
        color: #1f2937;
        margin-bottom: 0.5rem;
        white-space: pre-wrap;
    }
    .admin-copy-btn {
        font-size: 0.8rem;
        padding: 0.3rem 0.75rem;
    }
    .admin-copy-feedback {
        margin-left: 0.5rem;
        font-size: 0.8rem;
        color: #059669;
    }
    .admin-detail-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .admin-quick-actions-card {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.25rem;
    }
    .admin-quick-actions-card .admin-back-button {
        display: block;
        margin-bottom: 1rem;
        width: 100%;
        text-align: center;
    }
    .admin-whatsapp-quick {
        display: inline-flex;
        margin-bottom: 0.5rem;
    }
    </style>
    @php
        $metadata = $customerRequest->metadata ?? [];
        $answers = $metadata['answers'] ?? [];

        $serviceTitle = $metadata['service']['title'] ?? $customerRequest->service_slug;
        $requestTypeLabel = $metadata['request_type']['label'] ?? $customerRequest->request_type;

        $customerTypeLabels = [
            'residential' => 'Particulier',
            'business' => 'Bedrijf',
        ];

        $customerType = $answers['customer_type'] ?? null;
        $urgency = $answers['urgency'] ?? null;

        $serviceCategoryLabels = $serviceCategoryLabels ?? [];
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

        // WhatsApp URL
        $waUrl = null;
        $waNormalized = '';
        if ($customerRequest->customer_phone) {
            $waNormalized = trim($customerRequest->customer_phone);
            $waNormalized = preg_replace('/[\s\.\-\/\(\)]/', '', $waNormalized);
            if (str_starts_with($waNormalized, '+')) {
                $waNormalized = substr($waNormalized, 1);
            } elseif (str_starts_with($waNormalized, '00')) {
                $waNormalized = substr($waNormalized, 2);
            } elseif (str_starts_with($waNormalized, '0')) {
                $waNormalized = '32' . substr($waNormalized, 1);
            }
            $waNormalized = preg_replace('/\D/', '', $waNormalized);
            if (strlen($waNormalized) > 5) {
                $waMessage = rawurlencode('Dag ' . $customerRequest->customer_name . ', bedankt voor uw aanvraag via Mastechnics. Ik contacteer u even over uw aanvraag.');
                $waUrl = 'https://wa.me/' . $waNormalized . '?text=' . $waMessage;
            }
        }
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

            @if (session('success') === 'action_applied')
                <div class="form-success">
                    Status werd bijgewerkt.
                </div>
            @endif

            @if (session('success') === 'internal_notes_updated')
                <div class="form-success">
                    Memo werd opgeslagen.
                </div>
            @endif

            @if (session('success') === 'quote_saved')
                <div class="form-success">
                    Offerte werd opgeslagen.
                </div>
            @endif

            @if (session('success') === 'quote_action_applied')
                <div class="form-success">
                    Offerte-status werd bijgewerkt.
                </div>
            @endif

            @if (session('success') === 'quote_email_sent')
                <div class="form-success">
                    Offerte werd verstuurd naar de klant.
                </div>
            @endif

            @if (session('success') === 'quote_email_failed')
                <div class="form-error-list">
                    De offerte-status werd bijgewerkt, maar de e-mail kon niet verstuurd worden. Controleer het e-mailadres of probeer later opnieuw.
                </div>
            @endif

            @if (session('success') === 'note_updated')
                <div class="form-success">Notitie werd bijgewerkt.</div>
            @endif

            @if (session('success') === 'note_deleted')
                <div class="form-success">Notitie werd verwijderd.</div>
            @endif

            @if (session('success') === 'appointment_created')
                <div class="form-success">Afspraak werd toegevoegd.</div>
            @endif

            <div class="admin-detail-layout">

                {{-- ===================== LEFT SIDEBAR ===================== --}}
                <aside class="admin-detail-sidebar">

                    {{-- Group 1: Snelle acties --}}
                    <div class="admin-quick-actions-card">
                        <a class="button button-secondary admin-back-button" href="{{ route('admin.requests.index') }}">
                            ← Terug naar overzicht
                        </a>

                        @if ($waUrl)
                            <a class="admin-whatsapp-link admin-whatsapp-quick" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#25d366" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp {{ $customerRequest->customer_name }} ↗
                            </a>
                        @endif

                        {{-- Current status (read-only badge) --}}
                        <div class="admin-current-status-row" style="margin-top: 16px; margin-bottom: 4px;">
                            <span class="admin-current-status-label">Huidige status</span>
                            <span class="admin-status admin-status-{{ $customerRequest->status }}">
                                {{ $statuses[$customerRequest->status] ?? $customerRequest->status }}
                            </span>
                        </div>

                        {{-- Quick-action buttons --}}
                        <div class="admin-quick-action-grid">
                            {{-- Bekeken: only enabled when status is 'new' --}}
                            @if ($customerRequest->status === 'new')
                                <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="mark_viewed">
                                    <button type="submit" class="admin-quick-action-btn">Bekeken</button>
                                </form>
                            @else
                                <button type="button" class="admin-quick-action-btn admin-quick-action-disabled" disabled>Bekeken</button>
                            @endif

                            <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
                                @csrf
                                <input type="hidden" name="action" value="mark_contacted">
                                <button type="submit" class="admin-quick-action-btn">Gecontacteerd</button>
                            </form>

                            <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}">
                                @csrf
                                <input type="hidden" name="action" value="mark_quote_sent">
                                <button type="submit" class="admin-quick-action-btn">Offerte verstuurd</button>
                            </form>

                            <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}"
                                onsubmit="return confirm('Deze aanvraag markeren als gewonnen?');">
                                @csrf
                                <input type="hidden" name="action" value="mark_won">
                                <button type="submit" class="admin-quick-action-btn admin-quick-action-won">Gewonnen</button>
                            </form>

                            <form method="POST" action="{{ route('admin.requests.action', $customerRequest) }}"
                                onsubmit="return confirm('Deze aanvraag markeren als verloren?');">
                                @csrf
                                <input type="hidden" name="action" value="mark_lost">
                                <button type="submit" class="admin-quick-action-btn admin-quick-action-lost">Verloren</button>
                            </form>
                        </div>

                        @php
                            $snelBerichtCat = $serviceCategoryLabels[$customerRequest->service_category] ?? null;
                            $snelBericht = 'Dag ' . $customerRequest->customer_name . ', bedankt voor uw aanvraag via Mastechnics. Ik contacteer u even over uw aanvraag'
                                . ($snelBerichtCat ? ' voor ' . $snelBerichtCat . '.' : '.');
                        @endphp

                        <div class="admin-snel-bericht">
                            <h3>Snel bericht</h3>
                            <p id="admin-snel-bericht-text" class="admin-snel-bericht-content">{{ $snelBericht }}</p>
                            <button
                                type="button"
                                class="button button-secondary admin-copy-btn"
                                data-copy-target="admin-snel-bericht-text"
                                aria-label="Bericht kopiëren"
                            >
                                Kopiëren
                            </button>
                            <span class="admin-copy-feedback" aria-live="polite"></span>
                        </div>
                    </div>

                    {{-- Group 2: Klantgegevens --}}
                    <div class="admin-detail-card">
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
                                    @if ($waUrl)
                                        <a class="admin-whatsapp-link" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#25d366" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                            WhatsApp ↗
                                        </a>
                                    @endif
                                </dd>
                            </div>

                            @if ($customerType)
                                <div>
                                    <dt>Klanttype</dt>
                                    <dd>{{ $customerTypeLabels[$customerType] ?? $customerType }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    {{-- Internal memo (fixed short note, not the follow-up log) --}}
                    <div class="admin-detail-card admin-internal-notes-card">
                        <h2>Interne memo</h2>
                        <form method="POST"
                            action="{{ route('admin.requests.internal-notes.update', $customerRequest) }}">
                            @csrf
                            @method('PATCH')

                            <label>
                                <span>Korte interne samenvatting</span>
                                <textarea name="internal_notes" rows="4" maxlength="2000"
                                    placeholder="Bijv. offerte doorgestuurd, klant wacht op keuring...">{{ old('internal_notes', $customerRequest->internal_notes) }}</textarea>
                            </label>

                            @error('internal_notes')
                                <p class="field-error-text">{{ $message }}</p>
                            @enderror

                            <button class="button button-secondary" type="submit">
                                Memo opslaan
                            </button>
                        </form>
                    </div>

                </aside>

                {{-- ===================== MAIN AREA ===================== --}}
                <div class="admin-detail-main">

                    {{-- Summary block — rendered first in main column --}}
                    @php $summaryLines = $customerRequest->getSummaryLines(); @endphp
                    @if (! empty($summaryLines))
                        <div class="admin-detail-card admin-summary-block">
                            <h2>Samenvatting</h2>
                            <ul class="admin-summary-list">
                                @foreach ($summaryLines as $line)
                                    <li class="admin-summary-line">{{ $line }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Quote card --}}
                    @php
                        $quote = $customerRequest->quote;
                        $quoteItemCount = $quote ? $quote->items()->count() : 0;
                    @endphp
                    <div class="admin-detail-card admin-quote-card">
                        <div class="admin-quote-card-header">
                            <h2>Offerte</h2>
                            @if ($quote)
                                @php
                                    $quoteStatusLabels = [
                                        'draft'    => 'Concept',
                                        'sent'     => 'Verstuurd',
                                        'accepted' => 'Aanvaard',
                                        'rejected' => 'Afgewezen',
                                    ];
                                @endphp
                                <span class="admin-quote-status admin-quote-status-{{ $quote->quote_status }}">
                                    {{ $quoteStatusLabels[$quote->quote_status] ?? $quote->quote_status }}
                                </span>
                            @endif
                        </div>

                        @if (! $quote)
                            <p class="admin-muted-text">Nog geen offerte aangemaakt voor deze aanvraag.</p>
                            <div style="margin-top: 14px;">
                                <a class="button button-primary"
                                   href="{{ route('admin.requests.quote.edit', $customerRequest) }}">
                                    + Offerte aanmaken
                                </a>
                            </div>
                        @else
                            {{-- Meta: number + valid until --}}
                            <div class="admin-quote-meta-row">
                                @if ($quote->quote_number)
                                    <span class="admin-quote-number">{{ $quote->quote_number }}</span>
                                @endif
                                @if ($quote->valid_until)
                                    <span class="admin-quote-valid">Geldig t/m {{ $quote->valid_until->format('d/m/Y') }}</span>
                                @endif
                            </div>

                            @if ($quote->title)
                                <p class="admin-quote-title">{{ $quote->title }}</p>
                            @endif

                            @if ($quoteItemCount > 0)
                                <p class="admin-muted-text" style="margin-bottom: 8px; font-size: 0.82rem;">
                                    {{ $quoteItemCount }} {{ $quoteItemCount === 1 ? 'offerteregel' : 'offerteregels' }}
                                </p>
                            @endif

                            {{-- Amounts --}}
                            @if ($quote->amount_incl_vat !== null)
                                <div class="admin-quote-amounts">
                                    <div class="admin-quote-amount-row">
                                        <span>Excl. BTW</span>
                                        <span>€&nbsp;{{ number_format((float) $quote->amount_excl_vat, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="admin-quote-amount-row">
                                        <span>BTW</span>
                                        <span>€&nbsp;{{ number_format((float) $quote->amount_vat, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="admin-quote-amount-row admin-quote-amount-total">
                                        <span>Incl. BTW</span>
                                        <span>€&nbsp;{{ number_format((float) $quote->amount_incl_vat, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- Timestamps --}}
                            @if ($quote->sent_at || $quote->accepted_at || $quote->rejected_at)
                                <div class="admin-quote-timestamps">
                                    @if ($quote->sent_at)
                                        <div><span>Verstuurd:</span> {{ $quote->sent_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                    @if ($quote->accepted_at)
                                        <div><span>Aanvaard:</span> {{ $quote->accepted_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                    @if ($quote->rejected_at)
                                        <div><span>Afgewezen:</span> {{ $quote->rejected_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                </div>
                            @endif

                            {{-- Actions --}}
                            <div class="admin-quote-actions">
                                <a class="button button-secondary"
                                   href="{{ route('admin.requests.quote.edit', $customerRequest) }}">
                                    ✏ Bewerken
                                </a>

                                <a class="button button-secondary"
                                   href="{{ route('admin.requests.quote.pdf', $customerRequest) }}"
                                   target="_blank">
                                    ↓ PDF
                                </a>

                                @if ($quote->quote_status === 'draft')
                                    <button type="button" class="admin-quick-action-btn" id="sendQuoteBtn"
                                        aria-haspopup="dialog" aria-controls="sendQuoteModal">
                                        ✉ Offerte versturen
                                    </button>
                                @endif

                                @if ($quote->quote_status === 'sent')
                                    <form method="POST" action="{{ route('admin.requests.quote.action', $customerRequest) }}"
                                        onsubmit="return confirm('Deze offerte markeren als gewonnen?');">
                                        @csrf
                                        <input type="hidden" name="action" value="mark_accepted">
                                        <button type="submit" class="admin-quick-action-btn admin-quick-action-won">
                                            Gewonnen ▸
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.requests.quote.action', $customerRequest) }}"
                                        onsubmit="return confirm('Deze offerte markeren als verloren?');">
                                        @csrf
                                        <input type="hidden" name="action" value="mark_rejected">
                                        <button type="submit" class="admin-quick-action-btn admin-quick-action-lost">
                                            Verloren ▸
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($quote && $quote->quote_status === 'draft')
                        @php
                            $quoteEmailDefaults = [
                                'nl' => [
                                    'subject' => 'Uw offerte ' . ($quote->quote_number ?: '') . ' — ' . config('site.name'),
                                    'body' => "Beste {$customerRequest->customer_name},\n\nHierbij bezorgen we u de offerte voor uw aanvraag. U vindt alle details in de bijgevoegde PDF.\n\nHeeft u nog vragen? Neem gerust contact met ons op.\n\nMet vriendelijke groeten,\n" . config('site.name'),
                                ],
                                'fr' => [
                                    'subject' => 'Votre devis ' . ($quote->quote_number ?: '') . ' — ' . config('site.name'),
                                    'body' => "Bonjour {$customerRequest->customer_name},\n\nVeuillez trouver ci-joint le devis pour votre demande. Tous les détails figurent dans le PDF joint.\n\nN'hésitez pas à nous contacter pour toute question.\n\nCordialement,\n" . config('site.name'),
                                ],
                                'en' => [
                                    'subject' => 'Your quote ' . ($quote->quote_number ?: '') . ' — ' . config('site.name'),
                                    'body' => "Hello {$customerRequest->customer_name},\n\nPlease find attached the quote for your request. All details are in the attached PDF.\n\nFeel free to contact us with any questions.\n\nKind regards,\n" . config('site.name'),
                                ],
                            ];
                            $quoteEmailDefault = $quoteEmailDefaults[$customerRequest->locale] ?? $quoteEmailDefaults['nl'];
                        @endphp

                        <div class="reviews-modal-backdrop" id="sendQuoteBackdrop" hidden></div>
                        <div class="reviews-modal" id="sendQuoteModal" role="dialog" aria-modal="true" aria-labelledby="sendQuoteModalTitle" hidden>
                            <div class="reviews-modal-inner">
                                <button type="button" class="reviews-modal-close" id="sendQuoteModalClose" aria-label="Sluiten">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>

                                <h2 id="sendQuoteModalTitle">Offerte versturen</h2>
                                <p class="admin-muted-text">De PDF wordt automatisch als bijlage toegevoegd.</p>

                                <form method="POST" action="{{ route('admin.requests.quote.send-email', $customerRequest) }}" class="admin-quote-send-form">
                                    @csrf

                                    <label>
                                        <span>Aan</span>
                                        <input type="email" name="to" value="{{ $customerRequest->customer_email }}" required>
                                    </label>

                                    <label>
                                        <span>Onderwerp</span>
                                        <input type="text" name="subject" value="{{ $quoteEmailDefault['subject'] }}" maxlength="200" required>
                                    </label>

                                    <label>
                                        <span>Bericht</span>
                                        <textarea name="body" rows="8" maxlength="5000" required>{{ $quoteEmailDefault['body'] }}</textarea>
                                    </label>

                                    <div class="admin-quote-send-actions">
                                        <button type="submit" class="button button-primary">Versturen</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                        (function () {
                            'use strict';
                            var trigger = document.getElementById('sendQuoteBtn');
                            var modal = document.getElementById('sendQuoteModal');
                            var backdrop = document.getElementById('sendQuoteBackdrop');
                            var closeBtn = document.getElementById('sendQuoteModalClose');
                            if (!trigger || !modal || !backdrop || !closeBtn) return;

                            function open() {
                                modal.hidden = false;
                                backdrop.hidden = false;
                                document.body.classList.add('reviews-modal-open');
                                document.addEventListener('keydown', onKeydown);
                                closeBtn.focus();
                            }

                            function close() {
                                modal.hidden = true;
                                backdrop.hidden = true;
                                document.body.classList.remove('reviews-modal-open');
                                document.removeEventListener('keydown', onKeydown);
                                trigger.focus();
                            }

                            function onKeydown(e) {
                                if (e.key === 'Escape') close();
                            }

                            trigger.addEventListener('click', open);
                            closeBtn.addEventListener('click', close);
                            backdrop.addEventListener('click', close);
                        })();
                        </script>
                    @endif

                    {{-- Ontbrekende informatie — only render if there are missing items --}}
                    @php $missingItems = $customerRequest->getMissingInfoChecklist(); @endphp
                    @if (!empty($missingItems))
                        <div class="admin-detail-card admin-missing-info-card">
                            <h2>Ontbrekende informatie</h2>
                            <ul class="admin-missing-list">
                                @foreach ($missingItems as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Aanvraaggegevens --}}
                    <div class="admin-detail-card">
                        <h2>Aanvraaggegevens</h2>

                        <dl class="admin-detail-list">
                            <div>
                                <dt>Datum</dt>
                                <dd>{{ $customerRequest->created_at->format('d/m/Y H:i') }}</dd>
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
                                <dt>Categorie</dt>
                                <dd>{{ $serviceCategoryLabel ?: $serviceTitle }}</dd>
                            </div>

                            <div>
                                <dt>Type aanvraag</dt>
                                <dd>{{ $requestTypeLabel }}</dd>
                            </div>

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

                            @if (!empty($customerRequest->source))
                                <div>
                                    <dt>Bron</dt>
                                    <dd>{{ $customerRequest->source }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    {{-- Omschrijving --}}
                    <div class="admin-detail-card">
                        <h2>Omschrijving</h2>

                        @php
                            $descriptionText = $customerRequest->description
                                ?: ($customerRequest->customer_message ?? null);
                        @endphp

                        @if ($descriptionText)
                            <p>{{ $descriptionText }}</p>
                        @else
                            <p class="admin-muted-text">Geen beschrijving ingevuld.</p>
                        @endif
                    </div>

                    {{-- Locatie — only render if at least one field is filled --}}
                    @php
                        $hasLocation = !empty($answers['street'])
                            || !empty($answers['postal_code'])
                            || !empty($answers['city'])
                            || !empty($answers['availability']);
                    @endphp
                    @if ($hasLocation)
                        <div class="admin-detail-card">
                            <h2>Locatie en beschikbaarheid</h2>

                            <dl class="admin-detail-list">
                                @if (!empty($answers['street']))
                                    <div>
                                        <dt>Straat</dt>
                                        <dd>{{ $answers['street'] }}</dd>
                                    </div>
                                @endif

                                @if (!empty($answers['postal_code']))
                                    <div>
                                        <dt>Postcode</dt>
                                        <dd>{{ $answers['postal_code'] }}</dd>
                                    </div>
                                @endif

                                @if (!empty($answers['city']))
                                    <div>
                                        <dt>Gemeente</dt>
                                        <dd>{{ $answers['city'] }}</dd>
                                    </div>
                                @endif

                                @if (!empty($answers['availability']))
                                    <div>
                                        <dt>Beschikbaarheid</dt>
                                        <dd>{{ $answers['availability'] }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endif

                    {{-- Technische gegevens — only render if relevant data is present --}}
                    @php
                        $hasTechnical = $customerRequest->brand
                            || $customerRequest->device_model
                            || $customerRequest->serial_number
                            || $customerRequest->unknown_device_details;
                        $isAircoOfferte = ($customerRequest->service_category === 'airco_offerte');
                        $rooms = $answers['rooms'] ?? [];
                        $aircoHouseAge = $answers['airco_house_age'] ?? null;
                        $hasRooms = $isAircoOfferte && !empty($rooms) && is_array($rooms);
                        if ($isAircoOfferte && !is_null($aircoHouseAge)) {
                            $hasTechnical = true;
                        }
                    @endphp
                    @if ($hasTechnical || $hasRooms)
                        <div class="admin-detail-card">
                            <h2>Technische gegevens</h2>

                            <dl class="admin-detail-list">
                                @if ($customerRequest->brand)
                                    <div>
                                        <dt>Merk</dt>
                                        <dd>{{ $customerRequest->brand }}</dd>
                                    </div>
                                @endif

                                @if ($customerRequest->device_model)
                                    <div>
                                        <dt>Model</dt>
                                        <dd>{{ $customerRequest->device_model }}</dd>
                                    </div>
                                @endif

                                @if ($customerRequest->serial_number)
                                    <div>
                                        <dt>Serienummer</dt>
                                        <dd>{{ $customerRequest->serial_number }}</dd>
                                    </div>
                                @endif

                                @if ($customerRequest->unknown_device_details)
                                    <div>
                                        <dt>Merk/model/serienummer onbekend</dt>
                                        <dd>Ja</dd>
                                    </div>
                                @endif
                            </dl>

                            @if ($hasRooms)
                                <h3 style="margin-top:1rem;margin-bottom:0.5rem;font-size:0.9rem;font-weight:600;">Kamers</h3>
                                @foreach ($rooms as $room)
                                    <dl class="admin-detail-list" style="margin-bottom:0.75rem;padding-bottom:0.75rem;border-bottom:1px solid #e5e7eb;">
                                        @if (!empty($room['type']))
                                            <div>
                                                <dt>Type</dt>
                                                <dd>{{ $room['type'] }}</dd>
                                            </div>
                                        @endif
                                        @if (!empty($room['width']) || !empty($room['breedte']))
                                            <div>
                                                <dt>Breedte</dt>
                                                <dd>{{ $room['width'] ?? $room['breedte'] }}</dd>
                                            </div>
                                        @endif
                                        @if (!empty($room['length']) || !empty($room['lengte']))
                                            <div>
                                                <dt>Lengte</dt>
                                                <dd>{{ $room['length'] ?? $room['lengte'] }}</dd>
                                            </div>
                                        @endif
                                        @if (isset($room['attic_or_flat_roof']) || isset($room['zolderkamer']))
                                            <div>
                                                <dt>Zolderkamer / plat dak</dt>
                                                <dd>{{ ($room['attic_or_flat_roof'] ?? $room['zolderkamer'] ?? false) ? 'Ja' : 'Nee' }}</dd>
                                            </div>
                                        @endif
                                        @if (isset($room['large_windows']) || isset($room['grote_ramen']))
                                            <div>
                                                <dt>Grote ramen</dt>
                                                <dd>{{ ($room['large_windows'] ?? $room['grote_ramen'] ?? false) ? 'Ja' : 'Nee' }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                @endforeach
                            @endif

                            @if ($isAircoOfferte && !is_null($aircoHouseAge))
                                <dl class="admin-detail-list">
                                    <div>
                                        <dt>Woning ouder dan 10 jaar</dt>
                                        <dd>{{ $aircoHouseAge ? 'Ja' : 'Nee' }}</dd>
                                    </div>
                                </dl>
                            @endif
                        </div>
                    @endif

                    {{-- Bijlagen --}}
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

            {{-- ===================== FULL-WIDTH BELOW LAYOUT ===================== --}}

            {{-- Interne notities --}}
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
                            @php $canEditNote = $note->author_email === session('admin_user_email'); @endphp
                            <article class="admin-note-item">
                                <div class="admin-note-meta">
                                    <strong>{{ $note->author_email ?: 'Admin' }}</strong>
                                    <span>{{ $note->created_at->format('d/m/Y H:i') }}</span>

                                    @if ($canEditNote)
                                        <button type="button" class="admin-note-edit-toggle" data-note-id="{{ $note->id }}">
                                            Bewerken
                                        </button>

                                        <form method="POST" action="{{ route('admin.requests.notes.destroy', [$customerRequest, $note]) }}"
                                            onsubmit="return confirm('Deze notitie verwijderen?');" class="admin-note-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="admin-note-delete-btn">Verwijderen</button>
                                        </form>
                                    @endif
                                </div>

                                <p class="admin-note-body" id="note-body-{{ $note->id }}">{{ $note->body }}</p>

                                @if ($canEditNote)
                                    <form method="POST" action="{{ route('admin.requests.notes.update', [$customerRequest, $note]) }}"
                                        class="admin-note-edit-form" id="note-edit-form-{{ $note->id }}" hidden>
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="body" rows="3" maxlength="3000">{{ $note->body }}</textarea>
                                        <div class="admin-note-edit-actions">
                                            <button type="submit" class="button button-secondary button-small">Opslaan</button>
                                        </div>
                                    </form>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <script>
                    (function () {
                        'use strict';
                        document.querySelectorAll('.admin-note-edit-toggle').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var id = btn.dataset.noteId;
                                var body = document.getElementById('note-body-' + id);
                                var form = document.getElementById('note-edit-form-' + id);
                                if (!body || !form) return;
                                var isHidden = form.hidden;
                                form.hidden = !isHidden;
                                body.hidden = isHidden;
                            });
                        });
                    })();
                    </script>
                @endif
            </div>

            {{-- Activiteitstijdlijn --}}
            <div class="admin-detail-card admin-timeline-card">
                <h2>Tijdlijn</h2>

                @if (empty($timeline))
                    <p class="admin-muted-text">Nog geen activiteit geregistreerd.</p>
                @else
                    <ul class="admin-timeline-list">
                        @foreach ($timeline as $event)
                            <li>
                                <span class="admin-timeline-date">{{ $event['date']->format('d/m/Y H:i') }}</span>
                                <span class="admin-timeline-action">{{ $event['action'] }}</span>
                                @if (!empty($event['user']))
                                    <span class="admin-timeline-user">— {{ $event['user'] }}</span>
                                @endif
                                @if (!empty($event['note']))
                                    <p class="admin-timeline-note">{{ \Illuminate\Support\Str::limit($event['note'], 200) }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Afspraken (future-ready — geen volledige planning nog) --}}
            <div class="admin-detail-card admin-appointments-card">
                <h2>Afspraken</h2>

                @if ($customerRequest->appointments->isEmpty())
                    <p class="admin-muted-text">Nog geen afspraak ingepland.</p>
                @else
                    <ul class="admin-appointments-list">
                        @foreach ($customerRequest->appointments as $appointment)
                            <li>
                                <strong>{{ $appointment->date->format('d/m/Y') }}</strong>
                                @if ($appointment->time)
                                    <span>{{ \Illuminate\Support\Str::of((string) $appointment->time)->limit(5, '') }}</span>
                                @endif
                                @if ($appointment->technician)
                                    <span>— {{ $appointment->technician }}</span>
                                @endif
                                @if ($appointment->location)
                                    <span class="admin-appointment-location">{{ $appointment->location }}</span>
                                @endif
                                @if ($appointment->notes)
                                    <p class="admin-appointment-notes">{{ $appointment->notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <details class="admin-appointment-form-toggle">
                    <summary>+ Afspraak toevoegen</summary>

                    <form method="POST" action="{{ route('admin.requests.appointments.store', $customerRequest) }}" class="admin-appointment-form">
                        @csrf

                        <label>
                            <span>Datum</span>
                            <input type="date" name="date" required>
                        </label>

                        <label>
                            <span>Tijd (optioneel)</span>
                            <input type="time" name="time">
                        </label>

                        <label>
                            <span>Technicus (placeholder)</span>
                            <input type="text" name="technician" maxlength="255" placeholder="Nog geen technicus-systeem — vrije tekst">
                        </label>

                        <label>
                            <span>Locatie</span>
                            <input type="text" name="location" maxlength="255" value="{{ trim(($answers['street'] ?? '') . ' ' . ($answers['city'] ?? '')) }}">
                        </label>

                        <label>
                            <span>Notities</span>
                            <textarea name="notes" rows="3" maxlength="2000"></textarea>
                        </label>

                        <button class="button button-primary" type="submit">Afspraak opslaan</button>
                    </form>
                </details>
            </div>

            {{-- Alle antwoorden — collapsed by default --}}
            <div class="admin-detail-card admin-answers-card">
                <details>
                    <summary style="cursor:pointer;font-size:1.1rem;font-weight:600;">Alle antwoorden tonen</summary>

                    <dl class="admin-answers-grid" style="margin-top:1rem;">
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
                </details>
            </div>

        </div>
    </section>
    <script>
    (function () {
        var btn = document.querySelector('[data-copy-target="admin-snel-bericht-text"]');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-copy-target');
            var el = document.getElementById(targetId);
            if (!el) return;
            var text = el.textContent || el.innerText || '';
            var feedback = btn.parentElement.querySelector('.admin-copy-feedback');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    if (feedback) { feedback.textContent = 'Gekopieerd!'; setTimeout(function () { feedback.textContent = ''; }, 2500); }
                }).catch(function () {
                    fallbackCopy(el, feedback);
                });
            } else {
                fallbackCopy(el, feedback);
            }
        });

        function fallbackCopy(el, feedback) {
            var range = document.createRange();
            range.selectNodeContents(el);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            sel.removeAllRanges();
            if (feedback) { feedback.textContent = ok ? 'Gekopieerd!' : 'Selecteer handmatig.'; setTimeout(function () { feedback.textContent = ''; }, 2500); }
        }
    }());
    </script>
@endsection
