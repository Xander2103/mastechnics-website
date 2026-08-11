# HVAC end-to-end acceptatierapport — 2026-08-11

Acceptance + hardening sprint over de volledige workflow (aanvraag →
koellast → selectie → toebehoren → arbeid → prijs → aanbeveling →
goedkeuring → offerte → PDF). Alle scenario's zijn geautomatiseerd in
`tests/Feature/Hvac/HvacE2eAcceptanceTest.php` (fictieve TestBrand-data).

## Deel A — Productlijsten-audit (27 vereisten)

24/27 waren correct geïmplementeerd; 5 punten (deels overlappend) zijn in
deze sprint gedicht — commit `72b0cbe`:

| # | Vereiste | Status vóór | Fix |
|---|---|---|---|
| 3 | Elke import hoort bij een lijst | 🟡 sjabloonimport omzeilde lijsten | Sjabloonimport maakt/koppelt nu een template-lijst + run |
| 10 | Laatst gewijzigd zichtbaar | 🟡 | Getoond op lijstdetail |
| 14 | Filters: vermogen + compatibiliteit | 🟡 ontbraken | Koelvermogen van/tot + "Zonder compatibiliteit" toegevoegd |
| 17 | Preview nieuw/gewijzigd/ongewijzigd/ontbreekt | 🟡 alleen nieuw/bijwerken | Bevestigingsstap splitst gewijzigd/ongewijzigd en toont per bestaande lijst "X niet in dit bestand" — vóór er iets geschreven wordt |
| 23 | Bron toont profiel | 🟡 | Leveranciersinstellingen (profiel) in het Bron-blok |

Bewust aanvaarde beperkingen: statussen `superseded`/`failed` worden nog
nergens gezet; het lijstoverzicht pagineert niet (aantal lijsten blijft
klein); "Alle producten" blijft het bestaande vlakke overzicht.

## Deel B — Scenario's

| Scenario | Verwacht | Resultaat | Test |
|---|---|---|---|
| A. Enkele single split, volledige prijzen | draft → goedkeuren → conceptofferte, geen mail | ✅ PASS (Mail::fake bewijst 0 mails) | `test_scenario_a_…` |
| B. 2 kamers multi split | geldige combinatie via fabrikant-compatibiliteit | ✅ PASS — **na fix**: het venster aansluitbaar vermogen wordt nu van de compatibiliteitsrijen gelezen (voorheen genegeerd → altijd handmatige controle) | `test_scenario_b_…` |
| C. Ontbrekende technische input | waarschuwingen/aannames, nooit stil goedgekeurd | ✅ PASS | `test_scenario_c_…` |
| D. Geen compatibele buitenunit | geen aanbeveling / handmatige controle | ✅ PASS | `test_scenario_d_…` |
| E. Product zonder prijs | niet offerteklaar; na handmatige prijs (met reden) wél | ✅ PASS — **na fix**: handmatige prijs heft de blokkade nu op (voorheen permanent geblokkeerd) | `test_scenario_e_…` |
| F. Gearchiveerde lijst | historisch zichtbaar, niet meer selecteerbaar tenzij in andere actieve lijst | ✅ PASS — **nieuw gedrag** (`scopeSelectable`) | `test_scenario_f_…` |
| G. Override na plaatsbezoek | origineel bewaard, reden verplicht, audit | ✅ PASS | `test_scenario_g_…` |
| H. Bestaande offerte | nooit stil overschreven | ✅ PASS | `test_scenario_h_…` |

## Koellast v2

Workbook-regressie (6×5×2,6 m, 5 m² west, binnenzonwering, 3 personen,
250 W, ACH 0,5, veiligheidsfactor 1,1) reproduceert **alle**
tussenwaarden: oppervlakte 30, volume 78, schil 87,2, transmissie 418,56 W,
zon 1.125 W, personen 225/165 W, ventilatie 104,13/50,7 W, sensibel
2.122,69 W, latent 215,7 W, totaal 2.338,39 W → ontwerplast **2.572,229 W**
→ klasse 3,5 (`HvacCoolingLoadV2Test`, tolerantie 0,01). De v2-regelset
blijft **draft**; `HvacRuleSetResolver` selecteert uitsluitend
`status = 'active'` — activering kan alleen expliciet, na validatie door
Martin.

## Gevonden en gefixte fouten (audits + scenario's)

| Ernst | Fout | Fix |
|---|---|---|
| Kritiek | Goedgekeurde aanbeveling kon nog gemuteerd worden (prijs/btw/product) en daarna omgezet — audit toonde de oude cijfers | Statusguard op alle override-endpoints (`hvac_not_editable`) |
| Kritiek | Verouderde goedkeuring (na herberekening) bleef omzetbaar naar offerte | Conversie eist `calculation.status = 'calculated'` |
| Kritiek | Prijsranking: één product zonder prijzen werd "gratis" en kandidaat toch geldig door volgorde-afhankelijke reset | `totalPrice` reset nooit meer; aankoopprijs telt mee in de ranking |
| Hoog | Venster aansluitbaar vermogen van compatibiliteitsrijen (geïmporteerde fabrikantdata) werd volledig genegeerd; `maximum_units` per model idem | Engine leest linkkolommen met productkolommen als fallback |
| Hoog | Handmatige prijs kon `price_source: missing` nooit opheffen → aanbeveling permanent niet goedkeurbaar | Override zet `manual_override` (geauditeerd) |
| Hoog | Leverancier-identiteit hoofdlettergevoelig bij aanmaak → zelfde SKU twee keer als twee producten | Case-insensitive lookup vóór create |
| Hoog | Handmatige offerte-editor aanvaardde `vat_rate=9999`, qty 10⁹ | Grenzen: btw in 0/6/12/21, max qty/prijs |
| Midden | Negatieve marge werd groen getoond, zonder waarschuwing | Rode weergave "NEGATIEF" + `negative_margin`-waarschuwing |
| Midden | `needs_review`-import-vlag en 400V/driefasig onzichtbaar voor de engine | Kandidaat-waarschuwingen in het paneel |
| Midden | Units zonder koelvermogen verdwenen geluidloos uit de selectie | Telling + waarschuwing "X units zonder vermogen overgeslagen" |
| Midden | Offerte-PDF was altijd Nederlands, ook voor FR/EN-klanten | PDF-chrome (labels, voorwaarden, lang) volgt de klanttaal |
| Laag | Negatieve uren mogelijk bij foute regelwaarden | Clamp ≥ 0 op uren/tarief/verplaatsing |
| Laag | "Gevalideerde AI-uitleg" suggereerde inhoudelijke validatie | Label: "technisch gecontroleerd, inhoud zelf nalezen" |
| Perf | Per-optie regelvalidatie-queries + per-binnenunit compatibiliteitsquery | Memoization + één `whereIn`-query; `calculation` eager-loaded |

## Niet gefixt (bewust, gedocumenteerd)

- **Vloer-/daksteun**: wordt nooit automatisch toegevoegd; muurmontage is
  een expliciet gemarkeerde aanname. Vergt een plaatsingsvraag in het
  klantformulier (aparte sprint).
- **Moeilijke toegang / hoogtewerker / parkeren**: geen automatische
  regels; handmatig toe te voegen als offerteregel (geauditeerd).
- **Budget/Aanbevolen/Premium** rangschikt op geldigheid → voorraad →
  prijs; geluidsniveau/SEER/wifi worden getoond maar bepalen de indeling
  niet. Aantal opties is flexibel (0 → handmatig, 1 → alleen aanbevolen).
- **Spanning/fase**: échte afdwinging vergt de netaansluiting van de
  woning (formulier vraagt dat niet); nu zichtbaar als waarschuwing bij
  400V/driefasige producten.
- **Offerteregel-omschrijvingen** blijven Nederlands (PDF-labels zijn wel
  vertaald); `unit` (stuk/m/u) komt niet mee naar de offerteregel.
- **AI-prosecontrole**: de validator blokkeert structuur/HTML/vreemde
  producten, maar controleert de lopende tekst niet op verzonnen cijfers —
  label is daarop aangepast; blast radius is admin-only.

## Resterende handmatige checks (kunnen niet geautomatiseerd)

1. Echte leverancierslijst importeren (CatalogFR-flow is klaar).
2. Echte fabrikant-compatibiliteit importeren (nooit afleiden!).
3. Regelwaarden valideren met Martin (`docs/hvac/rules-to-validate.md`) en
   dan pas de regelset activeren — vóór validatie blokkeert de
   goedkeuringspoort echte catalogusproducten.
4. Eén echte aanvraag end-to-end + PDF visueel nakijken (dompdf-rendering
   is hier alleen als HTML getest).
5. Marge-instellingen (fallback 35% op aankoop) bevestigen.
