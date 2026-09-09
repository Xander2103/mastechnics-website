# Pre-mail trust gate, externe-mail-noodrem en beveiligingslog — ontwerp

Datum: 2026-09-09 · Status: goedgekeurd (vereisten aangeleverd door opdrachtgever, ontwerp afgeleid uit audit) · Sprint 21

## 1. Aanleiding en root cause

Brevo toont op 09/09/2026 (rond 02:10) opnieuw honderden logs en het
dagquotum (300) raakt op, terwijl sprint 20 (captcha, honeypots, invultijd,
limieten, mailbudget) live staat. De productielog en -database zijn vanaf de
ontwikkelmachine niet bereikbaar (lokale `laravel.log` bevat alleen
testruns, lokale SQLite is leeg); de analyse hieronder volgt uit de code
zoals die in productie draait, en klopt met de symptomen (honderden mails
's nachts, roterende IP's, willekeurige adressen). Sectie 9 geeft de
queries waarmee Martin/Xander de aanname op de server verifieert.

### Waarom de bot door alle lagen kwam

| Laag (sprint 20) | Waarom die niet hielp tegen deze bot |
|---|---|
| Turnstile | Werd opgelost (solver-service of headless browser). De server controleerde alleen `success=true`; `hostname`, `action` en `challenge_ts` uit het siteverify-antwoord werden genegeerd. Een token dat voor een andere hostname of zonder action werd uitgegeven, werd aanvaard. |
| Honeypots | Bot laat verborgen velden leeg. |
| Invultijd (`form_opened_at`) | Het token is 12 uur geldig, niet gebonden aan IP/sessie en **niet single-use**: één GET levert een token dat de bot voor elke volgende POST hergebruikt. "Minimaal 3 s" is dan altijd voldaan. |
| Per-IP limieten (pogingen, geaccepteerd) | Roterende IP's: elke POST komt van een nieuw adres, de per-IP-tellers blijven op 1. |
| Per-e-mail limiet (3/dag) | Willekeurige adressen: elk adres is nieuw. |
| Fingerprint exact | Bevat IP + e-mail → nooit gelijk. |
| Fingerprint inhoud (3/uur) | Alleen bij identieke tekst ≥ 20 tekens. Gevarieerde teksten, of een wizard-inzending met korte/lege omschrijving, worden niet gevangen. |
| Formulierlimiet 60/dag/formulier + globale burst 20/10 min | **Ontwerpkeuze van sprint 20**: de limieten zijn gedimensioneerd om *onder* 300 te blijven, niet om spam van het quotum weg te houden. 60 contact + 60 aanvraag = 120 geaccepteerde inzendingen/dag × 2 mails = 240 mails. |
| Mailbudget (klant 100/dag, admin 150/dag, 30/uur) | Zelfde probleem: 30 mails/uur × 8 nachtelijke uren = 240 mails. Het budget beschermt tegen overschrijding van 300, niet tegen consumptie. |

**Root cause in één zin:** elke inzending die captcha + honeypot + invultijd
passeert, wordt als "mens" behandeld en kost onmiddellijk twee Brevo-mails;
alle overige lagen zijn per-IP/per-adres of hebben limieten die opgeteld
nog altijd ~240 spammails per dag toelaten. Een bot die de captcha laat
oplossen en IP + adres roteert, heeft dus vrij spel tot de daglimieten.

Bijkomende gaten die deze sprint dicht: hostname/action van Turnstile
niet gecontroleerd, invultijdtoken herbruikbaar, geen similariteitsdetectie,
geen globale velocity-signaal, geen browserconsistentie-controle, geen
zicht in de admin op wat er gebeurt.

## 2. Doel en niet-doelen

Doel: spam kan het Brevo-quotum praktisch niet meer consumeren, ook niet als
Turnstile wordt opgelost. Alleen inzendingen die op **meerdere onafhankelijke
signalen** als mens scoren, veroorzaken automatisch mail. Twijfelgevallen
worden **wel opgeslagen** (status `needs_review`), veroorzaken **0 mails** en
zijn zichtbaar in de admin. Daarbovenop een harde, gezamenlijke noodrem op
alle formulier-gerelateerde externe mails.

Niet-doelen: push, deploy, DNS/Cloudflare-wijzigingen (alleen advies),
queue-migratie, ML/AI-classificatie, externe IP-reputatiediensten (geen
netwerkafhankelijkheid in de POST-flow behalve siteverify en een gecachte
MX-lookup).

## 3. Beslissingsmodel: trusted / needs_review / blocked

```
POST → kill switch → idempotency → attempt-limits (429) → validatie
     → TrustEvaluator (alle signalen, deterministisch)
        ├─ blocked      → niets opgeslagen, niets gemaild (zoals nu), securityevent
        ├─ needs_review → opgeslagen met trust_verdict=needs_review, 0 mails, securityevent
        └─ trusted      → opgeslagen, FormMailer → circuit breaker → MailDispatcher, securityevent
```

### 3.1 Harde blokkades (blocked, ongewijzigd gedrag + nieuw)

Onveranderd: captcha niet geslaagd, honeypot, invultijd ontbreekt/vervalst/te
snel/verlopen, IP-/e-mail-/formulier-/globale limieten, exacte of
inhoudsduplicaat, blocklist. Nieuw: captcha geslaagd maar **hostname of
action klopt niet** → blocked. Nieuw: risicoscore ≥ `block_score` → blocked.

### 3.2 Scoremodel (deterministisch, configureerbaar)

`TrustEvaluator` verzamelt `TrustSignal`s. Elk signaal heeft een code, een
risicogewicht (≥ 0) en een vlag `positive` (bewijs van menselijkheid). Twee
uitkomsten worden geteld: `risk` (som gewichten) en `positives` (aantal
positieve signalen).

| Beslissing | Voorwaarde |
|---|---|
| **trusted** | `risk ≤ trusted_max_risk` (2) **én** `positives ≥ trusted_min_positives` (3) **én** geen aanvalsmodus |
| **blocked** | harde blokkade, of `risk ≥ block_score` (12) |
| **needs_review** | alles daartussen |

Geen enkel signaal is op zichzelf voldoende voor trusted: captcha geslaagd
levert precies één positief signaal; er zijn er minstens drie nodig
(bv. captcha + normale invultijd + consistente browser), en de risicosom
moet laag zijn. Eén negatief signaal van gewicht 3 (bv. hergebruikt
invultijdtoken, wegwerpdomein, geen browser-UA) maakt een inzending al
needs_review.

### 3.3 Signalen

| Groep | Signaal | Risico | Positief |
|---|---|---|---|
| captcha | geslaagd + hostname ok + action ok | 0 | ✓ |
| captcha | challenge_ts ouder dan `captcha.max_token_age_seconds` (300) | 2 | |
| captcha | uitgeschakeld (dev) | 0 | – (geen positief) |
| invultijd | normaal (`fast_seconds` ≤ t ≤ 6 u) | 0 | ✓ |
| invultijd | snel (3 s ≤ t < `fast_seconds`: contact 15 s, aanvraag 40 s) | 2 | |
| invultijd | zeer oud (> 6 u, nog niet verlopen) | 1 | |
| invultijd | **token hergebruikt** (zelfde `form_opened_at` al eerder gepost) | 4 | |
| browser | geen User-Agent, of bekende non-browser (curl, python, Go-http, okhttp, wget, java, node-fetch, axios, headless) | 4 | |
| browser | UA is browser, maar `Sec-Fetch-Site`/`Sec-Fetch-Mode` ontbreken (elke moderne browser stuurt ze bij een formulier-POST) | 2 | |
| browser | `Accept` bevat geen `text/html` | 2 | |
| browser | `Accept-Language` ontbreekt | 1 | |
| browser | alles consistent | 0 | ✓ |
| e-mail | domein zonder MX/A-record (gecachte lookup, fail-open bij fout) | 3 | |
| e-mail | wegwerpdomein (kleine ingebouwde lijst + config) | 3 | |
| e-mail | lokaal deel ziet er willekeurig uit (≥ 40 % cijfers, of ≥ 12 tekens zonder klinker, of ≥ 6 opeenvolgende medeklinkers) | 2 | |
| e-mail | plausibel (MX ok, niet wegwerp, niet willekeurig) | 0 | ✓ |
| e-mail | naam komt terug in het lokale deel (bv. "jan" in jan.janssens@) | 0 | ✓ |
| inhoud | naam of bericht bevat URL(s): ≥ 2 URL's | 2 | |
| inhoud | naam bevat URL/`http`/`@`/cijferreeks | 2 | |
| inhoud | bericht in niet-Latijns schrift (Cyrillisch/CJK/Arabisch) op een nl/fr/en-site | 2 | |
| inhoud | naam en bericht bestaan uit dezelfde willekeurige token (bv. "xkq8Fz") | 2 | |
| inhoud | **similariteit**: simhash van genormaliseerd bericht binnen Hamming-afstand ≤ 6 van een bericht van de laatste uur (cache-venster, max 200 hashes) | 3 | |
| inhoud | zelfde naam (genormaliseerd) al ≥ 3× geaccepteerd in het laatste uur met andere adressen | 2 | |
| telefoon | Belgisch-plausibel (+32/0 + 8–9 cijfers) | 0 | ✓ |
| aanvraag | bijlage(n) meegestuurd, of kamers ingevuld | 0 | ✓ |
| IP | dit IP kreeg in de laatste 24 u al een afwijzing/needs_review | 2 | |
| IP | dit IP gebruikte in het laatste uur ≥ 3 verschillende adressen | 3 | |
| IP | privé/reserved adres in productie (proxy-misconfiguratie) | 1 | |
| velocity | geaccepteerde inzendingen site-breed laatste 10 min ≥ `velocity.review_per_10_minutes` (5) | 2 | |
| velocity | idem ≥ `velocity.attack_per_10_minutes` (10) of laatste uur ≥ `velocity.attack_per_hour` (25) → **aanvalsmodus**: geen trusted mogelijk | 4 | |
| velocity | ≥ 3 inzendingen laatste 10 min met dezelfde UA-hash maar verschillende IP's | 3 | |
| locale | `Accept-Language` bevat geen nl/fr/en/de terwijl formulier nl/fr | 1 | |

Gewichten en drempels staan in `config/form-protection.php` (`trust`) zodat
ze zonder code-wijziging bijgesteld kunnen worden; de defaults hierboven
zijn conservatief in de richting van needs_review (een echte klant die
onterecht needs_review krijgt, wordt gewoon opgeslagen en door Martin
gezien; een bot die onterecht trusted krijgt, kost quota).

### 3.4 Turnstile-audit

`SiteVerifyCaptchaVerifier::verifyDetailed()` geeft een `CaptchaVerdict`
(`success`, `hostname`, `action`, `challengeTs`, `errorCodes`,
`hostnameOk`, `actionOk`). Verwachte hostnames: host van `APP_URL` (met en
zonder `www.`) plus `CAPTCHA_EXPECTED_HOSTNAMES` (komma-gescheiden).
Verwachte action: de formulier-id (`contact` / `request`), gerenderd als
`data-action` op de Turnstile-widget (Turnstile stuurt die terug in
siteverify). reCAPTCHA v2 ondersteunt geen action: dan wordt alleen
hostname gecontroleerd (`actionOk = null`). `CAPTCHA_VERIFY_HOSTNAME`
(default true) en `CAPTCHA_VERIFY_ACTION` (default true) kunnen alleen uit
in noodgevallen. Mismatch = blocked, met reden `captcha_hostname` /
`captcha_action` in het securityevent. Bestaand `verify(): bool` blijft
bestaan en betekent nu: success én hostname én action ok.

## 4. Opslag van needs_review

Beide tabellen (`customer_requests`, `contact_submissions`) krijgen:

- `trust_verdict` string(20) not null default `trusted` (index) —
  `trusted` | `needs_review` | `spam` (door admin bevestigd)
- `trust_score` smallint unsigned null
- `trust_reasons` json null (lijst van signaalcodes, geen persoonsgegevens)
- `trust_reviewed_at` timestamp null, `trust_reviewed_by` string null

Bestaande rijen krijgen door de default `trusted`. De `status`-workflow van
aanvragen blijft ongemoeid (`new` … `won`); `trust_verdict` is een aparte
dimensie. Aanvragen in needs_review tellen in het dashboard niet mee bij
"Nieuw" (aparte tegel), staan wél in de lijst met een badge "Te
controleren" en een filter.

Adminacties (bestaande `admin`-middleware, POST + CSRF):

- **Vrijgeven** (`trust_verdict → trusted`, `trust_reviewed_*` gezet):
  stuurt alsnog de adminmelding en, als klantbevestigingen aanstaan, de
  klantbevestiging via `FormMailer` (dus onder de circuit breaker). Nooit
  automatisch.
- **Markeer als spam** (`trust_verdict → spam`): geen mail, blijft
  bewaard (Martin kan het adres daarna blokkeren via de bestaande
  blocklist).

Voor contactinzendingen bestaat nog geen adminscherm; er komt een minimale
lijst (`/admin/contact-submissions`, filter op verdict) en een detailpagina
met dezelfde twee acties. Geen bewerkfunctie.

## 5. Mail: structurele borging + noodrem

### 5.1 Alleen trusted mag mailen

`FormMailer::sendAdmin()` / `sendCustomer()` krijgen de `TrustDecision`
als verplichte parameter en weigeren (skip, reden `trust_needs_review` /
`trust_blocked`) wanneer die niet `trusted` is. Een controller kan dus niet
"vergeten" te controleren. `CUSTOMER_CONFIRMATION_MAIL_ENABLED=false` (nieuwe
default: **false**) stopt vóór elke transport-call; de mailable wordt niet
eens opgebouwd.

### 5.2 Gezamenlijke externe-mailnoodrem (circuit breaker)

`MailBudget` krijgt twee gezamenlijke tellers vóór de bestaande per-soort
tellers, onder dezelfde `Cache::lock`:

| Teller | Env | Default |
|---|---|---|
| Alle formulier-mails (admin + klant) per dag | `FORM_EXTERNAL_MAIL_DAILY_LIMIT` | 20 |
| Alle formulier-mails per 10 minuten | `FORM_EXTERNAL_MAIL_LIMIT_PER_10_MINUTES` | 4 |

Redenen: `mail_circuit_daily`, `mail_circuit_burst`. Zodra een teller vol
is: 0 verdere transport-calls vanuit de publieke formulieren tot het venster
verloopt; inzendingen blijven opgeslagen (`mail_logs.status = skipped`).
`MailBudget::circuitState()` levert `open` (bool), gebruikt/limiet per
teller en de reden, voor dashboard en CLI. De bestaande per-soort
budgetten blijven als tweede vangnet. Admin-geïnitieerde mails (offerte,
standaardantwoord) lopen niet via FormMailer en vallen buiten deze teller.

## 6. Beveiligingslog (`form_security_events`)

Eén rij per beslissing, geschreven door `SecurityEventRecorder`, altijd in
`try/catch`: falen van de log mag nooit een 500, een tweede mail of een
gewijzigde beslissing veroorzaken (failure mode: `Log::error` + doorgaan).

Kolommen: `occurred_at`, `form`, `decision` (trusted/needs_review/blocked),
`risk_score`, `risk_level` (low/medium/high), `reasons` json (codes),
`signals` json (per groep: captcha, timing, browser, email, content, ip,
velocity, rate_limit — alleen codes/buckets, geen waarden),
`captcha_status` (passed/failed/missing/disabled/hostname_mismatch/
action_mismatch), `captcha_hostname_ok` bool null, `captcha_action_ok`
bool null, `fill_time_bucket` (missing/invalid/too_fast/fast/normal/slow/
expired), `fill_time_seconds` int null, `attack_mode` bool, `mail_admin`
(sent/skipped/not_applicable), `mail_customer` (idem), `mail_reason`
string null, `mails_prevented` tinyint, `ip_hash` (16 hex), `email_masked`
(`xa***@gmail.com`), `email_hash` (16 hex), `locale`, `user_agent_family`
(bv. "Chrome 128 · Windows", geparsed, max 60 tekens), `subject_type` /
`subject_id` (morph naar de opgeslagen rij), `created_at`.
Indexen: (`occurred_at`), (`form`, `decision`, `occurred_at`).

Niet opgeslagen: Turnstile-token/secret, volledige headers, volledige UA,
berichttekst, naam, volledig IP, volledig e-mailadres.

Retention: `FORM_SECURITY_LOG_RETENTION_DAYS` (default 90);
`php artisan forms:prune-security-log` dagelijks gepland in
`routes/console.php`.

## 7. Admin: tegel + beveiligingslog

Dashboard (`/admin/requests`): tegel **Formulierbeveiliging** met
Geblokkeerd vandaag · Te controleren vandaag · Vertrouwd vandaag ·
Brevo-mails voorkomen (som `mails_prevented` vandaag) · Externe form-mails
vandaag / limiet · circuit-breaker-status (Actief / Open — geen mail meer
tot HH:MM / Uitgeschakeld) · knop **Bekijk beveiligingslog**. Rode band
"Aanval actief" als de deterministische aanvalsdrempel (sectie 3.3
velocity, berekend over de events van de laatste 10 min/uur) is bereikt.

Pagina `/admin/security-log`: filters datum van/tot, formulier, beslissing,
reden, mail verzonden/niet; tabel (25/pagina, paginator) met tijd,
formulier, beslissing, score, redenen (labels), captcha, invultijd,
mailactie, IP-hash, gemaskeerd adres, UA-familie, link naar de opgeslagen
aanvraag/contactinzending. Dutch-only, bestaande admin-CSS-klassen.

## 8. Tests (allemaal via `SpyMailTransport`, 0 = transport nooit bereikt)

Trust gate: captcha-succes alleen (geen andere positieven) → opgeslagen als
needs_review, 0 transport; suspicious → DB-rij + 0 transport; trusted →
exact 1 adminmail (+ 1 klantmail als aan); hostname-mismatch → 0 DB + 0
mail; action-mismatch → idem; hergebruikt invultijdtoken → needs_review;
aanvalsmodus → needs_review voor iedereen; verschillende IP's + adressen
kunnen de daglimiet niet omzeilen; burst 4/10 min → 5e = 0 transport;
daglimiet 20 → 21e = 0 transport; klantbevestiging uit → 0 klant-calls;
concurrency: lock-gebruik. Securitylog: blocked/needs_review/trusted
verschijnen, needs_review linkt naar rij, 0-mail zichtbaar, geen
token/secret/headers/bericht in de rij, niet-admin → redirect login,
filters + paginatie, recorder-fout breekt de flow niet, prune-command.
Turnstile: `Http::fake()` met hostname/action in het antwoord.

## 9. Verificatie op de server (voor Martin/Xander)

```
grep "form_protection" storage/logs/laravel.log | grep "2026-09-09 02:" | cut -c1-200 | sort | uniq -c | sort -rn | head
php artisan tinker --execute="echo DB::table('mail_logs')->where('created_at','>=','2026-09-09 00:00')->selectRaw('status, count(*) c')->groupBy('status')->get();"
php artisan tinker --execute="echo DB::table('customer_requests')->where('created_at','>=','2026-09-09 00:00')->selectRaw(\"substr(created_at,1,13) h, count(*) c\")->groupBy('h')->get();"
php artisan tinker --execute="echo DB::table('contact_submissions')->where('created_at','>=','2026-09-09 00:00')->selectRaw(\"substr(created_at,1,13) h, count(*) c\")->groupBy('h')->get();"
php artisan forms:protection-stats --days=2
```

Verwachting: `mail_logs.status = sent` ≈ 2 × aantal rijen; nul of weinig
`form_protection.rejected` met `captcha_failed` rond 02:10 (captcha werd
opgelost), wél `rate_limit_global`/`mail_budget_burst` zodra de tellers
vol zaten. Als er in de log **geen** `captcha_failed` én geen Turnstile-
siteverify-calls zichtbaar zijn, controleer `TURNSTILE_SECRET_KEY` op de
server op de testsleutel `1x0000000000000000000000000000000AA` (altijd pass).
