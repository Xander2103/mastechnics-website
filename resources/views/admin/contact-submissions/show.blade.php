@extends('layouts.app')

@section('title', 'Admin | Contactbericht')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Contactbericht</h1>
            <p>Bericht van {{ $submission->name }} via het contactformulier ({{ strtoupper($submission->locale) }}).</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">

            @if (session('success') === 'trust_released')
                <div class="form-success">Bericht vrijgegeven. De melding werd verstuurd.</div>
            @elseif (session('success') === 'trust_released_no_mail')
                <div class="form-error-list">Bericht vrijgegeven, maar er kon geen mail verstuurd worden (mailbudget of noodrem). Neem zelf contact op.</div>
            @elseif (session('success') === 'trust_marked_spam')
                <div class="form-success">Bericht als spam gemarkeerd. Er werd geen mail verstuurd.</div>
            @elseif (session('success') === 'trust_not_reviewable')
                <div class="form-error-list">Dit bericht staat niet (meer) op "te controleren".</div>
            @endif

            @include('admin.requests.partials.trust-review-banner', [
                'subject' => $submission,
                'action' => route('admin.contact-submissions.trust', $submission),
                'noun' => 'bericht',
            ])

            <div class="admin-detail-layout">
                <div class="admin-detail-card">
                    <a class="button button-secondary admin-back-button" href="{{ route('admin.contact-submissions.index') }}">← Terug naar contactberichten</a>

                    <h2>{{ $submission->subject }}</h2>

                    <dl class="admin-contact-meta">
                        <dt>Naam</dt>
                        <dd>{{ $submission->name }}</dd>
                        <dt>E-mail</dt>
                        <dd><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></dd>
                        <dt>Telefoon</dt>
                        <dd>{{ $submission->phone ?: '—' }}</dd>
                        <dt>Ontvangen</dt>
                        <dd>{{ $submission->created_at?->format('d/m/Y H:i') }}</dd>
                        <dt>Mail</dt>
                        <dd>{{ $submission->mail_sent_at ? 'Verzonden op ' . $submission->mail_sent_at->format('d/m/Y H:i') : 'Niet verzonden' }}</dd>
                    </dl>

                    <h3>Bericht</h3>
                    <div class="admin-contact-message">{{ $submission->message }}</div>

                    @if ($submission->trust_verdict !== \App\Services\Spam\Trust\TrustDecision::TRUSTED || $submission->trust_reviewed_at)
                        <p class="admin-security-muted" style="margin-top: 16px;">
                            <a class="admin-link" href="{{ route('admin.blocked-emails.index') }}">E-mailadres blokkeren →</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
