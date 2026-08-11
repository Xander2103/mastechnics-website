# HVAC-import: uploadlimieten en serverconfiguratie

De HVAC-importpagina's (producten + compatibiliteit) aanvaarden bestanden tot
`HVAC_IMPORT_MAX_MB` (standaard **25 MB**, instelbaar via `.env`, uitgelezen via
`config('hvac.import.max_upload_mb')`).

## De effectieve limiet is de LAAGSTE van vier lagen

| Laag | Instelling | Wie beheert |
|---|---|---|
| 1. Laravel-validatie | `HVAC_IMPORT_MAX_MB` (default 25 MB) | `.env` |
| 2. PHP per bestand | `upload_max_filesize` | php.ini |
| 3. PHP per request | `post_max_size` (moet > upload_max_filesize) | php.ini |
| 4. Webserver | nginx `client_max_body_size` | serverconfig |

Als laag 2–4 lager staat dan laag 1, wordt het bestand geweigerd **vóór**
Laravel-validatie draait. De importpagina toont dan een vriendelijke melding
(zonder technische serverdetails); de daadwerkelijke oorzaak vind je alleen in
de serverconfiguratie.

## Aanbevolen productiewaarden (bij de standaard van 25 MB)

php.ini:

```ini
upload_max_filesize = 30M
post_max_size = 32M
```

nginx (server- of locationblok van de site):

```nginx
client_max_body_size 32M;
```

> **Niet automatisch aanpassen.** Serverconfiguratie wordt bewust nooit door de
> applicatie of door een deploy-script gewijzigd — pas deze waarden handmatig
> aan en herlaad php-fpm/nginx.

De marge (30/32 boven 25) is bewust: multipart-overhead en het CSRF-veld tellen
mee in `post_max_size`, en zo blijft de Laravel-melding (met duidelijke
MB-waarde) de melding die de gebruiker ziet in plaats van een kale serverfout.

## Probleemoplossing

1. **Melding "Het bestand is groter dan de maximale bestandsgrootte van X MB."**
   → Laravel-validatie: het bestand is groter dan `HVAC_IMPORT_MAX_MB`.
   Verklein het bestand of verhoog de env-waarde (en de serverlimieten mee).
2. **Melding "…groter dan wat de server aanvaardt…" (of een lege 413-pagina)**
   → PHP of nginx weigerde de request vóór Laravel draaide. Controleer de vier
   lagen hierboven; verhoog `upload_max_filesize`, `post_max_size` en/of
   `client_max_body_size` en herlaad de services.
3. **Import lijkt te lukken maar rijen ontbreken**
   → Geen limietprobleem: bekijk het foutenrapport in de previewstap
   (rijvalidatie).

Beveiliging blijft ongewijzigd: MIME-/extensievalidatie, structurele controle,
formule-injectieweigering en rij-per-rij-validatie staan los van de
groottelimiet en mogen nooit uitgeschakeld worden om grotere bestanden door te
laten.
