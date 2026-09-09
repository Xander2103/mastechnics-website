{{--
    Compact "Formulierbeveiliging" panel (sprint 21). Numbers come from
    App\Services\Spam\SecurityDashboard::summary(); every value is zero-safe
    so the panel renders even when the security log is unavailable.

    Parameters:
      $security  summary() array
      $compact   optional: true on the security-log page (no button)
--}}
@php
    $circuit = $security['circuit'] ?? [];
    $attack = $security['attack'] ?? ['active' => false, 'last_10_minutes' => 0, 'last_hour' => 0];
    $compact = $compact ?? false;

    if (empty($circuit['enabled'])) {
        $circuitClass = 'is-off';
        $circuitText = 'Mailbudget uitgeschakeld';
    } elseif (! empty($circuit['open'])) {
        $circuitClass = 'is-open';
        $resets = $circuit['resets_at'] ?? null;
        $circuitText = 'Noodrem actief — geen formuliermails'
            . ($resets ? ' tot ' . $resets->timezone(config('app.timezone'))->format('H:i') : '')
            . ' (' . (\App\Services\Spam\FormProtectionLog::LABELS[$circuit['reason'] ?? ''] ?? 'limiet bereikt') . ')';
    } else {
        $circuitClass = 'is-closed';
        $circuitText = 'Noodrem gesloten — mail mogelijk';
    }
@endphp

<div class="admin-security-tile" data-testid="security-tile">
    <div class="admin-security-tile-header">
        <div>
            <h2>Formulierbeveiliging</h2>
            <p>Beslissingen van de anti-spamlaag vandaag. Twijfelgevallen worden bewaard zonder mail.</p>
        </div>
        @unless ($compact)
            <a class="button button-secondary admin-security-tile-button" href="{{ route('admin.security.index') }}">Bekijk beveiligingslog</a>
        @endunless
    </div>

    @if (! empty($attack['active']))
        <div class="admin-security-attack" role="status">
            <strong>Aanval actief:</strong>
            {{ $attack['last_10_minutes'] }} verdachte inzendingen in 10 min
            ({{ $attack['last_hour'] }} in het laatste uur). Nieuwe inzendingen krijgen geen automatische mail.
        </div>
    @endif

    <div class="admin-security-grid">
        <div class="admin-security-stat admin-security-stat-blocked">
            <span class="admin-security-number">{{ $security['blocked_today'] ?? 0 }}</span>
            <span class="admin-security-label">Geblokkeerd vandaag</span>
        </div>
        <a class="admin-security-stat admin-security-stat-review" href="{{ route('admin.requests.index', ['trust' => 'needs_review']) }}">
            <span class="admin-security-number">{{ $security['needs_review_today'] ?? 0 }}</span>
            <span class="admin-security-label">Te controleren vandaag</span>
            @if (($security['needs_review_open'] ?? 0) > 0)
                <span class="admin-security-sub">{{ $security['needs_review_open'] }} open</span>
            @endif
        </a>
        <div class="admin-security-stat admin-security-stat-trusted">
            <span class="admin-security-number">{{ $security['trusted_today'] ?? 0 }}</span>
            <span class="admin-security-label">Vertrouwd vandaag</span>
        </div>
        <div class="admin-security-stat">
            <span class="admin-security-number">{{ $security['mails_prevented_today'] ?? 0 }}</span>
            <span class="admin-security-label">Brevo-mails voorkomen</span>
        </div>
        <div class="admin-security-stat">
            <span class="admin-security-number">{{ $security['external_mails_today'] ?? 0 }}<small> / {{ $security['external_mail_limit'] ?? 0 }}</small></span>
            <span class="admin-security-label">Externe form-mails vandaag</span>
        </div>
    </div>

    <div class="admin-security-circuit {{ $circuitClass }}">
        <span class="admin-security-circuit-dot" aria-hidden="true"></span>
        {{ $circuitText }}
        @if (! empty($circuit['enabled']))
            <span class="admin-security-circuit-detail">· {{ $circuit['burst_used'] ?? 0 }}/{{ $circuit['burst_limit'] ?? 0 }} per 10 min</span>
        @endif
    </div>
</div>
