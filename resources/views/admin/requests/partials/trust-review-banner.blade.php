{{--
    Trust-gate banner on a detail page (request or contact submission).

    Parameters:
      $subject   CustomerRequest|ContactSubmission (has trust_* columns)
      $action    route URL of the POST that takes action=release|spam
      $noun      'aanvraag' | 'bericht'
--}}
@php
    $verdict = $subject->trust_verdict;
    $reasons = collect($subject->trust_reasons ?? [])->map(fn ($code) => \App\Models\FormSecurityEvent::label($code));
    $customerMailOn = \App\Services\Spam\FormMailer::customerConfirmationEnabled();
@endphp

@if ($verdict === \App\Services\Spam\Trust\TrustDecision::NEEDS_REVIEW)
    <div class="admin-trust-banner admin-trust-banner-review" data-testid="trust-review-banner">
        <div class="admin-trust-banner-text">
            <strong>Te controleren.</strong>
            Deze {{ $noun }} werd door de formulierbeveiliging als twijfelgeval opgeslagen. Er werd <strong>geen e-mail</strong> verstuurd.
            @if ($subject->trust_score !== null)
                <span class="admin-trust-score">Risicoscore {{ $subject->trust_score }}</span>
            @endif
            @if ($reasons->isNotEmpty())
                <ul class="admin-trust-reasons">
                    @foreach ($reasons as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="admin-trust-banner-actions">
            <form method="POST" action="{{ $action }}">
                @csrf
                <input type="hidden" name="action" value="release">
                <button type="submit" class="button button-primary">
                    Vrijgeven en {{ $customerMailOn ? 'mails' : 'melding' }} versturen
                </button>
            </form>
            <form method="POST" action="{{ $action }}">
                @csrf
                <input type="hidden" name="action" value="spam">
                <button type="submit" class="button button-secondary">Markeer als spam</button>
            </form>
        </div>
    </div>
@elseif ($verdict === \App\Services\TrustReviewService::VERDICT_SPAM)
    <div class="admin-trust-banner admin-trust-banner-spam">
        <strong>Als spam gemarkeerd</strong>
        @if ($subject->trust_reviewed_by)
            door {{ $subject->trust_reviewed_by }}
        @endif
        @if ($subject->trust_reviewed_at)
            op {{ $subject->trust_reviewed_at->format('d/m/Y H:i') }}
        @endif
        — er werd geen e-mail verstuurd.
    </div>
@elseif ($subject->trust_reviewed_at)
    <div class="admin-trust-banner admin-trust-banner-released">
        <strong>Handmatig vrijgegeven</strong>
        @if ($subject->trust_reviewed_by)
            door {{ $subject->trust_reviewed_by }}
        @endif
        op {{ $subject->trust_reviewed_at->format('d/m/Y H:i') }}.
    </div>
@endif
