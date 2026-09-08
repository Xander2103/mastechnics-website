# Anti-spam hardening van de publieke formulieren — ontwerp

Datum: 2026-09-08 · Status: goedgekeurd (vereisten aangeleverd door opdrachtgever, ontwerp afgeleid uit audit)

## 1. Aanleiding en root cause

Brevo-logs van 08/09/2026 tonen een bot die het aanvraag- en contactformulier
in serie indient met willekeurige e-mailadressen. Elke geaccepteerde submit
kost twee transactionele mails (admin + klantbevestiging) op een gratis
Brevo-account met 300 sends/dag.

Bevindingen van de audit (stand vóór deze sprint, inclusief de niet-gecommitte
Turnstile-aanzet in de werkkopie):

| Laag | Stand | Gat |
|---|---|---|
| Publieke POST-endpoints | `POST /{locale}/contact` (`ContactController@store`), `POST /{locale}/requests` (`CustomerRequestController@store`). Geen andere publieke schrijfroutes. | — |
| Mailflow | Alles via `MailDispatcher::send()` → `Mail::to()->send()` (synchroon, SMTP naar Brevo). Geen events, listeners, observers, queued mailables of jobs. Admin-mails (offerte, standaardantwoord) zitten achter `admin`-middleware. | Geen budget: elke geaccepteerde submit = 2 provider-calls. |
| Honeypot | Eén verborgen tekstveld `website_url`, server-side gecontroleerd, fake success. | Eén signaal, voorspelbare naam. |
| submission_token | UUID per GET, unique index in DB; replay → fake success, geen mail. | Werkt. Zonder token = altijd "nieuw" (bot laat het gewoon weg). |
| JS double-submit guard | Knop disabled na eerste submit. | Alleen UX. |
| Rate limiting | Per IP: dagelijks + per uur, geteld ná acceptatie. Aanzet in werkkopie: attempt-limiter per IP (30/10 min), per-e-mail (3/dag), globaal per formulier (100/dag). | Geen globale burst, geen fingerprint, geen invultijd. Een bot met wisselende IP's én adressen stuit pas op de globale daglimiet. |
| CSRF | Standaard `web`-middleware, actief op beide POST-routes. | — |
| Proxy/IP | `TRUSTED_PROXIES` leeg → `Request::ip()` = `REMOTE_ADDR`; `X-Forwarded-For`, `Forwarded`, `X-Real-IP` worden genegeerd (`ProxyHeaderHardeningTest`). | — (moet zo blijven) |
| CAPTCHA | Niets in productie. Werkkopie: Turnstile-aanzet (`TurnstileVerifier`, `PublicFormGuard`) — nooit gecommit of uitgerold. | **Root cause**: geen bot-challenge, dus een script dat de HTML leest, het honeypot-veld leeg laat en per request een nieuw IP/adres gebruikt, wordt geaccepteerd en kost 2 mails. |

Root cause samengevat: de formulieren onderscheiden bot van mens alleen via
een honeypot en per-IP-tellers. Een bot die het honeypot-veld leeg laat en
adressen roteert, passeert alles, en er is geen bovengrens op het aantal
mails dat de site per dag naar buiten stuurt.

## 2. Doel en niet-doelen

Doel: defense-in-depth waarbij een afgewezen submit **0 databaserecords en 0
provider-calls** oplevert, en waarbij zelfs een geslaagde bot-golf het
Brevo-quotum niet kan opeten. Normale klanten merken alleen een
Turnstile-widget (managed, meestal onzichtbaar).

Niet-doelen: redesign, deploy, push, queue-migratie, reCAPTCHA en Turnstile
tegelijk zichtbaar, disposable-e-mailblokkering.

## 3. Architectuur

Alles leeft in `app/Services/Spam/`; controllers roepen één screener aan en
één mailer. Geen provider-specifieke code in controllers of Blade.

```
POST /{locale}/contact | /{locale}/requests
  └─ web middleware (CSRF)
  └─ Controller@store
       ├─ 0. kill switch (CONTACT_FORM_ENABLED / REQUEST_WIZARD_ENABLED)
       ├─ 1. idempotency: submission_token al verwerkt → fake success, stop
       ├─ 2. attempt-limiter per IP + globale attempt-burst → 429
       ├─ 3. server-side validatie (formulierregels)
       ├─ 4. CAPTCHA (Turnstile | reCAPTCHA), server-side siteverify
       ├─ 5. honeypot (2 velden)
       ├─ 6. invultijd (gesigneerde timestamp, min. 3 s, max. 12 u)
       ├─ 7. geaccepteerd-limieten: per IP (dag/uur), per e-mail (dag),
       │      per formulier (dag), globale burst (10 min)
       ├─ 8. fingerprint: exacte herhaling + inhoudsherhaling
       ├─ 9. blocklist (alleen contact)
       ├─ 10. DB-save (transactie), tellers ophogen
       └─ 11. FormMailer: per mail kill switch → budget reserveren → MailDispatcher
                └─ pas hier: Mail::to()->send() → SMTP/Brevo
```

Stap 2 staat vóór validatie omdat hij de enige is die goedkoop genoeg is om
een POST-flood te begrenzen; stap 1 staat vooraan omdat een replay van een
verwerkt token per definitie geen nieuwe poging is (bestaande
regressietests). Alle overige stappen volgen de door de opdrachtgever
opgelegde volgorde.

### 3.1 CAPTCHA-abstractie

- `CaptchaVerifier` (interface): `enabled()`, `provider()`, `siteKey()`,
  `responseField()`, `verify(?string $token, ?string $ip): bool`.
- `SiteVerifyCaptchaVerifier` (abstract): één HTTP-siteverify-implementatie;
  Cloudflare en Google gebruiken hetzelfde contract (`secret`, `response`,
  `remoteip` → `{success}`). Timeout 5 s, netwerkfout = fail closed.
- `CloudflareTurnstileVerifier` en `GoogleRecaptchaVerifier` (v2 checkbox)
  verschillen alleen in URL, veldnaam en scriptbron.
- `NullCaptchaVerifier` (uit) en `RejectingCaptchaVerifier` (fail closed:
  vereist maar niet geconfigureerd).
- Binding in `AppServiceProvider` op basis van `config/captcha.php`
  (`CAPTCHA_PROVIDER`, `CAPTCHA_ENABLED`, per provider site/secret key).
  `TURNSTILE_ENABLED` blijft als alias werken.
- Eén Blade-partial `components.captcha-widget` rendert script + widget
  voor de actieve provider. De wizard rendert expliciet bij het openen van
  de laatste stap (widget in een verborgen stap is onbetrouwbaar).

Oordeel reCAPTCHA: technisch netjes inpasbaar (zelfde siteverify-contract,
~40 regels), dus geïmplementeerd als **wisselbare fallback** achter
`CAPTCHA_PROVIDER=recaptcha`. Standaard en aanbevolen blijft Turnstile:
privacyvriendelijker (geen Google-cookies, geen cookie-banner-implicaties),
gratis zonder volumelimiet, en dezelfde partij die de WAF-regels levert.
Beide tegelijk tonen is bewust niet mogelijk.

### 3.2 Invultijd

Hidden veld `form_opened_at` = `<unix-ts>.<hmac-sha256(form|ts, APP_KEY)>`,
gerenderd bij elke GET. Server: signature geldig, `now - ts >= 3 s`,
`now - ts <= 12 u`. Ontbrekend/ongeldig = geweigerd (het formulier rendert het
altijd; een hand-gemaakte POST zonder veld is per definitie geen browser).
Refresh geeft een nieuw veld; back-button hergebruikt een nog geldig veld;
trage gebruikers hebben 12 u.

### 3.3 Honeypot

Twee verborgen velden (`website_url`, `mailing_address_2`) in dezelfde
onzichtbare container; server-side: elke niet-lege waarde = hit → fake
success, niets opgeslagen, niets gemaild, wel geteld in monitoring.

### 3.4 Limieten (Laravel `RateLimiter`, cache-store)

| Sleutel | Standaard | Wanneer geteld |
|---|---|---|
| `attempts:{form}:{ip}` | 30 / 10 min | elke POST die de controller bereikt |
| `attempts:global` | 120 / 10 min | idem, site-breed |
| `accepted:{form}:ip:{ip}` dag / uur | contact 10/20, request 5/10 | na save |
| `accepted:{form}:email:{hash}` | 3 / dag | na save |
| `accepted:{form}:day` | 60 / dag | na save |
| `accepted:global:burst` | 20 / 10 min | na save |
| `fp:exact:{hash}` | 1 / 10 min | na save |
| `fp:content:{hash}` | 3 / uur | na save |

E-mail wordt genormaliseerd (lowercase, plus-tag weg, gmail-punten weg) en
gehasht in de sleutel. Fingerprint = sha256(form, ip, e-mail, telefoon,
sha256(bericht), user-agent). Exacte herhaling binnen 10 min of dezelfde
berichtinhoud >3×/uur wordt geweigerd.

IP = `Request::ip()`; `TRUSTED_PROXIES` blijft standaard leeg. Tests bewijzen
dat `X-Forwarded-For`, `Forwarded` en `X-Real-IP` de tellers niet beïnvloeden.

### 3.5 Mailbudget en kill switches (`FormMailer`, `MailBudget`)

- `MailBudget::reserve(kind)`: check-en-increment onder `Cache::lock`, dus
  concurrency-veilig; ontbrekend lock-support degradeert naar
  `RateLimiter::attempt` (nog steeds atomair op de DB-store).
- Budgetten (per dag, rollend 24 u): `CUSTOMER_CONFIRMATION_DAILY_LIMIT=100`,
  `ADMIN_NOTIFICATION_DAILY_LIMIT=150`, burst `FORM_MAIL_BURST_LIMIT=20`/uur
  over alle formuliermails. Samen met 60+60 geaccepteerde submits/dag blijft
  het totaal ruim onder 300 met marge voor offerte-/antwoordmails.
- Kill switches: `MAIL_GUARD_ENABLED`, `CUSTOMER_CONFIRMATION_MAIL_ENABLED`,
  `CONTACT_FORM_ENABLED`, `REQUEST_WIZARD_ENABLED`.
- Geblokkeerde mail → `mail_logs` status `skipped` met reden, `Log::warning`,
  geen retry. De klant ziet de normale succespagina; de aanvraag staat in de
  admin. De adminmail heeft een eigen budget en blijft dus werken als het
  klantbudget op is.
- Volgorde blijft: save eerst, mail daarna; een mailfout verliest nooit een
  aanvraag (`MailDispatcher` vangt alles).

### 3.6 Queue-oordeel

`QUEUE_CONNECTION=database` staat in `.env`, maar er is geen bewezen worker
op de Apache-host (Sprint 18: geen deploytooling, serverconfig
ongedocumenteerd). Een queue-migratie zou mails stil laten liggen. Synchrone
verzending blijft; alle bescherming zit vóór de send, dus de queue voegt
hier geen veiligheid toe.

### 3.7 Ontvanger-veiligheid

Validatieregel `email:rfc` + `max:254`; `RecipientSafety::isSafe()` weigert
CR/LF, spaties, ontbrekend TLD-punt en te lange adressen vlak vóór de send.
Geen disposable-lijst: false positives bij echte klanten (bv. kleine
providers) wegen zwaarder dan de winst, nu Turnstile + budgetten het
quotum al afdekken.

### 3.8 Monitoring

`FormProtectionLog::rejected(form, reason, request, email?)`: één
`Log::notice('form_protection.rejected', …)` met gehashte IP/e-mail (nooit
berichtinhoud of secrets) plus cache-tellers per dag/reden. Admin-dashboard
toont "Spam tegengehouden (24 u)" met uitsplitsing; `php artisan
forms:protection-stats` print dezelfde tellers.

### 3.9 Tests

`tests/Support/SpyMailTransport.php`: een Symfony `TransportInterface` die
telt in plaats van SMTP te spreken, geregistreerd als mailer `spy`. Dit is
de laag die anders Brevo aanspreekt. `FormProtectionTest` (beide
formulieren) bewijst per scenario `0 DB + 0 transport-calls`, en voor een
geldige submit exact 1 record + 1 admin + 1 klant. `Http::fake()` bewijst
dat de captcha-verifiers zonder token geen netwerkcall doen en bij
netwerkfout fail-closed zijn.

## 4. Nieuwe env-variabelen

```
CAPTCHA_PROVIDER=turnstile          # turnstile | recaptcha
CAPTCHA_ENABLED=                    # leeg = auto (productie: verplicht)
TURNSTILE_SITE_KEY= / TURNSTILE_SECRET_KEY=
RECAPTCHA_SITE_KEY= / RECAPTCHA_SECRET_KEY=
FORM_MIN_FILL_SECONDS=3
FORM_MAX_FILL_HOURS=12
GLOBAL_ATTEMPT_LIMIT_PER_10_MINUTES=120
FORM_ACCEPTED_BURST_LIMIT_PER_10_MINUTES=20
REQUEST_GLOBAL_DAILY_LIMIT=60 / CONTACT_GLOBAL_DAILY_LIMIT=60
MAIL_GUARD_ENABLED=true
CUSTOMER_CONFIRMATION_DAILY_LIMIT=100
ADMIN_NOTIFICATION_DAILY_LIMIT=150
FORM_MAIL_BURST_LIMIT=20
CUSTOMER_CONFIRMATION_MAIL_ENABLED=true
CONTACT_FORM_ENABLED=true
REQUEST_WIZARD_ENABLED=true
```

## 5. Bestanden

Nieuw: `config/captcha.php`, `config/form-protection.php`,
`app/Services/Spam/{CaptchaVerifier, SiteVerifyCaptchaVerifier,
CloudflareTurnstileVerifier, GoogleRecaptchaVerifier, NullCaptchaVerifier,
RejectingCaptchaVerifier, FormTimingToken, SubmissionFingerprint,
MailBudget, FormMailer, RecipientSafety, FormProtectionLog,
ScreeningResult}.php`, `resources/views/components/captcha-widget.blade.php`,
`resources/views/components/form-disabled-notice.blade.php`,
`app/Console/Commands/FormProtectionStats.php`, tests, `docs/anti-spam.md`.

Gewijzigd: `PublicFormGuard` (pipeline), beide controllers, beide
form-partials, `app.js` (contact guard), `AppServiceProvider`,
`MailDispatcher` (status `skipped`), admin dashboard, `.env.example`,
`docs/deployment-runbook.md`, `CLAUDE.md`. Verwijderd: `config/turnstile.php`,
`TurnstileVerifier`-interface en `Null/RejectingTurnstileVerifier` (vervangen
door de captcha-varianten).
