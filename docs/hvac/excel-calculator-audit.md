# Audit: "Belgian Deterministic Air-Conditioning Calculator" (Excel-werkboek)

Bestand: `docs/hvac/Belgian_AC_Quotation_Calculator.xlsx` (kopie van het referentiebestand,
origineel `Belgian_AC_Quotation_Calculator.xlsx`, 5 996 bytes).
Geïnspecteerd op 2026-08-11 door het XLSX-archief rechtstreeks uit te pakken en de
OOXML-bron te lezen (`xl/workbook.xml`, `xl/worksheets/sheet1.xml`). Er is **geen**
macro-code aanwezig en er zijn **geen** formules uitgevoerd tijdens de inspectie.

> **Status:** dit werkboek is een **referentie-rekenmodel** voor de koellastberekening.
> Het is GEEN leverancierscatalogus en mag nooit in `hvac_products` geïmporteerd worden.

---

## 1. Werkboekstructuur

| Onderdeel | Vaststelling |
|---|---|
| Werkbladen | Eén blad: **"AC Calculator"**, zichtbaar (`state="visible"`) |
| Verborgen bladen | Geen |
| Macro's / VBA | Geen (`vbaProject.bin` afwezig; bestandstype .xlsx, niet .xlsm) |
| Benoemde bereiken | Geen (`<definedNames/>` is leeg) |
| Opzoektabellen | Geen aparte tabellen — alle "lookups" zijn geneste `IF`-formules in E3:E5 |
| Externe verwijzingen | Geen |
| Berekende waarden in cache | Geen (`<v>` leeg, `fullCalcOnLoad="1"`), dus de zichtbare waarden komen uitsluitend uit herberekening door de spreadsheet-app |
| Celbereik | `A1:E30` |

Indeling van het blad:

- **A1** — titel.
- **A3:B13** — invoer (11 velden).
- **D3:E5** — afgeleide constanten (`qsol`, `Fs`, `Ueq`) via geneste IF's op de invoer.
- **A16:B30** — uitvoer (berekeningspijplijn).

### Validatielijsten (dropdowns)

| Cel | Veld | Toegelaten waarden |
|---|---|---|
| B7 | Orientation | `North`, `East`, `South`, `West` |
| B8 | Shading | `None`, `Internal blinds`, `External screen / shutter` |
| B9 | Building quality | `New`, `Recent insulated`, `Older insulated`, `Old uninsulated` |

---

## 2. Formules (volledig gereconstrueerd uit de XML-bron)

Alle formules hieronder zijn letterlijk uit het bestand gelezen — niets is geraden.

### 2.1 Afgeleide constanten

| Cel | Excel-formule | Wiskundig | Eenheid | Afhankelijk van |
|---|---|---|---|---|
| E3 `qsol` | `IF(B7="North",120,IF(B7="East",230,IF(B7="South",280,300)))` | qsol = f(oriëntatie): N=120, O=230, Z=280, **anders (incl. West)=300** | W/m² raam | B7 |
| E4 `Fs` | `IF(B8="None",1,IF(B8="Internal blinds",0.75,0.35))` | Fs = f(zonwering): geen=1,00, binnenzonwering=0,75, **anders (buitenzonwering/rolluik)=0,35** | — | B8 |
| E5 `Ueq` | `IF(B9="New",0.35,IF(B9="Recent insulated",0.6,IF(B9="Older insulated",0.9,1.4)))` | Ueq = f(bouwkwaliteit): nieuw=0,35, recent geïsoleerd=0,60, ouder geïsoleerd=0,90, **anders (oud ongeïsoleerd)=1,40** | W/(m²·K) | B9 |

Let op: de `IF`-ketens vallen bij een onbekende waarde altijd terug op de **laatste**
(meest ongunstige of West-)waarde. Dat is impliciet gedrag van het werkboek.

### 2.2 Berekeningspijplijn

| # | Cel | Excel-formule | Wiskundig | Eenheid | Aannames |
|---|---|---|---|---|---|
| 1 | B16 Area | `B3*B4` | A = L × B | m² | rechthoekige ruimte |
| 2 | B17 Volume | `B3*B4*B5` | V = L × B × H | m³ | |
| 3 | B18 Envelope | `2*B5*(B3+B4)+(B3*B4)` | A_env = 2·H·(L+B) + L·B | m² | **4 wanden + plafond, géén vloer**; alle wanden tellen als buitenschil |
| 4 | B19 Qtrans | `E5*B18*8` | Q_trans = Ueq · A_env · ΔT, met **ΔT = 8 K vast** | W | Belgische zomerontwerp-ΔT ≈ 8 K (bv. 32 °C buiten / 24 °C binnen), hardcoded |
| 5 | B20 Qsol | `B6*E3*E4` | Q_sol = A_raam · qsol · Fs | W | raamoppervlakte is invoer; oriëntatie geldt voor álle ramen samen |
| 6 | B21 People sens. | `B10*75` | Q_p,s = n · 75 | W | 75 W voelbaar per persoon (zittend werk) |
| 7 | B22 People lat. | `B10*55` | Q_p,l = n · 55 | W | 55 W latent per persoon |
| 8 | B23 Qvent sens. | `2.67*B12*B17` | Q_v,s = 2,67 · ACH · V | W | 2,67 ≈ 0,334 Wh/(m³·K) · 8 K — de lucht-warmtecapaciteitscoëfficiënt maal dezelfde ΔT van 8 K, als **één letterlijke constante** |
| 9 | B24 Qvent lat. | `1.3*B12*B17` | Q_v,l = 1,3 · ACH · V | W | impliciet vochtverschil binnen/buiten in één constante |
| 10 | B25 Qs totaal | `B19+B20+B21+B11+B23` | Q_s = Q_trans + Q_sol + Q_p,s + Q_app + Q_v,s | W | apparatuur (B11) telt volledig als voelbaar |
| 11 | B26 Ql totaal | `B22+B24` | Q_l = Q_p,l + Q_v,l | W | |
| 12 | B27 Qt totaal | `B25+B26` | Q_t = Q_s + Q_l | W | |
| 13 | B28 Design load | `B27*B13` | Q_ontwerp = Q_t · SF | W | veiligheidsfactor als invoer (voorbeeld 1,1) |
| 14 | B29 Design load | `B28/1000` | kW-conversie | kW | |
| 15 | B30 Recommended size | `IFS(B29<=2.5,"2.5 kW / 9000 BTU",B29<=3.5,"3.5 kW / 12000 BTU",B29<=5,"5.0 kW / 18000 BTU",B29<=6.8,"6.8 kW / 24000 BTU",B29<=8,"8.0 kW",TRUE,"10.0 kW")` | klasse-indeling op ontwerpbelasting | — | zie §5: cel toont `#NAME?` |

### 2.3 Celafhankelijkheden

```
B3 (L) ─┬─→ B16 Area ──→ B18 Envelope ──→ B19 Qtrans ─────────────┐
B4 (B) ─┤        └─→ B17 Volume ─┬─→ B23 QventS ──────────────────┤
B5 (H) ─┘                        └─→ B24 QventL ──→ B26 Ql ─┐     │
B6 (raam) ──→ B20 Qsol ←── E3 qsol ←── B7 oriëntatie        │     ├─→ B25 Qs ─→ B27 Qt ─→ B28 W ─→ B29 kW ─→ B30 klasse
              ↑                                             │     │
E4 Fs ←── B8 zonwering                                      │     │
E5 Ueq ←── B9 bouwkwaliteit ────────────────────────────────┼─────┘
B10 personen ─→ B21 sens ─────────────────────────────────────────┘
       └─────→ B22 lat ─→ B26 Ql
B11 apparatuur ─→ B25 Qs      B12 ACH ─→ B23/B24      B13 SF ─→ B28
```

### 2.4 Rekenvoorbeeld (gecontroleerd)

Invoer: L=6, B=5, H=2,6, raam=5 m², West, binnenzonwering, recent geïsoleerd,
3 personen, 250 W apparatuur, ACH=0,5, SF=1,1.

| Grootheid | Formule ingevuld | Resultaat | Werkboek |
|---|---|---|---|
| Area | 6×5 | 30 | 30 ✓ |
| Volume | 30×2,6 | 78 | 78 ✓ |
| Envelope | 2×2,6×11 + 30 | 87,2 | 87,2 ✓ |
| Qtrans | 0,6×87,2×8 | 418,56 | 418,56 ✓ |
| Qsol | 5×300×0,75 | 1 125 | 1 125 ✓ |
| People sens | 3×75 | 225 | 225 ✓ |
| People lat | 3×55 | 165 | 165 ✓ |
| Qvent sens | 2,67×0,5×78 | 104,13 | 104,13 ✓ |
| Qvent lat | 1,3×0,5×78 | 50,7 | 50,7 ✓ |
| Qs | 418,56+1125+225+250+104,13 | 2 122,69 | 2 122,69 ✓ |
| Ql | 165+50,7 | 215,7 | 215,7 ✓ |
| Qt | 2122,69+215,7 | 2 338,39 | 2 338,39 ✓ |
| Design | 2338,39×1,1 | **2 572,229 W** | 2 572,229 ✓ |

Alle formules zijn dus **volledig en met zekerheid** gereconstrueerd; het model is
deterministisch en compleet, met uitzondering van B30 (zie §5).

---

## 3. Vergelijking met de huidige Laravel-engine (regelset v1)

v1 (in `config/hvac.php` → `hvac_rule_sets` versie 1, uitgevoerd door
`CoolingLoadCalculator`) rekent per kamer:

```
basis_W = oppervlakte × W/m²(isolatie) × hoogtefactor × oriëntatiefactor × raamfactor × dakfactor
eind_W  = basis_W + bezettingswarmte + apparatuurwarmte
```

| Aspect | Laravel v1 | Excel-werkboek | Classificatie |
|---|---|---|---|
| Geometrie | oppervlakte = B×L; hoogte via factor H/2,5 | oppervlakte, volume én schiloppervlak expliciet | **Gedetailleerder in Excel** (fysisch volume/schil i.p.v. verhoudingsfactor) |
| Transmissie | impliciet in W/m² per isolatieklasse (70–140 W/m²) | expliciet: Ueq · A_schil · ΔT(8 K) | **Gedetailleerder in Excel**; ook **conflict** in vorm: v1 schaalt met vloeroppervlak, Excel met schiloppervlak |
| Isolatieklassen | excellent/good/average/poor → 70/90/110/140 W/m² | New/Recent/Older/Old → Ueq 0,35/0,6/0,9/1,4 W/m²K | **Equivalent qua intentie**, andere parametrisering; mapping nodig |
| Zonnewinst | raam*type*factor (1,00–1,18 multiplicatief) | raam*oppervlakte* × qsol(oriëntatie) × Fs(zonwering), additief in W | **Gedetailleerder in Excel** |
| Oriëntatie | multiplicatieve factor 0,95–1,12 op de hele basislast | qsol-waarde 120–300 W/m² alleen op het raamdeel | **Conflict** (ander mechanisme); Excel fysisch correcter |
| Zonwering | **ontbreekt** | Fs 1,00/0,75/0,35 | **Ontbreekt in Laravel** |
| Ventilatie/ACH | **ontbreekt** | voelbaar 2,67·ACH·V en latent 1,3·ACH·V | **Ontbreekt in Laravel** |
| Latente warmte | **ontbreekt** (alles voelbaar) | personen latent 55 W/p + ventilatie latent | **Ontbreekt in Laravel** |
| Personen | 120 W per persoon **boven** 1 inbegrepen persoon | 75 W voelbaar + 55 W latent = 130 W per persoon, allen tellen | **Conflict** (verschillende telling én verdeling) |
| Apparatuur | per toesteltype (tv 100, pc 150, open keuken 300 W) | één getal in W | **Gedetailleerder in Laravel** (types); Excel vraagt het totaal |
| Veiligheidsfactor | **ontbreekt** | ×1,1 op het totaal | **Ontbreekt in Laravel** |
| Daktype | neutrale factor 1,00 + waarschuwing (bewust niet gevalideerd) | **ontbreekt** (plafond zit impliciet in schil) | **Ontbreekt in Excel** |
| Meerdere kamers / multi-split | per kamer + gelijktijdigheidsfactoren | één ruimte | **Gedetailleerder in Laravel** |
| Klassekeuze | `CapacityClassSelector` met banden (≤2,2→2,5 … >7,1→handmatig) | IFS-banden (≤2,5→2,5 … anders 10 kW), **defect** (#NAME?) | **Gedetailleerder in Laravel** (werkend, handmatige-review-pad) |
| Leidingen/elektrisch/arbeid/prijzen | volledig aanwezig | **ontbreekt** | **Gedetailleerder in Laravel** |
| Waarschuwingen/aannames-flags | uitgebreid systeem | geen | **Gedetailleerder in Laravel** |

**Conclusie:** het werkboek bevestigt de vraag uit de taak: het gebruikt een
engineering-achtige lastberekening (schiltransmissie, U-waarde, zonnewinst per
raamoppervlak met oriëntatie en zonwering, ventilatie via ACH, voelbaar/latent
gescheiden, veiligheidsfactor) waar v1 een vereenvoudigde W/m²-methode met
multiplicatieve correctiefactoren is. De twee methodes zijn **niet mengbaar**:
v2 wordt een volledig eigen `load_method` binnen een nieuwe regelset­versie;
per berekening geldt exact één methode.

---

## 4. Aanbevolen v2-architectuur

- **Nieuwe regelset** "Belgische residentiële koellast (v2)" (`load_method: engineering_v2`),
  aangemaakt als **concept (draft)** via `php artisan hvac:seed-v2-rule-set`.
  v1 blijft actief en onaangeroerd; historische snapshots wijzigen nooit.
  Activering kan uitsluitend via het bestaande goedkeuringsscherm (expliciete
  bevestiging), nadat Martin de v2-waarden gevalideerd heeft.
- `CoolingLoadCalculator` kiest de methode op basis van `configuration['load_method']`
  (afwezig ⇒ `simple_v1`, dus oude snapshots en v1 blijven exact gelijk werken).
- Alle tussenresultaten (geometrie, transmissie, zonnewinst, interne winsten,
  ventilatie, totalen) worden in het onveranderlijke resultaat-snapshot bewaard en in
  het adminpaneel als leesbare groepen getoond — nooit als ruwe JSON.
- `final_watts`/`final_kw` blijven de sleutel voor klassekeuze, productselectie,
  overrides en offerteconversie; v2 verandert alleen hoe die last berekend wordt.
- Aanbevolen klasse komt uit `CapacityClassSelector` (de defecte IFS-formule wordt
  bewust NIET nagebouwd).

### Parametermapping formulier → v2

| v2-parameter | Bron | Waarde |
|---|---|---|
| Ueq | `insulation_level` (formulier) | excellent→0,35 · good→0,60 · average→0,90 · poor→1,40 · other/unknown→0,90 + waarschuwing |
| ΔT | regelset | 8 K |
| qsol | `orientation` per kamer | north 120 · east 230 · south 280 · west 300 · other/unknown 300 + waarschuwing (werkboekgedrag: onbekend valt op 300 terug) |
| Fs | regelset-aanname (formulier vraagt geen zonwering) | standaard `none` (1,00 — conservatief) + waarschuwing; instelbaar |
| Raamoppervlakte | afgeleid uit `windows`-type × vloeroppervlak | large 25 % · mixed 15 % · small 10 % · few_none 3 % · other/unknown 10 % + waarschuwing; echte m² krijgt voorrang zodra bekend |
| Personen | aanname per kamertype (zoals v1) | 75 W voelbaar + 55 W latent p.p. |
| Apparatuur | aanname per kamertype (zoals v1, W-waarden per toestel) | volledig voelbaar |
| ACH | regelset | 0,5 |
| Ventilatiecoëfficiënten | regelset | 2,67 en 1,3 W per (m³·ACH) — letterlijke werkboekconstanten |
| Veiligheidsfactor | regelset | 1,1 |

---

## 5. Waarom "Recommended size" `#NAME?` toont

De formule in B30 gebruikt **`IFS(...)`**. Die functie bestaat pas sinds
Excel 2019 / Microsoft 365. Het werkboek zelf is aangemaakt met een generator
(calcId 124519, geen cachewaarden) en wordt kennelijk geopend in een programma dat
`IFS` niet kent (ouder Excel, of een viewer/LibreOffice-modus zonder die functie).
Een onbekende functienaam geeft in Excel exact `#NAME?`.

- De formule is **syntactisch correct** en de bandgrenzen zijn goed leesbaar:
  ≤2,5 → "2.5 kW / 9000 BTU", ≤3,5 → "3.5 kW / 12000 BTU", ≤5 → "5.0 kW / 18000 BTU",
  ≤6,8 → "6.8 kW / 24000 BTU", ≤8 → "8.0 kW", anders "10.0 kW".
- Herstel in Excel zou kunnen met geneste `IF`'s, maar voor Laravel is dat irrelevant:
  **wij gebruiken `CapacityClassSelector`** op de berekende ontwerpbelasting.
  De Excel-banden (2,5/3,5/5/6,8/8/10) verschillen bovendien van onze
  cataloggedreven klassebanden (2,5/3,5/5,0/6,0/7,1 + handmatige review) — de
  catalogbanden blijven leidend omdat ze aan echte producten en het
  handmatige-review-pad gekoppeld zijn. Dit verschil is aan Martin voorgelegd via
  `docs/hvac/rules-to-validate.md`.

---

## 6. Gap-analyse formulierinvoer

Huidig aircoformulier per kamer: breedte, lengte, hoogte, daktype, raam*type*,
oriëntatie; per woning: isolatiegraad. Het werkboek heeft daarnaast nodig:

| Werkboekinvoer | Status formulier | Classificatie | Voorstel |
|---|---|---|---|
| Lengte / breedte / hoogte | aanwezig | vereist van klant | behouden |
| Oriëntatie | aanwezig | vereist van klant | behouden |
| Bouwkwaliteit | aanwezig als `insulation_level` | vereist van klant | mapping naar Ueq |
| **Raamoppervlakte (m²)** | ontbreekt (wel raamtype) | **veilig afleidbaar** uit raamtype × vloeroppervlak (regelset-ratio's, met waarschuwing) | NIET aan klant vragen; optioneel later een adminveld voor echte m² |
| **Zonwering** | ontbreekt | **regelset-standaard** (klant kán dit weten — optionele formulierswitch is denkbaar, maar niet nodig voor v2) | standaard `none` (conservatief), admin-aanpasbaar per regelset |
| **Personen** | ontbreekt | **admin-aanname** per kamertype (bestaand v1-mechanisme) | behouden; niet aan klant vragen |
| **Apparatuurlast (W)** | ontbreekt | **admin-aanname** per kamertype (bestaand v1-mechanisme) | behouden |
| **ACH** | ontbreekt | **regelset-standaard** (technisch begrip, klant kan dit niet weten) | 0,5 |
| **Veiligheidsfactor** | ontbreekt | **regelset-standaard** | 1,1 |

Het formulier hoeft dus **niet uitgebreid** te worden voor v2; alle ontbrekende
grootheden zijn regelset-standaarden of bestaande aannames, telkens met
waarschuwing + "aanname"-vlag in het adminpaneel.

---

## 7. Validatievoorbeeld (regressietest)

`tests/Feature/Hvac/HvacCoolingLoadV2Test.php` voert het werkboekvoorbeeld exact uit
(raamoppervlakte 5 m² expliciet, zonwering binnenzonwering, Ueq-klasse "good" = 0,60,
3 personen, 250 W apparatuur, ACH 0,5, SF 1,1) en verifieert **alle** tussenwaarden
uit §2.4 binnen ±0,01 W, plus de ontwerpbelasting 2 572,229 W / 2,572229 kW en de
klassekeuze (3,5 kW) via `CapacityClassSelector`.

---

## 8. Migratieplan

1. ✅ Audit (dit document) — formules 100 % zeker gereconstrueerd.
2. v2-regelsetdefinitie in `config/hvac.php` (naast v1, nooit erover heen);
   seed als **draft** via `php artisan hvac:seed-v2-rule-set`.
3. `engineering_v2`-tak in `CoolingLoadCalculator` met volledige
   tussenresultaat-snapshot; regressietest op het werkboekvoorbeeld.
4. Adminpaneel: leesbare v2-uitsplitsing (geometrie / transmissie / zonnewinst /
   interne winsten / ventilatie / totalen) met aanname-badges; v1-weergave blijft
   voor oude snapshots.
5. Martin valideert de v2-waarden (zie aanvullingen in `rules-to-validate.md`:
   raamratio's, zonweringsstandaard, ACH, ΔT, coëfficiënten) en activeert de
   regelset daarna zelf via het bestaande activeringsscherm.
6. Pas daarna rekenen nieuwe berekeningen met v2; alles ervóór blijft v1.

### Onzekerheden

Er zijn **geen onbepaalde formules**: alle cellen zijn met zekerheid gereconstrueerd.
De enige beperking is inhoudelijk, geen technische: de werkboekconstanten
(qsol-waarden, Fs, Ueq, 75/55 W p.p., 2,67/1,3, ΔT 8 K, SF 1,1) zijn
ingenieursvuistregels zonder bronvermelding in het bestand. Ze zijn overgenomen als
te valideren regelwaarden — niet als gecertificeerde engineering.
