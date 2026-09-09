# Rapport sprint 21 — Brevo-quotum, trust gate, noodrem en beveiligingslog

Datum: 2026-09-09 · Opdrachtgever: Xander (VanMalder Studio) voor Mastechnics ·
Status code: lokaal gecommit op `main`, **niet gepusht, niet gedeployed**.

## 1. Samenvatting

Op 09/09/2026 rond 02:10 verbruikte een bot opnieuw het Brevo-dagquotum,
hoewel sprint 20 (Turnstile, honeypots, invultijd, limieten, mailbudget)
live stond. Oorzaak: de applicatie behandelde elke inzending die captcha +
honeypot + invultijd passeerde als mens en stuurde meteen twee mails; alle
overige lagen waren per-IP/per-adres (omzeild door rotatie) of hadden
limieten die samen nog ~240 spammails per dag toelieten.

Deze sprint vervangt dat model door een **pre-mail trust gate**: een
inzending mailt pas als meerdere onafhankelijke signalen vertrouwen geven.
Twijfel wordt opgeslagen als `needs_review` zonder mail en is in de admin
vrij te geven. Daarbovenop een **harde gezamenlijke noodrem** (20 mails/dag,
4 per 10 min, admin + klant samen) en een **beveiligingslog** met
dashboardtegel. Klantbevestigingen staan standaard uit.

**GO** voor deploy, met de checklist in sectie 8.

## 2. Root cause van 09/09/2026 02:10

De productielog en -database zijn vanaf de ontwikkelmachine niet bereikbaar
(lokaal: `laravel.log` bevat enkel testruns, SQLite is leeg; er is geen
deploy-tooling of gedocumenteerde servertoegang). De analyse is gebaseerd op
de code zoals die in productie draait en klopt met de symptomen: honderden
Brevo-logs 's nachts, roterende IP's, willekeurige adressen. Sectie 2.2
geeft de queries om dit op de server te bevestigen.

### 2.1 Waarom elke laag faalde

| Laag (sprint 20) | Waarom de bot erdoor kwam |
|---|---|
| Turnstile | Token werd geldig aangeleverd (solver-service of headless browser). De server keek alleen naar `success=true`; `hostname`, `action` en `challenge_ts` werden genegeerd. Een token dat voor een andere hostname/zonder action werd uitgegeven, werd aanvaard. |
| Honeypots | Bot laat verborgen velden leeg. |
| Invultijd | `form_opened_at` was 12 u geldig, niet gebonden aan IP/sessie en **niet single-use**: één GET → één token → onbeperkt POSTs. |
| Per-IP limieten | Elke POST kwam van een ander IP; tellers bleven op 1. |
| Per-e-mail limiet | Elk adres was nieuw. |
| Fingerprint exact / inhoud | Exact bevat IP + adres (nooit gelijk); inhoud alleen bij identieke tekst ≥ 20 tekens, 3×/uur. |
| Formulierlimiet 60/dag/formulier, burst 20/10 min | Ontwerpkeuze: dimensionering "onder 300 blijven", niet "spam weghouden". 120 inzendingen × 2 mails = 240. |
| Mailbudget 100 klant + 150 admin/dag, 30/uur | Idem: 30/uur × 8 nachtelijke uren = 240 mails ≈ het quotum. |

**In één zin:** captcha-pass = mens = 2 Brevo-mails, en geen enkele
site-brede grens lag onder het quotum.

### 2.2 Verifiëren op de server (geen gevoelige data nodig)

```
grep "form_protection" storage/logs/laravel.log | grep "2026-09-09 0[1-3]:" | cut -c1-160 | sed 's/"ip_hash".*//' | sort | uniq -c | sort -rn | head
php artisan tinker --execute="echo DB::table('mail_logs')->where('created_at','>=','2026-09-09')->selectRaw('status, count(*) c')->groupBy('status')->get();"
php artisan tinker --execute="echo DB::table('customer_requests')->where('created_at','>=','2026-09-09')->selectRaw(\"substr(created_at,1,13) h, count(*) c\")->groupBy('h')->get();"
php artisan tinker --execute="echo DB::table('contact_submissions')->where('created_at','>=','2026-09-09')->selectRaw(\"substr(created_at,1,13) h, count(*) c\")->groupBy('h')->get();"
php artisan forms:protection-stats --days=2
```

Verwacht beeld: `mail_logs.status = sent` ≈ 2 × het aantal rijen; rond
02:10 weinig of geen `captcha_failed` (captcha werd opgelost), later wel
`rate_limit_global` / `mail_budget_burst` (tellers vol). Als er **geen**
enkele `captcha_failed` én geen Turnstile-siteverify-verkeer te zien is:
controleer of `TURNSTILE_SECRET_KEY` op de server niet de testsleutel
`1x0000000000000000000000000000000AA` is (die keurt alles goed).

## 3. Nieuwe trust flow

```
POST → kill switch → idempotency → attempt-limits (429) → validatie
     → harde lagen: captcha (success + hostname + action) · honeypot · invultijd
       · IP/e-mail/formulier/globale limieten · fingerprint        → blocked
     → TrustEvaluator: 7 signaalgroepen, deterministisch
         risico ≤ 2 én ≥ 3 positieve signalen én geen aanvalsmodus → trusted
         risico ≥ 12                                                → blocked (stil)
         anders                                                     → needs_review
     → opslag (trust_verdict, trust_score, trust_reasons)
     → FormMailer (weigert alles behalve trusted)
         → klantmail-switch (standaard uit) → ontvanger-check
         → noodrem 4/10 min + 20/dag (admin + klant samen, Cache::lock)
         → per-soort budgetten → MailDispatcher → Brevo
     → securityevent (fail-safe)
```

Signaalgroepen: captcha (incl. `challenge_ts`), invultijd (incl.
**hergebruikt token**), browser (`User-Agent`, `Sec-Fetch-*`, `Accept`,
`Accept-Language`), e-mail (wegwerpdomein, MX-lookup, willekeurig lokaal
deel, naam ↔ adres), inhoud (links, schrift, simhash-similariteit,
naamherhaling, telefoon, bijlagen/kamers), IP (recent verdacht, veel
adressen), velocity (site-brede burst, aanvalsmodus, zelfde browser vanaf
veel IP's). Geen enkel signaal is op zichzelf voldoende: captcha geslaagd is
één positief signaal, er zijn er drie nodig. Gewichten en drempels:
`config/form-protection.php` → `trust`; volledige tabel in de spec.

Wat Martin merkt: een echte klant met een normale browser wordt trusted en
Martin krijgt zijn adminmelding zoals voorheen. Een twijfelgeval staat als
"Te controleren" in de aanvragenlijst / contactberichten; met **Vrijgeven en
mails versturen** gaat de adminmelding (en evt. klantbevestiging) alsnog uit,
onder de noodrem. De klant ziet altijd het gewone successcherm.

## 4. Wat er gebouwd is

| Onderdeel | Bestanden |
|---|---|
| Turnstile-audit | `CaptchaVerdict`, `SiteVerifyCaptchaVerifier::verifyDetailed()` (hostname + action + challenge_ts), `config/captcha.php` (`CAPTCHA_EXPECTED_HOSTNAMES`, `CAPTCHA_VERIFY_HOSTNAME`, `CAPTCHA_VERIFY_ACTION`), widget `data-action` |
| Trust gate | `app/Services/Spam/Trust/` (`TrustEvaluator`, `TrustDecision`, `TrustSignal`, `TrustContext`, `Signals/*`, `SimHash`, `EmailDomainCheck`), `PublicFormGuard::screen(): TrustDecision`, beide controllers |
| Opslag | migratie `trust_verdict|trust_score|trust_reasons|trust_reviewed_*` op `customer_requests` en `contact_submissions` |
| Mail | `FormMailer` (eist `TrustDecision`, closure-mailables, `lastOutcome()`), `MailBudget` (noodrem + `circuitState()`), klantmail default uit |
| Beveiligingslog | migratie `form_security_events`, `FormSecurityEvent`, `SecurityEventRecorder`, `forms:prune-security-log` + scheduler (03:30), `forms:protection-stats` uitgebreid |
| Admin | tegel "Formulierbeveiliging", `/admin/security-log` (filters, paginatie), `/admin/contact-submissions`, review-acties op aanvraag en contactbericht, navigatie |
| Docs | `docs/anti-spam.md` herschreven, spec + plan onder `docs/superpowers/`, `.env.example`, `CLAUDE.md` |

Nieuwe env-variabelen (allemaal met veilige defaults in code):
`FORM_EXTERNAL_MAIL_DAILY_LIMIT=20`, `FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES=4`,
`CUSTOMER_CONFIRMATION_MAIL_ENABLED=false`, `CAPTCHA_EXPECTED_HOSTNAMES`,
`CAPTCHA_VERIFY_HOSTNAME`, `CAPTCHA_VERIFY_ACTION`, `FORM_TRUST_MAX_RISK`,
`FORM_TRUST_MIN_POSITIVES`, `FORM_TRUST_BLOCK_SCORE`, `FORM_VELOCITY_*`,
`FORM_EMAIL_DNS_CHECK`, `FORM_SECURITY_LOG_RETENTION_DAYS=90`.

## 5. Testresultaten

Alle mailbewijzen lopen via `tests/Support/SpyMailTransport.php`: de
Symfony-transport die in productie met Brevo praat is vervangen door een
teller. 0 calls = Brevo/SMTP is letterlijk niet aangeroepen.

| Vereiste | Test | Resultaat |
|---|---|---|
| Captcha-succes alleen is onvoldoende voor mail | `TrustGateTest::test_captcha_success_alone_is_not_enough_to_send_mail` (beide formulieren) + `test_script_client_with_a_solved_captcha…` | rij `needs_review`, 0 transport |
| Suspicious/needs_review → DB-record, 0 transport | idem + `test_reused_fill_time_token…`, `test_attack_mode…` | ✔ |
| Trusted → exact verwachte mails | `test_trusted_submission_stores_one_row_and_sends_exactly_the_expected_mails` (1 admin, klant uit) + `…only_when_enabled` (2) | ✔ |
| Globale daglimiet → daarna 0 transport | `MailCircuitBreakerTest::test_daily_limit_is_one_counter…` (21e = 0) + `TrustGateTest::test_rotating_ips_and_addresses_cannot_bypass…` | ✔ |
| Burstlimiet → daarna 0 transport | `MailCircuitBreakerTest::test_burst_limit_stops_the_fifth…` + `TrustGateTest::test_external_mail_burst_limit…` | ✔ |
| Klantbevestiging uit → 0 klant-calls | `MailCircuitBreakerTest::test_disabled_customer_confirmation…` + `TrustGateTest::test_customer_confirmation_disabled…` | ✔ |
| Verschillende IP's + e-mails omzeilen de globale guard niet | `TrustGateTest::test_rotating_ips_and_addresses_cannot_bypass_the_external_mail_daily_limit` (6 mensen, 6 IP's, 6 browsers → 3 mails) | ✔ |
| Hostname/action-mismatch → 0 mail | `TrustGateTest::test_captcha_token_for_another_hostname…` / `…another_action…` (0 rij, 0 mail, event) + `CaptchaProviderTest` (echt siteverify-contract via `Http::fake()`) | ✔ |
| Concurrency-safe lock | `MailCircuitBreakerTest::test_reservation_waits_for_the_cache_lock…` (lock bezet → weigeren, 0 transport) | ✔ |
| Securitylog: blocked/needs_review/trusted zichtbaar, link naar rij, 0-mail gelogd | `TrustGateTest::test_blocked_submissions_are_logged…`, `…needs_review_event_links_to_the_stored_row`, `SecurityLogTest` | ✔ |
| Geen secrets/tokens/PII in het log | `TrustGateTest::test_security_event_never_contains_tokens_headers_names_or_message_bodies` (ruwe DB-rij) | ✔ |
| Log-fout breekt de flow niet | `TrustGateTest::test_a_failing_security_log_never_breaks_the_submission_or_doubles_mail` (tabel gedropt → redirect, 1 mail) + `SecurityEventRecorderTest` | ✔ |
| Niet-admin geen toegang, filters, paginatie | `SecurityLogTest`, `TrustReviewTest` | ✔ |
| Retention | `PruneSecurityLogTest` (batches, `--days`, planning 03:30) | ✔ |

Suite-totalen: sectie 9 (868 groen na de sprint, 769 ervoor).

## 6. Turnstile-audit

- Server-side controle nu: `success` **én** `hostname` ∈ {host van
  `APP_URL`, met/zonder `www.`, plus `CAPTCHA_EXPECTED_HOSTNAMES`} **én**
  (Turnstile) `action` = formulier-id (`contact` / `request`). Mismatch →
  geweigerd, gelogd als `captcha_hostname` / `captcha_action`.
- `challenge_ts` wordt gelezen; > 300 s oud = risicosignaal (Turnstile-tokens
  zijn 5 min geldig en single-use bij Cloudflare zelf).
- reCAPTCHA v2 (fallback) kent geen action; daar alleen hostname.
- Testsleutels in productie zijn uitgesloten via documentatie, niet
  technisch: controleer de server-`.env` (sectie 8).

## 7. Advies: website achter de Cloudflare-proxy?

**Ja, aanbevolen — als tweede laag, niet als vervanging.** De trust gate en
de noodrem maken het quotum nu ook zonder Cloudflare veilig (worst case bij
een perfecte bot: 20 mails/dag, alles verder needs_review). Cloudflare-proxy
+ WAF voegt toe wat Laravel niet kan: blokkeren vóór PHP draait
(CPU/DB-belasting van floods, siteverify-calls), Bot Fight Mode / bot-score,
IP-reputatie en rate limiting per IP op de edge, en het weghouden van
hosting-IP's van datacenters. Kost: gratis plan volstaat.

Voorwaarden om het correct te doen (anders verslechtert de situatie):

1. DNS via Cloudflare zetten met oranje wolk; SSL-modus **Full (strict)**.
2. `TRUSTED_PROXIES` in `.env` op de Cloudflare-IP-reeksen
   (https://www.cloudflare.com/ips/) zetten — **nooit** `*`. Zonder dit is
   `REMOTE_ADDR` altijd een Cloudflare-IP en delen alle bezoekers één
   per-IP-teller (échte klanten zouden dan 429 krijgen tijdens een aanval).
3. Origin dichtzetten voor niet-Cloudflare-verkeer (hostingfirewall of
   `.htaccess` op de Cloudflare-reeksen), anders kan een bot rechtstreeks
   naar het origin-IP posten en de header vervalsen.
4. WAF-regels: Bot Fight Mode aan; rate-limiting `POST /(nl|fr|en)/(contact|requests)`
   5 per 10 min per IP → block 10 min; managed challenge op die paden bij
   `cf.threat_score` hoog / bekende bots; Turnstile blijft actief (de
   siteverify-check wordt zo ook "hostname-vast").
5. Na de omschakeling: `ProxyHeaderHardeningTest`-gedrag in productie
   nakijken (`php artisan tinker` → `request()->ip()` op een testrequest) en
   het beveiligingslog een dag volgen: de `ip_hash`-kolom moet weer variëren.

Als dit niet op korte termijn kan: de huidige code is op zichzelf voldoende
voor het hoofddoel (quotum), en de rate-limiting op de edge is een
optimalisatie.

## 8. GO/NO-GO en deploy-checklist

**GO.** Voorwaarden bij deploy (geen deploy uitgevoerd in deze sprint):

1. `php artisan migrate` (twee migraties: trust-kolommen, `form_security_events`).
2. `.env` op de server: `CUSTOMER_CONFIRMATION_MAIL_ENABLED=false` (of
   bewust `true`), `FORM_EXTERNAL_MAIL_DAILY_LIMIT=20`,
   `FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES=4`,
   `CAPTCHA_EXPECTED_HOSTNAMES=mastechnics.be,www.mastechnics.be`,
   `FORM_SECURITY_LOG_RETENTION_DAYS=90`; controleer dat
   `TURNSTILE_SECRET_KEY` geen testsleutel is; daarna `php artisan config:clear`.
3. Cron voor de scheduler (`* * * * * cd /pad && php artisan schedule:run`)
   of `forms:prune-security-log` periodiek manueel draaien.
4. `npm run build` op de server (of de build mee uploaden): `public/build` staat in
   `.gitignore`, de nieuwe admin-CSS (tegel, badges, log) zit in `resources/css/pages/admin.css`.
5. Eerste 48 u: dashboardtegel en `/admin/security-log` volgen. Verwacht:
   spam → needs_review/blocked, echte klanten → trusted. Als échte klanten
   structureel in needs_review belanden: eerst het signaal in het log
   bekijken en de drempel (`FORM_TRUST_MAX_RISK`, gewicht) bijstellen; de
   aanvragen zijn intussen gewoon opgeslagen.
6. Bekende trade-off: tot het log leert wat normaal is, kan een klant met
   een exotische browser/privacy-extensie (geen `Sec-Fetch`-headers) in
   needs_review belanden. Martin krijgt daar géén mail van; het dashboard
   toont het aantal "Te controleren" → dagelijks even kijken.

Bewust niet gedaan: push, deploy, Cloudflare-configuratie, queue, externe
IP-reputatiediensten, ML.

## 9. Suite-totalen

| Run | Resultaat |
|---|---|
| Baseline vóór sprint 21 (2026-09-09) | 769 tests groen (4680 assertions) |
| Finale run na sprint 21 (`php artisan test --compact`) | **868 tests groen, 0 gefaald** (5296 assertions, 195 s) |
| Nieuwe tests deze sprint | 99 (trust gate, noodrem, captcha-metadata, evaluator-units, securitylog, admin, retention) |

Visuele controle: dashboardtegel, beveiligingslog en review-banner gerenderd
op desktop en op 414 px breedte (tegel stapelt in twee kolommen, logtabel
klapt om naar label/waarde-rijen, knoppen stapelen).
