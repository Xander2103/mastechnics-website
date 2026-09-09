# Trust Gate + Externe-mail-noodrem + Beveiligingslog — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Spam kan het Brevo-quotum niet meer consumeren: alleen inzendingen die op meerdere onafhankelijke signalen als mens scoren mailen automatisch; twijfel wordt opgeslagen als `needs_review` zonder mail; een gezamenlijke externe-mailnoodrem en een admin-beveiligingslog maken het zichtbaar.

**Architecture:** `PublicFormGuard::screen()` geeft voortaan een `TrustDecision` (trusted / needs_review / blocked) uit een deterministische `TrustEvaluator` die signalen verzamelt (`app/Services/Spam/Trust/`). `FormMailer` eist een trusted `TrustDecision` en `MailBudget` krijgt een gezamenlijke dag- en 10-minutenteller onder lock. Elke beslissing wordt door `SecurityEventRecorder` (fail-safe) in `form_security_events` gelogd; admin krijgt tegel + logpagina + review-acties.

**Tech Stack:** Laravel 12, PHP 8.2+, SQLite (tests)/MySQL, cache-store `database` in productie, Blade, bestaande admin-CSS (`resources/css/pages/admin.css`, Vite build).

**Spec:** `docs/superpowers/specs/2026-09-09-trust-gate-and-security-log-design.md`

## Global Constraints

- Geen push, geen deploy. Alleen lokale commits.
- Alle user-facing tekst in de publieke formulieren: nl/fr/en. Admin is Dutch-only.
- Nooit `Mail::`/`MailDispatcher` in een publieke controller buiten `FormMailer`.
- Bestaande routes, `CustomerRequest`-opslag, uploads, mails en admin mogen niet breken; `FormProtectionTest` moet blijven slagen (met aangepaste verwachtingen waar de default `CUSTOMER_CONFIRMATION_MAIL_ENABLED=false` geldt).
- Securitylog bevat nooit: captcha-token/secret, volledige headers/UA, berichttekst, naam, volledig IP, volledig e-mailadres.
- Logging/monitoring mag de requestflow nooit breken (try/catch, `Log::error`, doorgaan).
- PowerShell-syntax voor shellcommando's in dit project.

---

### Task 1: CaptchaVerdict + hostname/action-verificatie

**Files:**
- Create: `app/Services/Spam/CaptchaVerdict.php`
- Modify: `app/Services/Spam/CaptchaVerifier.php`, `SiteVerifyCaptchaVerifier.php`, `NullCaptchaVerifier.php`, `RejectingCaptchaVerifier.php`, `CaptchaVerifierFactory.php`, `config/captcha.php`, `resources/views/components/captcha-widget.blade.php`, `tests/Support/FakeCaptchaVerifier.php`
- Test: `tests/Feature/Spam/CaptchaProviderTest.php` (uitbreiden), `tests/Unit/Spam/CaptchaVerdictTest.php`

**Interfaces:**
- Produces: `final class CaptchaVerdict { const PASSED='passed', FAILED='failed', MISSING='missing', DISABLED='disabled', HOSTNAME_MISMATCH='hostname_mismatch', ACTION_MISMATCH='action_mismatch'; public function __construct(public readonly string $status, public readonly ?bool $hostnameOk=null, public readonly ?bool $actionOk=null, public readonly ?int $challengeAgeSeconds=null, public readonly array $errorCodes=[]); public function passed(): bool; public static function disabled(): self; public static function missing(): self; public static function failed(array $codes=[]): self; }`
- `CaptchaVerifier::verifyDetailed(?string $token, ?string $ip, ?string $expectedAction): CaptchaVerdict` (nieuw); `verify()` blijft en = `verifyDetailed(...)->passed()`.
- `SiteVerifyCaptchaVerifier` constructor krijgt extra: `array $expectedHostnames = []`, `bool $verifyHostname = true`, `bool $verifyAction = true`, `bool $supportsAction = true` (Turnstile true, reCAPTCHA false).
- `config('captcha.expected_hostnames')` (env `CAPTCHA_EXPECTED_HOSTNAMES`, komma-gescheiden, aangevuld met host van `app.url` ± `www.`), `captcha.verify_hostname`, `captcha.verify_action`, `captcha.max_token_age_seconds` (300).
- Widget: `data-action="{{ $form }}"` en in JS `options.action = el.dataset.action` (alleen turnstile).
- `FakeCaptchaVerifier` krijgt `public ?CaptchaVerdict $verdict = null` override + registreert `expectedAction` in `$calls`.

- [ ] Tests: token voor andere hostname → `HOSTNAME_MISMATCH`, `verify()` false; Turnstile action mismatch → `ACTION_MISMATCH`; reCAPTCHA negeert action; `challenge_ts` 10 min oud → `challengeAgeSeconds ≥ 600`; hostname-check uit via config → passed.
- [ ] Implementatie + bestaande tests groen.
- [ ] Commit `security: verify Turnstile hostname/action server-side`.

### Task 2: MailBudget-noodrem + FormMailer trust-eis + klantmail-default

**Files:**
- Modify: `app/Services/Spam/MailBudget.php`, `FormMailer.php`, `FormProtectionLog.php` (nieuwe redenen + labels), `config/form-protection.php`, `.env.example`
- Create: `app/Services/Spam/Trust/TrustDecision.php` (alvast, zie Task 3)
- Test: `tests/Feature/Spam/MailCircuitBreakerTest.php`

**Interfaces:**
- `MailBudget::reserve(string $kind): ?string` controleert nu eerst `circuit burst` (`FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES`, default 4, key `form-protection:mail-circuit:burst`, 600 s) en `circuit daily` (`FORM_EXTERNAL_MAIL_DAILY_LIMIT`, default 20, key `form-protection:mail-circuit:day`, 86400 s) → redenen `FormProtectionLog::MAIL_CIRCUIT_BURST='mail_circuit_burst'`, `MAIL_CIRCUIT_DAILY='mail_circuit_daily'`.
- `MailBudget::circuitState(): array{enabled: bool, open: bool, reason: ?string, daily_used: int, daily_limit: int, burst_used: int, burst_limit: int}`.
- `FormMailer::sendAdmin(string $form, ?string $recipient, Mailable $mailable, TrustDecision $decision, ?CustomerRequest $cr = null): bool`, idem `sendCustomer(...)`, met `Closure|Mailable $mailable` toegestaan zodat de klantmailable lazy gebouwd wordt. Niet-trusted → skip met reden `FormProtectionLog::MAIL_NOT_TRUSTED='mail_not_trusted'`.
- `FormMailer::lastOutcome(): array{admin: 'sent'|'skipped'|'not_applicable', customer: idem, reason: ?string}` reset per `beginSubmission()`; wordt door de recorder gebruikt.
- Config default `customer_confirmation_enabled` → `false`.

- [ ] Tests: 5e mail binnen 10 min → 0 transport; 21e op een dag → 0 transport; verschillende IP's/adressen omzeilen niet; klantbevestiging uit → 0 klant-calls en mailable-closure niet aangeroepen; niet-trusted decision → 0 transport + `mail_logs` skipped.
- [ ] Commit `security: joint external-mail circuit breaker, mail requires trusted decision`.

### Task 3: TrustEvaluator + signalen

**Files:**
- Create: `app/Services/Spam/Trust/TrustDecision.php`, `TrustSignal.php`, `TrustEvaluator.php`, `TrustContext.php`, `Signals/CaptchaSignals.php`, `Signals/TimingSignals.php`, `Signals/BrowserSignals.php`, `Signals/EmailSignals.php`, `Signals/ContentSignals.php`, `Signals/IpSignals.php`, `Signals/VelocitySignals.php`, `SimHash.php`, `EmailDomainCheck.php`
- Modify: `config/form-protection.php` (sectie `trust`), `app/Providers/AppServiceProvider.php` (binding)
- Test: `tests/Unit/Spam/TrustEvaluatorTest.php`, `tests/Unit/Spam/SimHashTest.php`, `tests/Unit/Spam/BrowserSignalsTest.php`, `tests/Unit/Spam/EmailSignalsTest.php`

**Interfaces:**
- `final class TrustSignal { public function __construct(public readonly string $code, public readonly string $group, public readonly int $risk = 0, public readonly bool $positive = false, public readonly bool $block = false) }`
- `final class TrustDecision { const TRUSTED='trusted', NEEDS_REVIEW='needs_review', BLOCKED='blocked'; public readonly string $verdict; public readonly int $risk; public readonly int $positives; public readonly bool $attackMode; /** @var TrustSignal[] */ public readonly array $signals; public readonly ?Rejection $rejection; public function trusted(): bool; public function blocked(): bool; public function reasons(): array /* codes van risk>0 of block */; public function signalsByGroup(): array; public function riskLevel(): 'low'|'medium'|'high'; public static function trustedForTests(): self; }`
- `final class TrustContext { public function __construct(public readonly string $form, public readonly Request $request, public readonly SubmissionFacts $facts, public readonly CaptchaVerdict $captcha, public readonly string $timingStatus, public readonly ?int $fillSeconds, public readonly bool $honeypot, public readonly ?Rejection $hardRejection) }` — `SubmissionFacts` krijgt extra optionele velden `name`, `locale`, `hasAttachments`, `hasRooms`.
- `TrustEvaluator::evaluate(TrustContext $ctx): TrustDecision` — verzamelt van elke `SignalProvider` (`interface SignalProvider { /** @return TrustSignal[] */ public function collect(TrustContext $ctx): array; }`), past drempels toe uit `config('form-protection.trust')`: `trusted_max_risk` (2), `trusted_min_positives` (3), `block_score` (12), `fast_seconds` per form (contact 15, request 40), `velocity.review_per_10_minutes` (5), `velocity.attack_per_10_minutes` (10), `velocity.attack_per_hour` (25), `email.dns_check` (true), `email.disposable_domains` (lijst), `similarity.max_hamming` (6), `similarity.window_seconds` (3600), `similarity.max_entries` (200).
- Hard rejection (captcha/honeypot/timing/limits/duplicate) → `TrustDecision` met verdict BLOCKED en `rejection` gezet; overige signalen worden dan niet meer verzameld (geen DNS-lookups voor bots).
- `TrustEvaluator::recordAccepted(TrustContext $ctx, TrustDecision $d): void` — vult de vensters (simhash-lijst, naam-teller, timing-token-teller, IP→adressen, UA-hash→IP's, velocity-tellers) fail-safe.
- `SimHash::of(string $normalizedText): string` (16 hex, 64 bit) en `SimHash::distance(string $a, string $b): int`.
- `EmailDomainCheck::hasMailServer(string $domain): ?bool` (cache 24 u, `null` bij fout/uit).

- [ ] Unit-tests per spec-tabel 3.3 (minstens: captcha alleen → needs_review; captcha+timing+browser → trusted; hergebruikt token → needs_review; curl-UA → needs_review; attack mode → geen trusted; block_score → blocked; wegwerpdomein; simhash-afstand).
- [ ] Commit `security: deterministic trust evaluator with independent signals`.

### Task 4: Migraties + modellen + guard/controller-integratie

**Files:**
- Create: `database/migrations/2026_09_09_100000_add_trust_columns_to_submissions.php`, `2026_09_09_100100_create_form_security_events_table.php`, `app/Models/FormSecurityEvent.php`, `app/Services/Spam/SecurityEventRecorder.php`
- Modify: `app/Models/CustomerRequest.php`, `app/Models/ContactSubmission.php`, `app/Services/Spam/PublicFormGuard.php`, `app/Http/Controllers/ContactController.php`, `app/Http/Controllers/CustomerRequestController.php`, `app/Services/Spam/FormProtectionLog.php`
- Test: `tests/Feature/Spam/TrustGateTest.php`, `tests/Feature/Spam/SecurityEventRecorderTest.php`

**Interfaces:**
- `PublicFormGuard::screen(string $form, Request $request, SubmissionFacts $facts, string $emailErrorKey): TrustDecision` (was `?Rejection`). Blocked → recorder logt meteen (`subject` null). Bestaande publieke checkmethodes blijven.
- `PublicFormGuard::recordAccepted(string $form, Request $request, SubmissionFacts $facts, TrustDecision $decision): void`.
- `SecurityEventRecorder::record(string $form, Request $request, SubmissionFacts|null $facts, TrustDecision $decision, ?Model $subject, array $mail /* van FormMailer::lastOutcome() */): void` — nooit throwen.
- `FormSecurityEvent` model: `$casts` json voor `reasons`, `signals`; `subject(): MorphTo`; scopes `today()`, `decision()`; statische `LABELS` voor alle reason-codes (NL).
- Controllers: `$decision = $guard->screen(...)`; `if ($decision->blocked()) refuse(...)`; opslag met `trust_verdict`, `trust_score`, `trust_reasons`; `recordAccepted(..., $decision)`; `mailer->beginSubmission()`; mails met `$decision`; `recorder->record(...)` in `finally`-stijl (try/catch). Silent-success gedrag bij honeypot blijft. Naam/attachments/rooms in `SubmissionFacts`.
- `contact_submissions.mail_sent_at` alleen zetten als er effectief iets verstuurd is.

- [ ] Tests (SpyMailTransport): trusted → 1 rij + 1 adminmail (+ klantmail als config aan); needs_review → 1 rij `trust_verdict=needs_review` + 0 transport + event `mail_admin=skipped`; captcha-succes alleen → needs_review; hostname/action-mismatch → 0 rij + 0 transport + event `captcha_status`; recorder gooit (DB-fout gesimuleerd door tabel te droppen) → 200-redirect, 1 mail, geen dubbele mail; event bevat geen token/UA/bericht (assert op json-encoded rij).
- [ ] `FormProtectionTest` aanpassen: klantmail expliciet aan in `setUp`, mailer-signatuur.
- [ ] Commit `security: trust gate in both public forms, needs_review storage, security events`.

### Task 5: Retention-command + scheduler + CLI-stats

**Files:**
- Create: `app/Console/Commands/PruneSecurityLog.php`
- Modify: `routes/console.php`, `app/Console/Commands/FormProtectionStats.php`, `config/form-protection.php` (`security_log.retention_days`, env `FORM_SECURITY_LOG_RETENTION_DAYS`, default 90)
- Test: `tests/Feature/Spam/PruneSecurityLogTest.php`

- [ ] `forms:prune-security-log {--days=}` verwijdert events ouder dan N dagen in batches van 1000; scheduler `daily()`; stats-command toont beslissingen vandaag + circuit-status.
- [ ] Commit `security: security-log retention + scheduler`.

### Task 6: Admin — tegel, beveiligingslog, review-acties, contactinzendingen

**Files:**
- Create: `app/Http/Controllers/Admin/SecurityLogController.php`, `app/Http/Controllers/Admin/ContactSubmissionController.php`, `app/Services/Spam/SecurityDashboard.php`, `resources/views/admin/security/index.blade.php`, `resources/views/admin/contact-submissions/index.blade.php`, `resources/views/admin/contact-submissions/show.blade.php`, `resources/views/admin/requests/partials/security-tile.blade.php`, `resources/views/admin/requests/partials/trust-review-banner.blade.php`
- Modify: `routes/web.php` (in `admin`-groep), `app/Http/Controllers/Admin/RequestController.php` (tegel-data, filter `trust_verdict`, acties `releaseTrust`/`markSpam`), `resources/views/admin/requests/index.blade.php`, `show.blade.php`, `resources/views/partials/admin-header.blade.php`, `resources/css/pages/admin.css`
- Test: `tests/Feature/Admin/SecurityLogTest.php`, `tests/Feature/Admin/TrustReviewTest.php`

**Interfaces:**
- `SecurityDashboard::summary(): array{blocked_today, needs_review_today, trusted_today, mails_prevented_today, external_mails_today, external_mail_limit, circuit: array, attack: array{active: bool, last_10_minutes: int, last_hour: int}}`; aanval = events niet-trusted ≥ `trust.velocity.attack_per_10_minutes` in 10 min of ≥ `attack_per_hour` in 1 u.
- Routes: `GET admin/security-log` (`admin.security.index`), `GET admin/contact-submissions` (`admin.contact-submissions.index`), `GET admin/contact-submissions/{contactSubmission}` (`.show`), `POST admin/contact-submissions/{id}/trust` (`.trust`, body `action=release|spam`), `POST admin/requests/{customerRequest}/trust` (`admin.requests.trust`).
- Filters querystring: `date_from`, `date_to`, `form`, `decision`, `reason`, `mail` (`sent`|`skipped`); `paginate(25)->withQueryString()`.
- Release: `trust_verdict=trusted`, `trust_reviewed_at/by`; mails via `FormMailer` met `TrustDecision::trustedForTests()`-achtige `TrustDecision::manualRelease()`; event gelogd met form + `decision=trusted`, reden `manual_release`.

- [ ] Tests: niet-admin → redirect naar login voor alle routes; events zichtbaar; filters (decision/form/mail) en paginatie (26 events → 2 pagina's); needs_review-rij linkt naar `admin.requests.show`; release stuurt 1 adminmail via spy en zet verdict; spam-markering stuurt 0.
- [ ] `npm run build` voor CSS.
- [ ] Commit `admin: form security monitor, security log, trust review actions`.

### Task 7: Documentatie + rapport

**Files:**
- Modify: `docs/anti-spam.md`, `CLAUDE.md` (sprint 21 + regels), `.env.example`
- Create: `docs/anti-spam-report-2026-09-09.md` (root cause, trust flow, testresultaten, Cloudflare-advies, GO/NO-GO)

- [ ] Volledige testsuite groen; commit `docs: sprint 21 trust gate report`.
