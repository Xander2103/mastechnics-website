# Anti-spam bescherming van de publieke formulieren

Stand: sprint 20 (2026-09-08). Geldt voor het contactformulier
(`POST /{nl|fr|en}/contact`) en de aanvraagwizard (`POST /{nl|fr|en}/requests`).
Ontwerp: `docs/superpowers/specs/2026-09-08-anti-spam-hardening-design.md`.

## 1. Waarom

Elke geaccepteerde inzending kost twee transactionele mails (adminmelding +
klantbevestiging) op een gratis Brevo-account met 300 sends/dag. Op
08/09/2026 diende een bot beide formulieren in serie in met willekeurige
adressen. De enige verdediging was een honeypot en een per-IP-teller; een bot
die het honeypot-veld leeg laat en adressen/IP's roteert, passeerde alles.

## 2. De lagen, in de volgorde waarin ze lopen

Alle logica zit in `app/Services/Spam/`. De controllers roepen alleen
`PublicFormGuard` (screening) en `FormMailer` (verzending) aan.

| # | Laag | Waar | Gedrag bij afwijzing |
|---|---|---|---|
| 0 | Kill switch formulier | `CONTACT_FORM_ENABLED`, `REQUEST_WIZARD_ENABLED` | GET: nette melding i.p.v. formulier. POST: foutmelding, niets opgeslagen. |
| 1 | Idempotency | `submission_token` (unique index) | Replay van een verwerkt token → successcherm, niets opnieuw. |
| 2 | Attempt-limiters | per IP 30/10 min per formulier, site-breed 120/10 min | HTTP 429 (gebrande pagina, nl/fr/en), vóór validatie en captcha. |
| 3 | Server-side validatie | controller | Gewone veldfouten. Captcha wordt hier nog **niet** aangeroepen. |
| 4 | CAPTCHA | `CaptchaVerifier` (Turnstile of reCAPTCHA), siteverify server-side | Foutmelding `captcha`. Netwerkfout of ontbrekende keys = fail closed. |
| 5 | Honeypot | 2 verborgen velden (`website_url` tekstveld, `newsletter_optin` checkbox — autofill vinkt nooit een checkbox aan) | Vals successcherm, niets opgeslagen, niets gemaild. |
| 6 | Invultijd | HMAC-gesigneerde timestamp `form_opened_at`, min 3 s, max 12 u | Foutmelding `captcha`. Ontbrekend/vervalst/verlopen = afgewezen. |
| 7 | Geaccepteerd-limieten | per IP (dag + uur), per genormaliseerd e-mailadres (3/dag), per formulier (60/dag), globale burst (20/10 min) | Foutmelding `rate_limit` of e-mailveld. |
| 8 | Fingerprint | exact (ip+e-mail+telefoon+bericht+UA) 1/10 min; zelfde berichttekst 3/uur | Melding "zonet al verstuurd". |
| 9 | Blocklist | alleen contact (`blocked_emails`) | Neutrale melding. |
| 10 | Opslag | DB-transactie, daarna tellers 7–8 ophogen | — |
| 11 | Mail | `FormMailer`: kill switch klantmail → ontvanger-check → budget → `MailDispatcher` | Mail overgeslagen, `mail_logs.status = skipped`, aanvraag blijft. |

**Brevo/SMTP wordt pas bereikt in stap 11**, in `MailDispatcher::send()`
(`Mail::to()->send()`), uitsluitend nadat de rij is opgeslagen. Er bestaan
geen events, listeners, observers, queued mailables of jobs die mail sturen;
de admin-mails (offerte, standaardantwoord) zitten achter de `admin`-middleware
en gebruiken `MailDispatcher` rechtstreeks.

Tellers staan in de cache-store (`CACHE_STORE`, productie: database) en zijn
dus gedeeld over alle PHP-workers. Het mailbudget reserveert onder een
`Cache::lock`, zodat twee gelijktijdige requests nooit samen het laatste slot
nemen.

## 3. Mailbudget (circuit breaker)

| Budget | Env | Standaard |
|---|---|---|
| Klantbevestigingen per dag | `CUSTOMER_CONFIRMATION_DAILY_LIMIT` | 100 |
| Adminmeldingen per dag | `ADMIN_NOTIFICATION_DAILY_LIMIT` | 150 |
| Alle formuliermails per uur | `FORM_MAIL_BURST_LIMIT` | 30 |
| Budget aan/uit | `MAIL_GUARD_ENABLED` | true |

Rekensom: maximaal 60 + 60 geaccepteerde inzendingen/dag → 240 mails, plus de
budgetten als harde bovengrens → altijd onder 300 met marge voor offerte- en
antwoordmails uit de admin. Een geblokkeerde mail wordt **nooit** automatisch
alsnog verstuurd; Martin ziet in de aanvraag-activiteit "E-mail niet verstuurd
(mail_budget_customer)" en kan zelf mailen.

## 4. Kill switches

```
CONTACT_FORM_ENABLED=true
REQUEST_WIZARD_ENABLED=true
CUSTOMER_CONFIRMATION_MAIL_ENABLED=true
MAIL_GUARD_ENABLED=true
```

Wijzigen in `.env` en daarna `php artisan config:clear` (of `config:cache`
als dat in productie gebruikt wordt). Geen deploy nodig.

## 5. CAPTCHA-provider

```
CAPTCHA_PROVIDER=turnstile        # of: recaptcha
CAPTCHA_ENABLED=                  # leeg = auto: alleen local/testing/development mogen zonder keys draaien
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```

- **Turnstile** (standaard, aanbevolen): privacyvriendelijk (geen
  Google-cookies, geen impact op de cookie-banner), gratis zonder
  volumelimiet, managed-modus is voor vrijwel alle bezoekers onzichtbaar.
- **reCAPTCHA v2 (checkbox)**: geïmplementeerd als wisselbare fallback met
  dezelfde `SiteVerifyCaptchaVerifier`-basis (zelfde siteverify-contract).
  Alleen inschakelen als Turnstile onbruikbaar wordt. Nooit beide tegelijk:
  de provider wordt op één plaats gebonden (`AppServiceProvider` →
  `CaptchaVerifierFactory`), controllers en views kennen geen provider.
- **Fail closed**: buiten `local`/`testing`/`development` (dus ook bij een typo in `APP_ENV`) zonder keys (of met een onbekende provider)
  weigert `RejectingCaptchaVerifier` elke inzending en logt een error.
  Zet dus de keys **vóór** de release.
- De widget wordt in de wizard pas gerenderd bij het openen van de laatste
  stap (`window.mtRenderCaptcha`), in het contactformulier meteen.

### Turnstile aanmaken (Cloudflare-dashboard, zelf te doen)

1. Cloudflare-dashboard → **Turnstile** → **Add site**.
2. Site name: Mastechnics. Hostname: `mastechnics.be` (en `www.mastechnics.be`).
3. Widget mode: **Managed**. Pre-clearance: uit.
4. Kopieer de **Site key** naar `TURNSTILE_SITE_KEY` en de **Secret key**
   naar `TURNSTILE_SECRET_KEY` in `.env` op de server.
5. `php artisan config:clear` en test beide formulieren in nl/fr/en.

Voor lokaal testen zonder echte keys: laat de keys leeg (challenge uit
buiten productie) of gebruik Cloudflare's testkeys
(`1x00000000000000000000AA` / `1x0000000000000000000000000000000AA`, altijd pass).

## 6. Proxy / IP-detectie (belangrijk voor de limiters)

`Request::ip()` is `REMOTE_ADDR` zolang `TRUSTED_PROXIES` leeg is. Tests
(`FormProtectionTest::test_spoofed_proxy_headers_do_not_reset_any_ip_limiter`)
bewijzen dat `X-Forwarded-For`, `Forwarded` en `X-Real-IP` de tellers niet
beïnvloeden. **Nooit** `TRUSTED_PROXIES=*` zetten.

- **Apache-host zonder proxy (huidige situatie)**: `TRUSTED_PROXIES` leeg laten.
- **Als de site achter de Cloudflare-proxy (oranje wolk) komt**: dan is
  `REMOTE_ADDR` altijd een Cloudflare-IP en delen alle bezoekers één teller.
  Zet dan `TRUSTED_PROXIES` op de Cloudflare-IP-reeksen
  (https://www.cloudflare.com/ips/), komma-gescheiden, bv.
  `TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22,...,2400:cb00::/32,...`.
  Laravel leest dan `X-Forwarded-For` alleen van die reeksen; Cloudflare
  voegt het echte client-IP als laatste toe, een door de bezoeker
  meegestuurde header wordt genegeerd. Controleer de lijst bij elke
  Cloudflare-wijziging. Sluit ook de origin af voor niet-Cloudflare-verkeer
  (firewall), anders kan iemand met een rechtstreekse verbinding naar de
  origin de header alsnog vervalsen.

## 7. Cloudflare WAF / rate limiting (buiten Laravel, zelf te doen)

Alleen zinvol als het domein via de Cloudflare-proxy loopt. Aanbevolen
regels (Security → WAF → Rate limiting rules):

| Regel | Expressie | Drempel | Actie |
|---|---|---|---|
| Contact POST | `(http.request.method eq "POST" and http.request.uri.path matches "^/(nl\|fr\|en)/contact$")` | 5 requests / 10 min per IP | Block 10 min |
| Aanvraag POST | `(http.request.method eq "POST" and http.request.uri.path matches "^/(nl\|fr\|en)/requests$")` | 5 requests / 10 min per IP | Block 10 min |
| Formulier-burst site-breed | zelfde paden, characteristic "Cloudflare-managed" of zonder per-IP | 60 / 10 min | Managed challenge |
| Admin login | `http.request.uri.path eq "/admin/login" and method POST` | 10 / 5 min per IP | Block |

Extra: Bot Fight Mode aanzetten; een WAF custom rule "Managed challenge" voor
POST op de twee formulierpaden wanneer `cf.bot_management.score` (Pro+) laag
is. Geen DNS- of dashboardwijzigingen zijn in deze sprint uitgevoerd.

## 8. Monitoring

- Elke afwijzing: `Log::notice('form_protection.rejected', {form, reason, ip_hash, email_hash, locale})`.
  Elke overgeslagen mail: `Log::warning('form_protection.mail_skipped', …)`.
  Nooit berichtinhoud, adressen of secrets.
- Admin-dashboard: tegel "Spam tegengehouden vandaag (N in 7 d)"; hover toont
  de uitsplitsing en het resterende mailbudget.
- CLI: `php artisan forms:protection-stats --days=7`.
- Redenen: `captcha_failed`, `honeypot`, `fill_time`, `rate_limit_attempts`,
  `rate_limit_ip`, `rate_limit_email`, `rate_limit_form`, `rate_limit_global`,
  `duplicate`, `blocklist`, `form_disabled`, `mail_customer_disabled`,
  `mail_unsafe_recipient`, `mail_budget_customer`, `mail_budget_admin`,
  `mail_budget_burst`.
- Logquery: `grep form_protection storage/logs/laravel.log | grep -c captcha_failed`.

## 9. Limieten (env)

```
CONTACT_ATTEMPT_LIMIT_PER_10_MINUTES=30   REQUEST_ATTEMPT_LIMIT_PER_10_MINUTES=30
GLOBAL_ATTEMPT_LIMIT_PER_10_MINUTES=120
CONTACT_DAILY_LIMIT=10                    REQUEST_DAILY_LIMIT=5        (per IP)
CONTACT_BURST_LIMIT_PER_HOUR=20           REQUEST_BURST_LIMIT_PER_HOUR=10
CONTACT_EMAIL_DAILY_LIMIT=3               REQUEST_EMAIL_DAILY_LIMIT=3
CONTACT_GLOBAL_DAILY_LIMIT=60             REQUEST_GLOBAL_DAILY_LIMIT=60
FORM_ACCEPTED_BURST_LIMIT_PER_10_MINUTES=20
FORM_MIN_FILL_SECONDS=3                   FORM_MAX_FILL_HOURS=12
```

Tellers resetten: `php artisan cache:clear` (wist ook de spamstatistieken).

## 10. Tests die het bewijzen

- `tests/Feature/Spam/FormProtectionTest.php` — per laag: 0 DB-records + 0
  transport-calls (via `tests/Support/SpyMailTransport.php`, een Symfony
  `TransportInterface` die telt in plaats van SMTP te spreken), geldige
  inzending = 1 record + 1 adminmail + 1 klantmail, duplicate token, IP/
  e-mail/formulier/globale limieten, gespoofte proxy-headers, fingerprint,
  klant-/admin-/burstbudget, kill switches, transportfout, onveilige
  ontvanger, providerwissel zonder controllerwijziging, dashboardtelling.
- `tests/Feature/Spam/CaptchaProviderTest.php` — echte verifiers tegen
  `Http::fake()`: geen netwerkcall zonder token, fail closed bij netwerkfout,
  reCAPTCHA-contract, containerbinding volgt config.
- `tests/Unit/Spam/*` — timing-token, ontvanger-veiligheid/normalisatie,
  provider-factory (fail closed in productie zonder keys).
- Bestaande suites (`ContactFormTest`, `RequestWizardHardeningTest`,
  `ProxyHeaderHardeningTest`, …) draaien door de volledige pipeline.

## 11. Bewust niet gedaan

- Geen queue-migratie: er is geen bewezen worker op de host; synchrone
  verzending na de DB-save blijft, alle bescherming zit ervóór.
- Geen disposable-e-mailblokkering: false positives bij echte klanten wegen
  zwaarder dan de winst nu captcha + budgetten het quotum afdekken.
- Turnstile en reCAPTCHA nooit tegelijk zichtbaar.
- Geen SRI op de widget-scripts: beide providers roteren de build achter een
  vaste URL en ondersteunen integrity-attributen niet.
