# HVAC-regels die Martin moet valideren vóór productiegebruik

Statuslegende: **[P]** placeholder (verzonnen startwaarde) · **[V]** te
valideren vakregel · **[F]** fabrikant-specifiek (komt uit productdata zodra
geïmporteerd) · **[OK]** gevalideerd (nog geen enkele regel heeft deze status).

**Het systeem mag niet als productie-klaar beschouwd worden vóór elke regel
hieronder expliciet gevalideerd of aangepast is.** Alle waarden staan in
`config/hvac.php` en worden als regelset v1 in de databank gezet; na
validatie wordt een nieuwe regelsetversie aangemaakt zodat historische
berekeningen ongewijzigd blijven.

## Koellastberekening

| Regel | Huidige standaardwaarde | Status |
|---|---|---|
| Basislast isolatie: uitstekend | 70 W/m² | ☐ te valideren |
| Basislast isolatie: goed | 90 W/m² | ☐ te valideren |
| Basislast isolatie: gemiddeld | 110 W/m² | ☐ te valideren |
| Basislast isolatie: beperkt | 140 W/m² | ☐ te valideren |
| Isolatie "andere"/"onbekend" | 110 W/m² + waarschuwing | ☐ te valideren |
| Hoogtefactor | hoogte / 2,5 m | ☐ te valideren |
| Ligging noord / oost / west / zuid | 0,95 / 1,00 / 1,08 / 1,12 | ☐ te valideren |
| Ramen groot / gemengd / klein / weinig | 1,18 / 1,10 / 1,05 / 1,00 | ☐ te valideren |
| Raamverhoudingstabel (bij gekende raamoppervlakte) | ≤10 %: 1,00 · ≤20 %: 1,05 · ≤30 %: 1,10 · >30 %: 1,18 | ☐ te valideren |
| **Dak-/zoldercorrectie** | **1,00 (bewust neutraal — geen waarde verzonnen)** | ☐ **waarde bepalen** |
| Personenwarmte | eerste persoon inbegrepen, +120 W per extra persoon | ☐ te valideren |
| Aangenomen bezetting per kamertype | slaapkamer 2, woonkamer 3, bureau 1, keuken 2, zolder 2, andere 2 | ☐ te valideren |
| Toestelwarmte tv / pc / open keuken | 100 / 150 / 300 W | ☐ te valideren |
| Aangenomen toestellen per kamertype | woonkamer tv, bureau pc, keuken open keuken, slaapkamer tv | ☐ te valideren |

## Capaciteit & multi-split

| Regel | Waarde | Status |
|---|---|---|
| Klassen | ≤2,2→2,5 · ≤3,2→3,5 · ≤4,6→5,0 · ≤6,3→6,0 · daarboven 7,1 kW of handmatig | ☐ |
| Maximale overdimensionering product | klasse × 1,30 | ☐ |
| Gelijktijdigheid 2 / 3 / 4 / 5+ units | 0,90 / 0,88 / 0,85 / 0,82 | ☐ |

## Leidingen & elektrisch

| Regel | Waarde | Status |
|---|---|---|
| Equivalent: bocht / stijging | +1,0 m per bocht · +0,5 × stijging | ☐ |
| Generieke waarschuwingsdrempel | 15 m | ☐ |
| Aangenomen installatieprofiel | 5 m leiding, 4 bochten, 2,5 m stijging | ☐ |
| Elektrisch 2,5/3,5 kW | 16 A / 3G2.5 | ☐ |
| Elektrisch 5,0/6,0 kW | 20 A / 3G2.5 of 3G4 | ☐ |
| Elektrisch 7,1 kW | 25 A / 3G4 | ☐ |

## Materialen, arbeid, prijzen

| Regel | Waarde | Status |
|---|---|---|
| Muurbeugel / dempers per buitenunit | 1 / 1 set | ☐ |
| Goot- en kabelfactor t.o.v. leiding | ×1,0 / ×1,2 | ☐ |
| Afvoerslang per binnenunit | 5 m | ☐ |
| Basisuren installatie | 6,0 u | ☐ |
| Extra binnenunit | +3,0 u | ☐ |
| Leidingwerk > 5 m | +0,25 u per meter | ☐ |
| Doorboring per kamer | +0,5 u | ☐ [P] |
| Condensaatpomp | +1,0 u | ☐ [P] |
| Toeslag dak/zolder | +2,0 u | ☐ [P] |
| Elektrische aansluiting (schatting) | +1,5 u | ☐ [P] |
| Tweede technieker | vanaf 3 binnenunits, +4,0 u | ☐ [P] |
| Extra koelmiddel gemarkeerd vanaf | 10 m equivalente leidinglengte (optionele regel, hoeveelheid door installateur) | ☐ [V]/[F] |
| Wi-Fi-module | enkel toegevoegd als product expliciet géén Wi-Fi heeft | ☐ [V] |
| **Uurtarief** | **€ 65 excl. btw** | ☐ **[P]** |
| **Verplaatsing** | **€ 35 forfait** | ☐ **[P]** |
| Terugvalmarge zonder verkoopprijs | 35 % op aankoopprijs | ☐ [P] |
| Materiaalopslag | 25 % op aankoopprijs | ☐ [P] |
| Korting | handmatig per optie, verplichte reden, nooit groter dan subtotaal | ☐ [V] |
| Btw | standaard 21 %, 6 % enkel als suggestie bij woning >10 jaar + particulier, altijd handmatig te bevestigen | ☐ [V] |

## Catalogus (leveranciersdata vereist)

- ☐ Echte producten importeren (alle types, incl. toebehoren mét prijzen).
- ☐ Fabrikantcompatibiliteit invoeren: binnen↔buiten (single), multi-split
  ondersteuningstabellen, aansluitbare capaciteitsvensters, leiding- en
  hoogtelimieten per product.
- ☐ Merken/voorkeursleveranciers bevestigen.

Zonder deze data blijft de catalogus leeg en genereert het systeem geen
goedkeurbare aanbevelingen — dat is bewust gedrag, geen fout.
