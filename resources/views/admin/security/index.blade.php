@extends('layouts.app')

@section('title', 'Admin | Beveiligingslog')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Beveiligingslog</h1>
            <p>Elke beslissing van de formulierbeveiliging: wat werd geblokkeerd, wat wacht op controle en welke mails bewust niet verstuurd werden.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">

            @include('admin.requests.partials.security-tile', ['security' => $security, 'compact' => true])

            <div class="admin-panel admin-security-log-panel">
                <details class="admin-filter-details" {{ collect($filters)->filter()->isNotEmpty() ? 'open' : '' }}>
                    <summary>
                        Filters
                        @if (collect($filters)->filter()->isNotEmpty())
                            <span>actief</span>
                        @endif
                    </summary>

                    <form class="admin-filter-form" method="GET" action="{{ route('admin.security.index') }}">
                        <label>
                            <span>Van</span>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                        </label>
                        <label>
                            <span>Tot</span>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                        </label>
                        <label>
                            <span>Formulier</span>
                            <select name="form">
                                <option value="">Alle formulieren</option>
                                @foreach ($formLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['form'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Beslissing</span>
                            <select name="decision">
                                <option value="">Alle beslissingen</option>
                                @foreach ($decisionLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['decision'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Reden</span>
                            <select name="reason">
                                <option value="">Alle redenen</option>
                                @foreach ($reasonLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['reason'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Mail</span>
                            <select name="mail">
                                <option value="">Verzonden of niet</option>
                                <option value="sent" @selected($filters['mail'] === 'sent')>Mail verzonden</option>
                                <option value="skipped" @selected($filters['mail'] === 'skipped')>Mail niet verzonden</option>
                            </select>
                        </label>
                        <div class="admin-filter-actions">
                            <button type="submit" class="button button-primary">Filteren</button>
                            <a class="button button-secondary" href="{{ route('admin.security.index') }}">Wissen</a>
                        </div>
                    </form>
                </details>

                <div class="admin-panel-header">
                    <h2>Events</h2>
                    <p>{{ $events->total() }} {{ $events->total() === 1 ? 'event' : 'events' }} · nieuwste eerst · IP en e-mail zijn gehasht/gemaskeerd.</p>
                </div>

                @if ($events->isEmpty())
                    <p class="admin-empty">Geen events gevonden voor deze filters.</p>
                @else
                    <div class="admin-table-wrapper">
                        <table class="admin-table admin-security-table">
                            <thead>
                                <tr>
                                    <th>Tijd</th>
                                    <th>Formulier</th>
                                    <th>Beslissing</th>
                                    <th>Score</th>
                                    <th>Redenen</th>
                                    <th>Captcha</th>
                                    <th>Invultijd</th>
                                    <th>Signalen</th>
                                    <th>Mail</th>
                                    <th>Bezoeker</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($events as $event)
                                    @php
                                        $signalGroups = collect($event->signals ?? [])
                                            ->only(['rate_limit', 'duplicate', 'content', 'velocity', 'ip'])
                                            ->flatMap(fn ($codes, $group) => collect($codes)->filter(fn ($c) => ! str_starts_with($c, '+'))->map(fn ($c) => \App\Models\FormSecurityEvent::label($c)));
                                        $captchaLabel = \App\Models\FormSecurityEvent::CAPTCHA_LABELS[$event->captcha_status] ?? ($event->captcha_status ?: '—');
                                        $fillLabel = \App\Models\FormSecurityEvent::FILL_TIME_LABELS[$event->fill_time_bucket] ?? ($event->fill_time_bucket ?: '—');
                                        $subjectUrl = null;
                                        $subjectLabel = null;

                                        if ($event->subject_id !== null) {
                                            if ($event->subject_type === (new \App\Models\CustomerRequest())->getMorphClass()) {
                                                $subjectUrl = route('admin.requests.show', $event->subject_id);
                                                $subjectLabel = 'Open aanvraag';
                                            } elseif ($event->subject_type === (new \App\Models\ContactSubmission())->getMorphClass()) {
                                                $subjectUrl = route('admin.contact-submissions.show', $event->subject_id);
                                                $subjectLabel = 'Open bericht';
                                            }
                                        }
                                    @endphp
                                    <tr class="admin-security-row admin-security-row-{{ $event->decision }}">
                                        <td data-label="Tijd">
                                            {{ $event->occurred_at?->format('d/m/Y') }}<br>
                                            <span class="admin-security-muted">{{ $event->occurred_at?->format('H:i:s') }}</span>
                                        </td>
                                        <td data-label="Formulier">{{ $event->formLabel() }}</td>
                                        <td data-label="Beslissing">
                                            <span class="admin-trust-badge admin-trust-badge-{{ $event->decision }}">{{ $event->decisionLabel() }}</span>
                                            @if ($event->attack_mode)
                                                <span class="admin-trust-badge admin-trust-badge-attack">Aanval</span>
                                            @endif
                                        </td>
                                        <td data-label="Score">
                                            {{ $event->risk_score }}
                                            <span class="admin-security-muted">({{ ['low' => 'laag', 'medium' => 'midden', 'high' => 'hoog'][$event->risk_level] ?? $event->risk_level }})</span>
                                        </td>
                                        <td data-label="Redenen">
                                            @if (empty($event->reasons))
                                                <span class="admin-security-muted">Geen twijfel</span>
                                            @else
                                                {{ implode(', ', $event->reasonLabels()) }}
                                            @endif
                                        </td>
                                        <td data-label="Captcha">
                                            {{ $captchaLabel }}
                                            @if ($event->captcha_hostname_ok !== null || $event->captcha_action_ok !== null)
                                                <br><span class="admin-security-muted">hostname {{ $event->captcha_hostname_ok === null ? '–' : ($event->captcha_hostname_ok ? '✓' : '✗') }} · action {{ $event->captcha_action_ok === null ? '–' : ($event->captcha_action_ok ? '✓' : '✗') }}</span>
                                            @endif
                                        </td>
                                        <td data-label="Invultijd">
                                            {{ $fillLabel }}
                                            @if ($event->fill_time_seconds !== null)
                                                <span class="admin-security-muted">({{ $event->fill_time_seconds }} s)</span>
                                            @endif
                                        </td>
                                        <td data-label="Signalen">
                                            @forelse ($signalGroups as $label)
                                                <span class="admin-security-tag">{{ $label }}</span>
                                            @empty
                                                <span class="admin-security-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td data-label="Mail">
                                            <span class="admin-security-muted">admin:</span> {{ \App\Models\FormSecurityEvent::MAIL_LABELS[$event->mail_admin] ?? $event->mail_admin }}<br>
                                            <span class="admin-security-muted">klant:</span> {{ \App\Models\FormSecurityEvent::MAIL_LABELS[$event->mail_customer] ?? $event->mail_customer }}
                                            @if ($event->mail_reason)
                                                <br><span class="admin-security-muted">{{ \App\Models\FormSecurityEvent::label($event->mail_reason) }}</span>
                                            @endif
                                        </td>
                                        <td data-label="Bezoeker">
                                            <code class="admin-security-hash" title="Privacyvriendelijke IP-hash">{{ $event->ip_hash ?: '—' }}</code><br>
                                            {{ $event->email_masked ?: '—' }}<br>
                                            <span class="admin-security-muted">{{ $event->user_agent_family ?: 'onbekende browser' }}@if ($event->locale) · {{ $event->locale }}@endif</span>
                                        </td>
                                        <td data-label="">
                                            @if ($subjectUrl)
                                                <a class="admin-link" href="{{ $subjectUrl }}">{{ $subjectLabel }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-security-pagination">
                        {{ $events->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
