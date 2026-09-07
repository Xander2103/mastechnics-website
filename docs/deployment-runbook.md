# Mastechnics — Deployment-runbook (voor Xander)

> Status 2026-08-20: er bestaat **geen** deploy-tooling in deze repo (geen CI,
> geen deploy-script, geen serverconfig). De site draait op een Apache-host
> op https://mastechnics.be; hoe de eerste deploy gebeurde is niet
> gedocumenteerd. Dit runbook beschrijft de minimale correcte release voor
> dit specifieke project. Vul de sectie "Hostgegevens" één keer in en dit
> document is daarna het volledige releaseproces.

## Hostgegevens (invullen — ontbreekt in de repo!)

- Provider / controlepaneel: _______________
- Toegang (SSH / SFTP / paneel): _______________
- Document root: _______________
- PHP-versie (vereist ≥ 8.2): _______________
- Databank-engine + locatie (sqlite-bestand of MySQL?): _______________
- Hoe komt code op de server (git pull / upload)?: _______________
- Backuplocatie databank + uploads: _______________

## 0. Vooraf — lokaal (al gebeurd op 2026-08-20)

1. `php artisan test` → 607 passed.
2. `npm run build` → slaagt; `public/build/` is up-to-date.
3. `git push origin main` → alle lokale commits staan op GitHub.

## 1. Backup — VERPLICHT vóór elke release

Er is geen rollbackplan zonder backup.

- Databankbestand (sqlite) of dump (MySQL) veiligstellen.
- `storage/app/public/customer-requests/` (klantuploads) veiligstellen.
- Kopie van het productie-`.env` bewaren (staat nergens anders!).

## 2. Productie-`.env` controleren (NIET overschrijven met een lokale kopie)

Controleer/corrigeer op de server:

```
APP_ENV=production        # LET OP: stond mogelijk fout als "poduction"
APP_DEBUG=false
APP_URL=https://mastechnics.be
LOG_LEVEL=error
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
HVAC_IMPORT_MAX_MB=25
```

- `APP_KEY` **nooit** wijzigen of opnieuw genereren.
- `MAIL_FROM_ADDRESS` moet exact het bij Brevo geverifieerde adres zijn
  (documentatie zegt `no-reply@mastechnics.be`, lokale .env had
  `noreply@…` — verifieer in het Brevo-dashboard welke klopt).
- `COMPANY_NUMBER`, `COMPANY_ADDRESS`, `COMPANY_LOCALITY`,
  `COMPANY_POSTAL_CODE`, `COMPANY_OPENING_HOURS` invullen (footer +
  LocalBusiness-schema tonen anders niets).
- `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` zijn enkel nodig als de
  seeder ooit draait (die weigert nu zonder deze waarden, in élke
  omgeving); adminaccounts maak je normaal met `php artisan admin:create`.
- `TRUSTED_PROXIES` **leeg laten** op de Apache-host (geen reverse proxy).
  Alleen invullen met het echte proxy-IP/CIDR als er ooit een load balancer
  of CDN vóór PHP komt — anders deelt elke bezoeker hetzelfde IP voor de
  rate limiting. `*` betekent "vertrouw iedereen" en maakt IP-spoofing
  via `X-Forwarded-For` mogelijk.
- `APP_URL` is nu de enige bron voor absolute URL's (canonical, sitemap,
  maillinks): een verkeerde waarde is direct zichtbaar in de sitemap.
- Na de release éénmalig `php artisan attachments:move-to-private`
  draaien: bestaande klantuploads verhuizen van `storage/app/public` naar
  `storage/app/private`. Tot dan leest de admin-download ze nog van de
  oude locatie; nieuwe uploads komen meteen op de private disk.

## 3. Release-stappen op de server

```
php artisan down
# code binnenhalen (git pull origin main, of upload)
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # additief; incl. submission_token, validated_value, indexen
php artisan storage:link           # controleren dat de link klopt
php artisan attachments:move-to-private   # eenmalig: klantuploads van public naar private disk (herhaalbaar)
npm ci && npm run build            # OF: lokaal gebouwde public/build/ uploaden
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Let op:

- `public/build/` is gitignored — zonder buildstap of upload geeft elke
  pagina een Vite-manifestfout (500).
- **Nooit** `composer setup` draaien op productie (bevat `key:generate`).
- **Nooit** `migrate:fresh` of een seeder op productie.
- Uploadlimieten: php.ini `upload_max_filesize=30M`, `post_max_size=32M`.
  De host is Apache — de nginx-regel in `docs/hvac/import-deployment.md`
  (`client_max_body_size`) is daar niet van toepassing; controleer of er
  een `LimitRequestBody` actief is.

## 4. Wat NIET nodig is

- Geen queue-worker (`queue:work`): niets wordt gequeued, alle mail is
  synchroon.
- Geen cron: er zijn geen geplande taken.

## 5. Smoketests na de release

1. `https://mastechnics.be/up` → 200.
2. `/nl`, `/fr`, `/en` renderen (assets laden, geen manifestfout).
3. `/sitemap.xml` en `/robots.txt` correct, canonicals op https.
4. `/nl/aanvraag` formulier laadt; contactpagina laadt.
5. `/admin/login` → inloggen lukt; `/admin/requests` toont de lijst.
6. Bijlage van een bestaande aanvraag openen (bewijst `storage:link`).
7. `/admin/hvac/products` → Productlijsten-overzicht; een lijst openen.
8. `/admin/hvac/import` laadt; `/admin/hvac/rules` toont de regelset.
9. Offerte van een bestaande aanvraag: `↓ PDF` rendert.
10. HTTPS-slot + HSTS-header aanwezig.

Maak **geen** testaanvragen aan op productie; er is geen opruimmechanisme.

## 6. Gekende open punten (bewust niet in deze release)

- ~~Klantuploads op de public disk~~ — opgelost (audit 2026-09-07): nieuwe
  uploads staan op de private disk; bestaande bestanden verhuizen met
  `php artisan attachments:move-to-private` (zie release-stappen).
- ~~`trustProxies('*')`~~ — opgelost: proxies worden alleen vertrouwd via
  `TRUSTED_PROXIES` (standaard leeg).
- `.env.example` documenteert `MAIL_ENCRYPTION` niet (`ADMIN_*` en
  `TRUSTED_PROXIES` inmiddels wel).
- **Productie-databank-engine is niet gedocumenteerd.** Op SQLite houdt
  één grote HVAC-import (één transactie) de hele site kort vast
  (`database is locked` bij sessies/cache in dezelfde file); overweeg
  `SESSION_DRIVER=file` en `CACHE_STORE=file` op SQLite, of MySQL.
- De guided import leest grote CSV's (50k+ rijen) nog volledig in het
  geheugen (≈300 MB piek bij 60k rijen); een `memory_limit` van 512M op
  de importroute of kleinere bestanden zijn nodig tot dit streaming wordt.
- Afspraken in het verleden worden aanvaard (`date` zonder
  `after_or_equal:today`) — bewust gelaten: Martin registreert soms
  achteraf.
