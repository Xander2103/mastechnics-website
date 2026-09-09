<?php

namespace App\Models;

use App\Services\Spam\FormProtectionLog;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One trust decision of a public-form submission (see SecurityEventRecorder).
 * Contains hashes, buckets and codes only — never tokens, secrets, raw
 * headers, names, message bodies, full IPs or full e-mail addresses.
 */
class FormSecurityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'occurred_at',
        'form',
        'decision',
        'risk_score',
        'risk_level',
        'reasons',
        'signals',
        'captcha_status',
        'captcha_hostname_ok',
        'captcha_action_ok',
        'fill_time_bucket',
        'fill_time_seconds',
        'attack_mode',
        'mail_admin',
        'mail_customer',
        'mail_reason',
        'mails_prevented',
        'ip_hash',
        'email_masked',
        'email_hash',
        'locale',
        'user_agent_family',
        'subject_type',
        'subject_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'reasons' => 'array',
        'signals' => 'array',
        'captcha_hostname_ok' => 'boolean',
        'captcha_action_ok' => 'boolean',
        'attack_mode' => 'boolean',
        'risk_score' => 'integer',
        'fill_time_seconds' => 'integer',
        'mails_prevented' => 'integer',
    ];

    public const DECISION_LABELS = [
        TrustDecision::TRUSTED => 'Vertrouwd',
        TrustDecision::NEEDS_REVIEW => 'Te controleren',
        TrustDecision::BLOCKED => 'Geblokkeerd',
    ];

    public const FORM_LABELS = [
        'contact' => 'Contact',
        'request' => 'Aanvraag',
    ];

    public const CAPTCHA_LABELS = [
        'passed' => 'Geslaagd',
        'failed' => 'Mislukt',
        'missing' => 'Ontbreekt',
        'disabled' => 'Uitgeschakeld',
        'hostname_mismatch' => 'Verkeerde hostname',
        'action_mismatch' => 'Verkeerde action',
    ];

    public const FILL_TIME_LABELS = [
        'missing' => 'Ontbreekt',
        'invalid' => 'Vervalst',
        'too_fast' => 'Te snel',
        'fast' => 'Snel',
        'normal' => 'Normaal',
        'slow' => 'Zeer oud',
        'expired' => 'Verlopen',
    ];

    public const MAIL_LABELS = [
        'sent' => 'verzonden',
        'skipped' => 'bewust overgeslagen',
        'failed' => 'mislukt',
        'not_applicable' => '—',
    ];

    /** Human labels for every reason/signal code the evaluator, guard and mailer can emit. */
    public const REASON_LABELS = [
        // captcha
        'captcha_ok' => 'Captcha geslaagd',
        'captcha_failed' => 'Captcha mislukt/ontbreekt',
        'captcha_hostname' => 'Captcha: verkeerde hostname',
        'captcha_action' => 'Captcha: verkeerde action',
        'captcha_token_old' => 'Captcha-token oud',
        // timing / honeypot
        'honeypot' => 'Honeypot ingevuld',
        'fill_time_missing' => 'Invultijdtoken ontbreekt',
        'fill_time_invalid' => 'Invultijdtoken vervalst',
        'fill_time_too_fast' => 'Te snel ingevuld',
        'fill_time_expired' => 'Invultijdtoken verlopen',
        'fill_time_fast' => 'Snel ingevuld',
        'fill_time_very_old' => 'Formulier zeer lang open',
        'fill_time_normal' => 'Normale invultijd',
        'fill_time_token_reused' => 'Invultijdtoken hergebruikt',
        // browser
        'ua_non_browser' => 'Geen browser (script/tool)',
        'sec_fetch_missing' => 'Browserheaders ontbreken',
        'accept_not_html' => 'Accept-header geen HTML',
        'accept_language_missing' => 'Geen taalheader',
        'accept_language_mismatch' => 'Taalheader past niet bij site',
        'browser_consistent' => 'Browser consistent',
        // e-mail
        'email_disposable' => 'Wegwerp-e-maildomein',
        'email_random_local_part' => 'Willekeurig e-mailadres',
        'email_domain_no_mx' => 'E-maildomein zonder mailserver',
        'email_plausible' => 'E-mailadres plausibel',
        'email_matches_name' => 'E-mail past bij naam',
        // content
        'content_urls' => 'Links in bericht/naam',
        'name_suspicious' => 'Verdachte naam',
        'content_non_latin' => 'Niet-Latijns schrift',
        'content_name_equals_message' => 'Naam = bericht',
        'content_similar_recent' => 'Lijkt op recent bericht',
        'name_repeated_recent' => 'Zelfde naam recent herhaald',
        'phone_plausible' => 'Telefoonnummer plausibel',
        'request_details_provided' => 'Bijlagen/kamers ingevuld',
        // ip
        'ip_recently_flagged' => 'IP recent al verdacht',
        'ip_many_emails' => 'IP met veel adressen',
        'ip_private_range' => 'Privé-IP (proxy?)',
        // velocity
        'velocity_elevated' => 'Verhoogde inzendsnelheid',
        'velocity_attack' => 'Aanvalsmodus (burst)',
        'velocity_same_ua_many_ips' => 'Zelfde browser, veel IP\'s',
        'attack_mode' => 'Aanvalsmodus',
        // score / manual
        'trust_score' => 'Risicoscore te hoog',
        'manual_release' => 'Handmatig vrijgegeven',
        'manual_spam' => 'Handmatig als spam gemarkeerd',
        // hard limits (from PublicFormGuard / FormProtectionLog)
        FormProtectionLog::REASON_ATTEMPTS => 'Te veel pogingen (429)',
        FormProtectionLog::REASON_IP_LIMIT => 'IP-limiet',
        FormProtectionLog::REASON_EMAIL_LIMIT => 'E-maillimiet',
        FormProtectionLog::REASON_FORM_LIMIT => 'Daglimiet formulier',
        FormProtectionLog::REASON_GLOBAL_BURST => 'Globale burst-limiet',
        FormProtectionLog::REASON_DUPLICATE => 'Herhaalde inzending',
        FormProtectionLog::REASON_BLOCKLIST => 'Geblokkeerd adres',
        FormProtectionLog::REASON_DISABLED => 'Formulier uitgeschakeld',
        // mail outcomes
        FormProtectionLog::MAIL_NOT_TRUSTED => 'Mail overgeslagen: niet vertrouwd',
        FormProtectionLog::MAIL_CUSTOMER_DISABLED => 'Klantmail uitgeschakeld',
        FormProtectionLog::MAIL_UNSAFE_RECIPIENT => 'Onveilig ontvangeradres',
        FormProtectionLog::MAIL_BUDGET_CUSTOMER => 'Klantmailbudget bereikt',
        FormProtectionLog::MAIL_BUDGET_ADMIN => 'Adminmailbudget bereikt',
        FormProtectionLog::MAIL_BUDGET_BURST => 'Mail-burstlimiet bereikt',
        FormProtectionLog::MAIL_CIRCUIT_DAILY => 'Noodrem: daglimiet externe mails',
        FormProtectionLog::MAIL_CIRCUIT_BURST => 'Noodrem: burstlimiet externe mails',
        'mail_transport_failed' => 'Mailtransport mislukt',
    ];

    public static function label(string $code): string
    {
        return self::REASON_LABELS[$code] ?? $code;
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->where('occurred_at', '>=', now()->startOfDay());
    }

    public function scopeDecision(Builder $query, string $decision): Builder
    {
        return $query->where('decision', $decision);
    }

    public function decisionLabel(): string
    {
        return self::DECISION_LABELS[$this->decision] ?? $this->decision;
    }

    public function formLabel(): string
    {
        return self::FORM_LABELS[$this->form] ?? $this->form;
    }

    /** @return array<int, string> */
    public function reasonLabels(): array
    {
        return array_map([self::class, 'label'], $this->reasons ?? []);
    }

    public function mailSent(): bool
    {
        return $this->mail_admin === 'sent' || $this->mail_customer === 'sent';
    }
}
