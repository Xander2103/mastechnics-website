# Anti-spam bescherming van de publieke formulieren

Stand: sprint 21 (2026-09-09). Geldt voor het contactformulier
(`POST /{nl|fr|en}/contact`) en de aanvraagwizard (`POST /{nl|fr|en}/requests`).
Ontwerp: `docs/superpowers/specs/2026-09-08-anti-spam-hardening-design.md`
(sprint 20) en `docs/superpowers/specs/2026-09-09-trust-gate-and-security-log-design.md`
(sprint 21). Incidentrapport 09/09: `docs/anti-spam-report-2026-09-09.md`.

## 1. Waarom

Elke geaccepteerde inzending kost transactionele mail op een gratis
Brevo-account met 300 sends/dag. Op 08/09/2026 diende een bot beide
formulieren in serie in; sprint 20 voegde captcha, honeypots, invultijd,
limieten en een mailbudget toe. Op 09/09/2026 kwam een bot die de captcha
liet oplossen en IP + e-mailadres roteerde door alle lagen: elke inzending
die captcha + honeypot + invultijd passeerde, werd als mens behandeld en
kostte onmiddellijk twee mails, tot de daglimieten (~240 mails) vol zaten.

Sprint 21 draait het principe om: **een inzending mag pas mail veroorzaken
als meerdere onafhankelijke signalen vertrouwen geven**. Twijfel wordt
opgeslagen (`needs_review`) zonder mail; een gezamenlijke noodrem begrenst
alle formuliermails hard; alles is zichtbaar in de admin.

## 2. De lagen, in de volgorde waarin ze lopen

Alle logica zit in `app/Services/Spam/`. De controllers roepen alleen
`PublicFormGuard` (screening + beslissing), `FormMailer` (verzending) en via
de guard `SecurityEventRecorder` (log) aan.

| # | Laag | Waar | Gedrag |
|---|---|---|---|
| 0 | Kill switch formulier | `CONTACT_FORM_ENABLED`, `REQUEST_WIZARD_ENABLED` | GET: nette melding. POST: foutmelding, niets opgeslagen. |
| 1 | Idempotency | `submission_token` (unique index) | Replay → successcherm, niets opnieuw. |
| 2 | Attempt-limiters | per IP 30/10 min per formulier, site-breed 120/10 min | HTTP 429 (gebrande pagina), vóór validatie en captcha. |
| 3 | Server-side validatie | controller | Gewone veldfouten. |
| 4 | CAPTCHA | `CaptchaVerifier::verifyDetailed()` → `CaptchaVerdict` | `success` **én** `hostname` **én** (Turnstile) `action` moeten kloppen. Fout/mismatch = **blocked**. Netwerkfout/geen keys = fail closed. |
| 5 | Honeypot | 2 verborgen velden | Vals successcherm, niets opgeslagen (**blocked**). |
| 6 | Invultijd | HMAC-token `form_opened_at`, min 3 s, max 12 u | Ontbrekend/vervalst/te snel/verlopen = **blocked**. |
| 7 | Geaccepteerd-limieten | per IP, per e-mail, per formulier, globale burst | **blocked** met `rate_limit`-melding. |
| 8 | Fingerprint | exact 1/10 min; zelfde tekst 3/uur | **blocked** ("zonet al verstuurd"). |
| 9 | **Trust-evaluatie** | `Trust\TrustEvaluator` (sectie 3) | `trusted` / `needs_review` / `blocked` (score). |
| 10 | Blocklist | alleen contact | Neutrale melding, **blocked**. |
| 11 | Opslag | DB-transactie, `trust_verdict` + `trust_score` + `trust_reasons` op de rij | needs_review wordt **wel** opgeslagen. |
| 12 | Mail | `FormMailer`: **alleen trusted** → klantmail-switch → ontvanger-check → noodrem + budget → `MailDispatcher` | needs_review = 0 transport-calls; `mail_logs.status = skipped`. |
| 13 | Securityevent | `SecurityEventRecorder` → `form_security_events` | Eén rij per beslissing; falen breekt niets. |

**Brevo/SMTP wordt pas bereikt in stap 12**, in `MailDispatcher::send()`,
uitsluitend voor een trusted beslissing en nadat de rij is opgeslagen.
`FormMailer` eist de `TrustDecision` als parameter, dus een controller kan de
controle niet vergeten.

## 3. Trust gate (sprint 21)

`TrustEvaluator` verzamelt signalen van zeven providers
(`Trust/Signals/*`): captcha, invultijd, browser, e-mail, inhoud, IP en
velocity. Elk signaal heeft een risicogewicht en/of is "positief" (bewijs
van een mens). Beslissing (drempels in `config/form-protection.php` →
`trust`):

| Beslissing | Voorwaarde |
|---|---|
| trusted | risico ≤ `trusted_max_risk` (2) **én** positieven ≥ `trusted_min_positives` (3) **én** geen aanvalsmodus |
| blocked | harde laag 4–8/10, of risico ≥ `block_score` (12) → stil successcherm |
| needs_review | alles daartussen |

Captcha geslaagd is precies één positief signaal; er zijn er drie nodig
(typisch captcha + normale invultijd + consistente browser). Signalen en
gewichten: zie de spec §3.3 en de labels in
`App\Models\FormSecurityEvent::REASON_LABELS`. Belangrijkste:

- **Invultijdtoken hergebruikt** (+4): hetzelfde `form_opened_at` twee keer
  gepost — dicht het gat waarmee een bot met één GET onbeperkt kon posten.
- **Geen browser** (+4) / browserheaders (`Sec-Fetch-*`, `Accept`,
  `Accept-Language`) ontbreken (+1 à +2).
- **E-mail**: wegwerpdomein (+3), domein zonder MX/A (+3, gecachte
  DNS-lookup, `FORM_EMAIL_DNS_CHECK`), willekeurig lokaal deel (+2).
- **Inhoud**: similariteit met een recent bericht (simhash, +3), zelfde
  naam ≥ 3× in een uur (+2), links, niet-Latijns schrift.
- **IP**: recent al verdacht (+2), ≥ 3 adressen in een uur (+3).
- **Velocity**: ≥ 5 geaccepteerd/10 min (+2); ≥ 10/10 min of ≥ 25/uur =
  **aanvalsmodus** (+4, niemand wordt trusted); zelfde browser vanaf ≥ 3
  IP's (+3).

Aanvalsmodus en de drempels zijn deterministisch (`FORM_VELOCITY_*`,
`FORM_TRUST_*`). Een provider die faalt (cache/DNS) logt en valt weg: het
resultaat schuift dan richting needs_review, nooit richting trusted.

### needs_review in de admin

Rijen met `trust_verdict = needs_review` staan in de aanvragenlijst (badge
"Te controleren", filter) en in `/admin/contact-submissions`. In het detail:
**Vrijgeven en mails versturen** (verdict → trusted, adminmelding + evt.
klantbevestiging via `FormMailer`, dus onder de noodrem) of **Markeer als
spam** (verdict → spam, geen mail). Beide worden als securityevent gelogd
(`manual_release` / `manual_spam`).

## 4. Mailnoodrem en budget

| Teller | Env | Standaard |
|---|---|---|
| **Alle formuliermails (admin + klant, beide formulieren) per dag** | `FORM_EXTERNAL_MAIL_DAILY_LIMIT` | 20 |
| **Alle formuliermails per 10 minuten** | `FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES` | 4 |
| Klantbevestigingen per dag | `CUSTOMER_CONFIRMATION_DAILY_LIMIT` | 100 |
| Adminmeldingen per dag | `ADMIN_NOTIFICATION_DAILY_LIMIT` | 150 |
| Alle formuliermails per uur | `FORM_MAIL_BURST_LIMIT` | 30 |
| Budget aan/uit | `MAIL_GUARD_ENABLED` | true |

De twee gezamenlijke tellers zijn de **noodrem**: zodra één vol is, gaan er
0 verdere mails vanuit de publieke formulieren naar Brevo tot het venster
verloopt; aanvragen blijven opgeslagen (`mail_logs.status = skipped`, reden
`mail_circuit_daily` / `mail_circuit_burst`). Reserveren gebeurt onder
`Cache::lock` (check + increment atomair), concurrency-safe; kan de lock
niet genomen worden, dan wordt de mail geweigerd (fail closed). Het
dashboard toont de status ("Noodrem actief — geen formuliermails tot
HH:MM"). Admin-mails (offerte, standaardantwoord) lopen niet via
`FormMailer` en vallen buiten deze tellers.

Een geblokkeerde mail wordt **nooit** automatisch alsnog verstuurd; Martin
ziet in de aanvraag-activiteit "E-mail niet verstuurd (reden)" en kan zelf
mailen of de aanvraag vrijgeven.

## 5. Kill switches

```
CONTACT_FORM_ENABLED=true
REQUEST_WIZARD_ENABLED=true
CUSTOMER_CONFIRMATION_MAIL_ENABLED=false   # sinds sprint 21 standaard UIT
MAIL_GUARD_ENABLED=true
```

`CUSTOMER_CONFIRMATION_MAIL_ENABLED=false` betekent letterlijk geen externe
call voor de klantmail: `FormMailer::sendCustomer()` stopt vóór het budget
en vóór `MailDispatcher`; alleen de auditrij in `mail_logs` wordt
geschreven. Wijzigen in `.env` en daarna `php artisan config:clear`.

## 6. CAPTCHA-provider en Turnstile-audit

```
CAPTCHA_PROVIDER=turnstile        # of: recaptcha
CAPTCHA_ENABLED=                  # leeg = auto: alleen local/testing/development mogen zonder keys draaien
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
CAPTCHA_EXPECTED_HOSTNAMES=       # optioneel, komma-gescheiden; host van APP_URL (± www.) zit er altijd in
CAPTCHA_VERIFY_HOSTNAME=true
CAPTCHA_VERIFY_ACTION=true
```

Server-side wordt niet alleen `success` gecontroleerd. Het
siteverify-antwoord moet de verwachte `hostname` dragen en (Turnstile) de
verwachte `action` = formulier-id (`contact` / `request`, gerenderd als
`data-action` op de widget). Een geldig token dat voor een andere hostname
of action werd uitgegeven, wordt geweigerd (`captcha_hostname` /
`captcha_action` in het securitylog). `challenge_ts` wordt gelezen; een
token ouder dan 300 s telt als risicosignaal. reCAPTCHA v2 kent geen action:
daar geldt alleen de hostname-controle.

- **Fail closed**: buiten `local`/`testing`/`development` zonder keys (of met
  een onbekende provider) weigert `RejectingCaptchaVerifier` elke inzending.
- Gebruik in productie **nooit** Cloudflare's testsleutels
  (`1x0000…AA`): die keuren elk token goed.
- Widget: contactformulier meteen, wizard bij het openen van de laatste stap.

## 7. Beveiligingslog (`form_security_events`)

Eén rij per beslissing (trusted / needs_review / blocked, ook 429's,
blocklist, handmatige acties). Kolommen: tijd, formulier, beslissing,
score + risiconiveau, redenen (codes), signalen per groep, captcha-status
+ hostname/action ok, invultijd-bucket + seconden, aanvalsmodus, mailactie
admin/klant + reden, voorkomen mails, IP-hash (16 hex, gekeyd met
APP_KEY), gemaskeerd e-mail (`xa***@gmail.com`) + e-mailhash, locale,
browserfamilie ("Chrome 128 · Windows"), koppeling naar de opgeslagen rij.

**Nooit opgeslagen**: captcha-token/secret, volledige headers of user-agent,
naam, berichttekst, volledig IP, volledig e-mailadres. `TrustGateTest`
bewijst dit op de ruwe databaserij.

**Failure mode**: `SecurityEventRecorder` vangt elke Throwable, logt
`security_log.write_failed` en gaat door. Een kapotte of ontbrekende tabel
verandert dus nooit de beslissing, veroorzaakt geen 500 en geen dubbele mail
(getest door de tabel te droppen).

**Retention**: `FORM_SECURITY_LOG_RETENTION_DAYS` (default 90);
`php artisan forms:prune-security-log [--days=N]` verwijdert in batches van
1000, dagelijks gepland om 03:30 (`routes/console.php`; vereist
`* * * * * php artisan schedule:run` op de host, anders manueel).

## 8. Admin

- Dashboard `/admin/requests`: tegel **Formulierbeveiliging** — geblokkeerd /
  te controleren / vertrouwd vandaag, Brevo-mails voorkomen, externe
  form-mails vandaag / limiet, noodremstatus, band "Aanval actief" bij de
  deterministische velocity-drempel, knop **Bekijk beveiligingslog**.
- `/admin/security-log`: filters datum, formulier, beslissing, reden, mail
  verzonden/niet; 25 per pagina; per rij alle kolommen van sectie 7 en een
  link naar de aanvraag/het contactbericht.
- `/admin/contact-submissions`: lijst + detail van contactberichten met
  dezelfde review-acties.
- Alles achter de bestaande `admin`-middleware (`EnsureAdminIsAuthenticated`).
- CLI: `php artisan forms:protection-stats --days=7` toont ook noodrem en
  beslissingen van vandaag.

## 9. Proxy / IP-detectie

`Request::ip()` is `REMOTE_ADDR` zolang `TRUSTED_PROXIES` leeg is (tests
bewijzen dat gespoofte `X-Forwarded-For`/`Forwarded`/`X-Real-IP` niets
doen). **Nooit** `TRUSTED_PROXIES=*`.

- Apache-host zonder proxy (huidig): `TRUSTED_PROXIES` leeg laten.
- Achter de Cloudflare-proxy: `TRUSTED_PROXIES` op de Cloudflare-reeksen
  (https://www.cloudflare.com/ips/), origin dichtzetten voor niet-Cloudflare
  verkeer. Zie het advies in `docs/anti-spam-report-2026-09-09.md`.

## 10. Cloudflare WAF / rate limiting (buiten Laravel)

Alleen zinvol via de Cloudflare-proxy (oranje wolk). Aanbevolen: Bot Fight
Mode, rate-limiting op `POST /(nl|fr|en)/(contact|requests)` 5/10 min per
IP, managed challenge bij lage bot-score. Geen DNS-wijzigingen uitgevoerd.

## 11. Limieten (env)

```
CONTACT_ATTEMPT_LIMIT_PER_10_MINUTES=30   REQUEST_ATTEMPT_LIMIT_PER_10_MINUTES=30
GLOBAL_ATTEMPT_LIMIT_PER_10_MINUTES=120
CONTACT_DAILY_LIMIT=10                    REQUEST_DAILY_LIMIT=5        (per IP)
CONTACT_BURST_LIMIT_PER_HOUR=20           REQUEST_BURST_LIMIT_PER_HOUR=10
CONTACT_EMAIL_DAILY_LIMIT=3               REQUEST_EMAIL_DAILY_LIMIT=3
CONTACT_GLOBAL_DAILY_LIMIT=60             REQUEST_GLOBAL_DAILY_LIMIT=60
FORM_ACCEPTED_BURST_LIMIT_PER_10_MINUTES=20
FORM_MIN_FILL_SECONDS=3                   FORM_MAX_FILL_HOURS=12
FORM_TRUST_MAX_RISK=2                     FORM_TRUST_MIN_POSITIVES=3      FORM_TRUST_BLOCK_SCORE=12
FORM_VELOCITY_REVIEW_PER_10_MINUTES=5     FORM_VELOCITY_ATTACK_PER_10_MINUTES=10   FORM_VELOCITY_ATTACK_PER_HOUR=25
FORM_EMAIL_DNS_CHECK=true
FORM_EXTERNAL_MAIL_DAILY_LIMIT=20         FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES=4
FORM_SECURITY_LOG_RETENTION_DAYS=90
```

Tellers resetten: `php artisan cache:clear` (wist ook spamstatistieken en
similariteitsvensters; het securitylog staat in de database en blijft).

## 12. Tests die het bewijzen

Alle mailtests gebruiken `tests/Support/SpyMailTransport.php` (een Symfony
`TransportInterface` die telt in plaats van SMTP te spreken): 0 calls = de
provider is letterlijk niet aangeroepen. `Mail::fake()` wordt daarvoor niet
gebruikt.

- `tests/Feature/Spam/TrustGateTest.php` — trusted = 1 rij + exact de
  verwachte mails; captcha-succes alleen → needs_review, 0 transport;
  script-client met opgeloste captcha → needs_review; hergebruikt
  invultijdtoken → needs_review; aanvalsmodus → needs_review voor iedereen;
  hostname-/action-mismatch → 0 rij + 0 mail; roterende IP's + adressen
  omzeilen de daglimiet niet; burstlimiet; klantbevestiging uit → 0
  klant-calls; blocked/needs_review/trusted in het log; needs_review linkt
  naar de rij; geen token/UA/naam/bericht/IP/e-mail in de logrij; kapot log
  → geen 500, geen dubbele mail.
- `tests/Feature/Spam/MailCircuitBreakerTest.php` — noodrem op
  transportniveau: 5e mail/10 min en 21e mail/dag = 0 transport, één teller
  voor admin + klant over beide formulieren, lock-timeout = weigeren,
  niet-trusted beslissing = 0 transport en 0 budgetverbruik.
- `tests/Feature/Spam/CaptchaProviderTest.php` + `tests/Unit/Spam/CaptchaVerdictTest.php`
  — hostname/action/challenge_ts-contract tegen `Http::fake()`.
- `tests/Unit/Spam/TrustEvaluatorTest.php`, `BrowserSignalsTest`,
  `EmailSignalsTest`, `SimHashTest`, `SecurityEventRecorderTest`.
- `tests/Feature/Spam/PruneSecurityLogTest.php` — retention + planning.
- `tests/Feature/Admin/SecurityLogTest.php`, `TrustReviewTest.php` — toegang
  alleen voor admins, filters, paginatie, links, vrijgeven/spam.
- `tests/Feature/Spam/FormProtectionTest.php` (sprint 20) blijft de harde
  lagen bewijzen; de trust gate is daar via `disableTrustGate()`
  geneutraliseerd zodat beide onafhankelijk getest worden.

## 13. Bewust niet gedaan

- Geen queue-migratie (synchrone verzending na de DB-save; alle bescherming
  zit ervóór).
- Geen externe IP-reputatiedienst: geen extra netwerkafhankelijkheid in de
  POST-flow (alleen siteverify en een gecachte MX-lookup).
- Geen automatische mail na vrijgave-timeouts: needs_review blijft wachten
  op een mens.
- Geen ML: alle drempels zijn deterministisch en configureerbaar.
