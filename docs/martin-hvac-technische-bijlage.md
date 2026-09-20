# Technische bijlage — Hoe de berekeningen werken

**Hoort bij: Mastechnics — Van aanvraag tot offerte**

Versie september 2026 · Beschrijft uitsluitend wat werkelijk in de software zit. Waar iets niet bestaat, staat dat er uitdrukkelijk bij.

---

## Hoofdstuk 1 — Hoe leest u deze bijlage?

Elke regel of formule krijgt één van deze vijf labels:

| Label | Betekenis |
|-------|-----------|
| **Geïmplementeerd** | De logica zit in de software en wordt gebruikt. Zegt niets over de juistheid van de waarde. |
| **Door Martin bevestigd** | Martin heeft de waarde uitdrukkelijk bevestigd in **Offerte-instellingen**. De actuele stand staat altijd op dat scherm, niet in dit document. |
| **Nog te valideren** | Bedrijfs- of vuistregel die werkt, maar nog door Martin bevestigd moet worden. |
| **Startwaarde** | Waarde die de ontwikkelaar heeft ingevuld om te kunnen rekenen. Geen waarde van Mastechnics. In het scherm: *Nog instellen*. |
| **Niet geïmplementeerd** | Bestaat niet in de software. |

> **Let op** — Op het moment van schrijven is in de software nog **geen enkele** instelling door Martin bevestigd (0 van 12 belangrijke instellingen). Alle getallen in deze bijlage zijn dus startwaarden of nog te valideren, tenzij het scherm **Offerte-instellingen** intussen *Goedgekeurd* toont.

De gegevensstroom in één regel:

Klantaanvraag → gedetailleerde koellast per kamer → capaciteitsklasse → toestelselectie uit de catalogus → materialen → werkuren → prijs, btw en marge → optie(s) → goedkeuring door Martin → conceptofferte → PDF → handmatig versturen.

---

## Hoofdstuk 2 — Snelle inschatting (W/m³)

**Status: geïmplementeerd · waarden aangeleverd door Martin, in de software nog te valideren · niet "belangrijk" (blokkeert geen goedkeuring).**

```
volume (m³)            = lengte × breedte × hoogte
indicatief vermogen (W) = volume × W/m³ van de gekozen situatie
indicatief vermogen (kW) = W ÷ 1.000
```

| Situatie | Waarde |
|----------|-------:|
| Goed geïsoleerd | 30 W/m³ |
| Zuidgericht | 35 W/m³ |
| Veel zon en slecht geïsoleerd | 40 W/m³ |
| Veel zon, slechte isolatie en onder dak | 45 W/m³ |

De situaties zijn afzonderlijke keuzes; er wordt niets opgeteld of vermenigvuldigd. Voorbeeld: 5 × 4 × 2,5 m = 50 m³ × 30 = 1.500 W = 1,5 kW.

Afronding: volume op 2 decimalen, watt op een geheel getal, kW op 2 decimalen. Invoer buiten 0,5–50 m (lengte, breedte) of 0,5–10 m (hoogte) wordt geweigerd als typefout; dat zijn technische grenzen, geen bedrijfsregels.

**Veiligheidsgrenzen (geïmplementeerd en getest):** de pagina is alleen-lezen. Ze bewaart niets, kiest geen product, heeft geen verbinding met goedkeuren, omzetten of e-mail, en raakt de gedetailleerde berekening niet. De getoonde capaciteitsklasse komt uit dezelfde klassentabel als hoofdstuk 5, maar is uitdrukkelijk niet-bindend.

**De vier waarden zijn versieerbaar:** ze zitten in de instellingen (`quick_estimate`) en wijzigen dus via een concept, net als elke andere waarde.

### W/m² tegenover W/m³

De snelle inschatting rekent per **m³** (volume, de hoogte telt dus rechtstreeks mee). De gedetailleerde berekening in hoofdstuk 3 rekent per **m²** vloeroppervlak en corrigeert daarna voor de hoogte. De twee getallen zijn niet in elkaar om te rekenen zonder de hoogte: 30 W/m³ bij 2,5 m komt overeen met 75 W/m²; bij 3 m met 90 W/m². De twee methodes zullen daarom zelden exact hetzelfde resultaat geven. Dat is geen fout.

---

## Hoofdstuk 3 — Gedetailleerde koelbelasting: berekening per m² (in gebruik)

**Status: geïmplementeerd en in gebruik (instellingen versie 1).**

```
basisvermogen (W) = oppervlakte × W/m²(isolatie) × hoogtefactor × liggingsfactor × raamfactor × dakfactor
eindvermogen (W)  = basisvermogen + warmte personen + warmte toestellen
hoogtefactor      = kamerhoogte ÷ referentiehoogte
warmte personen   = (aantal personen − 1) × W per extra persoon      (nooit negatief)
```

| Regel | Waarde | Status |
|-------|--------|--------|
| W/m² uitstekende / goede / gemiddelde / beperkte isolatie | 70 / 90 / 110 / 140 | Startwaarde · **belangrijk** |
| Isolatie "andere" of "onbekend" | 110 + waarschuwing | Startwaarde |
| Referentiehoogte | 2,5 m | Nog te valideren |
| Ligging noord / oost / west / zuid | 0,95 / 1,00 / 1,08 / 1,12 | Startwaarde |
| Ramen groot / gemengd / klein / weinig-geen | 1,18 / 1,10 / 1,05 / 1,00 | Startwaarde |
| Raamfactor bij bekende glasoppervlakte (≤10% / ≤20% / ≤30% / >30% van de vloer) | 1,00 / 1,05 / 1,10 / 1,18 | Geïmplementeerd, maar het formulier vraagt geen m² glas — wordt in de praktijk niet gebruikt |
| Dak- en zoldercorrectie | 1,00 voor elk daktype | **Bewust neutraal.** Er is geen correctie bepaald; zolder- en platdakkamers krijgen wel een waarschuwing |
| Warmte per extra persoon | 120 W (eerste persoon inbegrepen) | Startwaarde |
| Aangenomen personen per kamertype | slaapkamer 2 · woonkamer 3 · bureau 1 · keuken 2 · zolderkamer 2 · andere 2 | Startwaarde — het formulier vraagt dit niet, altijd een aanname |
| Warmte toestellen tv / pc / open keuken | 100 / 150 / 300 W | Startwaarde |
| Aangenomen toestellen per kamertype | woonkamer tv · bureau pc · keuken open keuken · slaapkamer tv | Startwaarde — altijd een aanname |

**Uitgewerkt voorbeeld** — slaapkamer 4 × 5 m (20 m²), 2,5 m hoog, goede isolatie, zuid, grote ramen, geen dak:

```
20 × 90 × (2,5 ÷ 2,5) × 1,12 × 1,18 × 1,00 = 2.378,9 W
+ personen (2 − 1) × 120                    =   120 W
+ tv                                        =   100 W
eindvermogen                                = 2.599 W = 2,60 kW  → klasse 3,5 kW
```

---

## Hoofdstuk 4 — Gedetailleerde koelbelasting: uitgebreid model (concept)

**Status: geïmplementeerd, maar NIET in gebruik.** Het model bestaat als aparte set instellingen ("Belgische residentiële koellast", versie 2) die de ontwikkelaar als concept kan klaarzetten. Het wordt nooit automatisch geactiveerd. De twee modellen worden nooit gemengd binnen één berekening. Het model is nagebouwd uit een referentiewerkboek en wordt bewaakt door een regressietest (ontwerplast 2.572,229 W voor de referentiekamer van het werkboek).

```
schil (m²)      = 2 × hoogte × (lengte + breedte) + vloeroppervlakte     (4 muren + plafond)
Q transmissie   = Ueq × schil × ΔT
glas (m²)       = vloeroppervlakte × raamaandeel                         (als m² glas onbekend is)
Q zon           = glas × zoninstraling(ligging) × zonweringsfactor
Q voelbaar      = Q transmissie + Q zon + personen × 75 + toestellen + 2,67 × ACH × volume
Q latent        = personen × 55 + 1,3 × ACH × volume
ontwerplast     = (Q voelbaar + Q latent) × veiligheidsfactor
```

| Regel (correctiefactoren) | Waarde | Status |
|-------|--------|--------|
| Ueq uitstekend / goed / gemiddeld / beperkt | 0,35 / 0,60 / 0,90 / 1,40 W/m²K | Nog te valideren · **belangrijk** |
| Ontwerp-ΔT | 8 K | Nog te valideren · **belangrijk** |
| Zoninstraling noord / oost / zuid / west | 120 / 230 / 280 / 300 W per m² glas | Nog te valideren |
| Ligging "andere" of "onbekend" | 300 (gedrag van het werkboek) + waarschuwing | Nog te valideren |
| Zonwering geen / binnen / buiten | 1,00 / 0,75 / 0,35 | Nog te valideren |
| Aangenomen zonwering | "geen" — het formulier vraagt dit niet | Startwaarde · **belangrijk** |
| Raamaandeel groot / gemengd / klein / weinig | 25% / 15% / 10% / 3% van de vloer | **Startwaarde — eigen aanname, niet uit het werkboek** · **belangrijk** |
| Personen voelbaar / latent | 75 / 55 W per persoon (iedereen telt) | Nog te valideren |
| Luchtverversing (ACH) | 0,5 per uur | Nog te valideren · **belangrijk** |
| Ventilatiecoëfficiënt voelbaar / latent | 2,67 / 1,3 W per m³·ACH | Nog te valideren |
| Veiligheidsfactor | 1,1 | Nog te valideren · **belangrijk** |
| Dak- of zoldercorrectie | Bestaat niet in dit model; het plafond zit in de schil. De waarschuwing blijft | Bewuste keuze |

**Uitgewerkt voorbeeld** — dezelfde slaapkamer (5 × 4 × 2,5 m, goed, zuid, grote ramen, 2 personen, tv):

```
schil            = 2 × 2,5 × (5 + 4) + 20      = 65 m²
Q transmissie    = 0,60 × 65 × 8               = 312 W
glas             = 20 × 0,25                   = 5 m²
Q zon            = 5 × 280 × 1,00              = 1.400 W
Q voelbaar       = 312 + 1.400 + 150 + 100 + 2,67 × 0,5 × 50 = 2.028,75 W
Q latent         = 110 + 1,3 × 0,5 × 50        = 142,5 W
ontwerplast      = (2.028,75 + 142,5) × 1,1    = 2.388 W = 2,39 kW  → klasse 3,5 kW
```

De capaciteitsbanden uit het werkboek zelf (2,5 / 3,5 / 5 / 6,8 / 8 / 10) zijn **niet** overgenomen: de betreffende cel in het werkboek is defect. Beide modellen gebruiken de klassentabel van hoofdstuk 5.

---

## Hoofdstuk 5 — Capaciteitsklassen, toestelselectie en multi-split

**Status: geïmplementeerd. Klassentabel en gelijktijdigheid: nog te valideren · belangrijk.**

| Berekende koellast | Klasse |
|--------------------|-------:|
| tot 2,2 kW | 2,5 kW |
| tot 3,2 kW | 3,5 kW |
| tot 4,6 kW | 5,0 kW |
| tot 6,3 kW | 6,0 kW |
| tot 7,1 kW | 7,1 kW |
| boven 7,1 kW | geen klasse — handmatige beoordeling |

De klasse is een zoekdoel, nooit een productkeuze.

**Selectie van toestellen** (geïmplementeerd):

```
koellast ≤ koelvermogen toestel ≤ klasse × 1,30        (1,30 = maximale overdimensionering, startwaarde)
```

- Alleen **actieve** producten uit **niet-gearchiveerde** productlijsten, met ingevuld koelvermogen.
- Een single-split set is per definitie compatibel met zichzelf.
- Binnen- en buitenunit worden alleen gecombineerd via een door u ingevoerde compatibiliteitsregel. Er wordt nooit geraden. Geen regel → kandidaat ongeldig, melding "handmatige controle vereist".
- Leidinglengte en hoogteverschil worden getoetst aan de limieten op het product: *in orde*, *overschreden* of *onbekend*. Onbekend = handmatige controle.
- Producten die na import op **controleren** staan, krijgen een waarschuwing en worden niet gebruikt als goedkoopste materiaal.
- Rangschikking: voorraad gaat voor op prijs. **Aanbevolen** = hoogst gerangschikt, **Budget** = goedkoopste, **Premium** = duurste (als ze verschillen).

**Multi-split** (geïmplementeerd; factoren nog te valideren · belangrijk):

```
geschat vermogen buitenunit = som van de klassen van de binnenunits × gelijktijdigheidsfactor
```

| Aantal binnenunits | Factor |
|--------------------|-------:|
| 2 | 0,90 |
| 3 | 0,88 |
| 4 | 0,85 |
| 5 of meer | 0,82 |

Voorbeeld: 2,5 + 2,5 + 3,5 = 8,5 kW × 0,88 = 7,48 kW. De compatibiliteitstabel van de fabrikant (toegelaten binnenunits, aangesloten-vermogenvenster, maximum aantal units) gaat altijd voor op deze schatting.

---

## Hoofdstuk 6 — Leidingberekening

**Status: geïmplementeerd; alle waarden zijn startwaarden.** Het formulier vraagt geen leidingtraject, dus elke kamer krijgt hetzelfde aangenomen profiel, telkens met de melding "(aanname)".

```
equivalente lengte = werkelijke lengte + aantal bochten × 1,0 m + hoogteverschil × 0,5
```

| Regel | Waarde |
|-------|--------|
| Aangenomen lengte / bochten / hoogteverschil per kamer | 5 m / 4 / 2,5 m |
| Waarschuwingsdrempel | 15 m equivalente lengte |

Met de standaardaannames: 5 + 4 × 1,0 + 2,5 × 0,5 = **10,25 m** equivalente lengte per kamer. De limieten van de fabrikant op het product gaan altijd voor.

---

## Hoofdstuk 7 — Elektrische aannames

**Status: geïmplementeerd; tabel is een algemene schatting ("fabrikantgegevens gaan voor") · belangrijk.**

| Klasse | Zekering | Kabel |
|--------|---------:|-------|
| 2,5 en 3,5 kW | 16 A | 3G2.5 |
| 5,0 en 6,0 kW | 20 A | 3G2.5 of 3G4 |
| 7,1 kW | 25 A | 3G4 |

Standaardspanning: 230 V mono. Heeft het gekozen product eigen elektrische gegevens, dan worden die getoond in plaats van de tabel. In beide gevallen staat erbij dat elektrische controle door de installateur verplicht blijft. Er is **geen** berekening van spanningsval, kabellengte of selectiviteit (niet geïmplementeerd).

---

## Hoofdstuk 8 — Materiaal- en arbeidsberekening

### Materialen

**Status: geïmplementeerd. Hoeveelheden volgen uit regels; prijzen komen uitsluitend uit de catalogus.** Per materiaalsoort wordt het goedkoopste actieve catalogusproduct met een prijs genomen. Bestaat het niet, dan blijft de regel zonder prijs en krijgt de optie de status *Handmatige controle vereist*.

| Regel op de voorcalculatie | Hoeveelheid | Verplicht? |
|----------------------------|-------------|-----------|
| Muurbeugel buitenunit | 1 per buitenunit (muurmontage aangenomen) | ja |
| Trillingsdempers | 1 set per buitenunit | ja |
| Koelleiding | som van de leidinglengtes | ja |
| Leidinggoot | leidinglengte × 1,0 | ja |
| Kabel | leidinglengte × 1,2 | ja |
| Condensafvoerslang | 5 m per binnenunit | ja |
| Condensaatpomp | 1 per binnenunit | **optioneel** — afvoer is onbekend |
| Extra koelmiddel | 1 regel zonder hoeveelheid, als equivalente lengte > 10 m | **optioneel** — hoeveelheid bepaalt de installateur |
| Wi-Fi-module | 1 per binnenunit, alleen als het product uitdrukkelijk géén wifi heeft | optioneel |

Alle hoeveelheidsregels zijn startwaarden of nog te valideren.

### Arbeid

**Status: geïmplementeerd; alle uren zijn startwaarden.**

```
totaal uren = basis + extra binnenunits + extra leiding + boringen + elektriciteit
              (+ tweede technieker) (+ toeslag dak/zolder) (+ condensaatpomp)
arbeidskost = totaal uren × uurtarief
```

| Onderdeel | Waarde |
|-----------|--------|
| Basisinstallatie (1 binnenunit + buitenunit) | 6,0 u |
| Per extra binnenunit | 3,0 u |
| Leidingwerk boven 5 m totaal | 0,25 u per meter |
| Boring per kamer | 0,5 u |
| Elektrische aansluiting | 1,5 u (altijd, met melding "te controleren") |
| Tweede technieker | vanaf 3 binnenunits: + 4,0 u (met waarschuwing) |
| Toeslag dak of zolder | + 2,0 u (met waarschuwing) |
| Condensaatpomp | + 1,0 u, alleen als een pomp wordt meegerekend |
| **Uurtarief** | **€ 65,00 excl. btw · belangrijk** |
| **Verplaatsing** | **€ 35,00 vast bedrag excl. btw · belangrijk** |

Voorbeeld, één kamer: 6,0 + 0,5 + 1,5 = 8,0 u × € 65,00 = € 520,00, plus € 35,00 verplaatsing. Negatieve uren of tarieven worden op nul begrensd. Verplaatsing **per kilometer** of per zone is niet geïmplementeerd.

---

## Hoofdstuk 9 — Prijsformules; opslag versus marge

**Status: geïmplementeerd; percentages zijn startwaarden · belangrijk.**

Volgorde per toestel:

1. Staat er een **verkoopprijs** in de catalogus → die wordt gebruikt.
2. Anders, staat er een **aankoopprijs** → `verkoopprijs = aankoopprijs × (1 + opslag ÷ 100)`, met waarschuwing.
3. Anders → geen prijs; de optie kan niet goedgekeurd worden.

| Regel | Waarde |
|-------|--------|
| Opslag op toestellen zonder verkoopprijs | 35% |
| Opslag op materialen zonder verkoopprijs | 25% |

> **Let op — naamgeving.** In de code heet de eerste regel nog "terugvalmarge". De berekening is en blijft een **opslag op de aankoopprijs**. De schermen benoemen ze daarom als opslag. De rekenwijze is in Sprint 23 niet gewijzigd.

**Opslag is geen marge:**

```
opslag 35% :  € 1.000,00 × 1,35 = € 1.350,00
winst       = € 350,00
marge op verkoop = 350 ÷ 1.350 = 25,9%         algemeen: marge% = opslag ÷ (100 + opslag) × 100
```

**De marge op het scherm:**

```
marge (€) = verkooptotaal toestellen + materialen − hun aankooptotaal
marge (%) = marge (€) ÷ subtotaal excl. btw × 100
```

Let op twee dingen. Arbeid en verplaatsing tellen **niet** mee in de marge in €, maar het subtotaal waardoor gedeeld wordt bevat ze **wel**. Het percentage is dus lager dan de zuivere productmarge. Ontbreekt één aankoopprijs, dan toont de software geen marge ("onvolledig") in plaats van te gokken. Een negatieve marge geeft een rode waarschuwing. Aankoopprijzen en marges worden nooit naar een offerte of PDF gekopieerd.

Kortingen: handmatig per optie, met verplichte reden, nooit groter dan het totaal van de andere regels.

---

## Hoofdstuk 10 — Btw-berekening

**Status: geïmplementeerd; standaardtarief nog te valideren · belangrijk.**

```
btw per regel = regeltotaal × tarief ÷ 100, afgerond op de cent
btw totaal    = som van de btw per regel
totaal incl.  = subtotaal excl. + btw totaal
```

Afronden per regel is bewust gelijk aan de offertemodule, zodat het goedgekeurde totaal en de PDF nooit een cent verschillen.

- Standaardtarief: 21%.
- 6% wordt alleen **gemeld** wanneer de klant particulier is én aangeeft dat de woning ouder is dan 10 jaar. Het wordt nooit automatisch toegepast.
- In de voorcalculatie kiest u 21% of 6% via **Btw aanpassen** (met reden). In de offerte-editor zijn per regel 0, 6, 12 en 21% toegelaten.
- De software controleert **niet** of de wettelijke voorwaarden voor 6% vervuld zijn (niet geïmplementeerd; verantwoordelijkheid van Mastechnics).

---

## Hoofdstuk 11 — Versiebeheer, momentopnames en bevestiging

**Status: geïmplementeerd.**

- **Versies.** Alle instellingen samen vormen één genummerde versie met status *concept*, *in gebruik* of *gearchiveerd*. Er is altijd precies één versie in gebruik.
- **Waarden wijzigen** kan alleen in een concept. Elke wijziging wordt gelogd: wie, wanneer, welke instelling, oude en nieuwe waarde. Enkel gewone getallen zijn op het scherm te wijzigen, binnen vaste grenzen (bv. uurtarief € 10–500; btw alleen 0/6/12/21). Tabellen (klassen, gelijktijdigheid, zekering/kabel) en tekstkeuzes (aangenomen zonwering) wijzigt de ontwikkelaar.
- **Activeren** vraagt een uitdrukkelijke bevestiging. De vorige versie wordt gearchiveerd, nooit verwijderd. Er is geen automatische activering.
- **Momentopname.** Elke voorcalculatie bewaart de volledige instellingen waarmee ze gemaakt is, plus het versienummer. Oude berekeningen en offertes veranderen dus nooit wanneer u later instellingen wijzigt. Opnieuw berekenen maakt een nieuwe berekening; de oude krijgt de status "vervangen".
- **Bevestiging** van een instelling bewaart: wie, wanneer, welke waarde, in welke versie. Een bevestiging telt alleen zolang de instelling nog exact die waarde heeft. Bij een nieuw concept gaan bevestigingen mee; een gewijzigde waarde moet opnieuw bevestigd worden. Niets wordt bevestigd omdat het "een geldig getal" is.
- **Blokkade.** Een optie met echte producten kan pas goedgekeurd — en pas omgezet naar een offerte — wanneer álle belangrijke instellingen van de gebruikte versie bevestigd zijn. Alleen opties die volledig uit TEST-producten bestaan, mogen zonder die bevestiging doorlopen om te oefenen; zo'n offerte krijgt `[TESTCATALOGUS]` en kan niet verstuurd worden.
- **Na goedkeuring** zijn prijzen, btw en producten van de optie vergrendeld. Een goedkeuring op een verouderde berekening kan niet omgezet worden.

De twaalf belangrijke instellingen van versie 1: vier keer W/m² per isolatieniveau, capaciteitsklassen, gelijktijdigheidsfactoren, zekering/kabel per klasse, uurtarief, verplaatsing, opslag toestellen, opslag materialen, standaard btw-tarief.

---

## Hoofdstuk 12 — Bekende beperkingen

1. **Geen enkele waarde is al bevestigd.** Alle getallen zijn startwaarden of vuistregels.
2. **Geen dak- of zoldercorrectie** in de gedetailleerde berekening (bewust neutraal). Alleen de snelle inschatting kent "onder dak".
3. **Veel invoer is een aanname**: personen, toestellen, leidingtraject, plaats van de buitenunit, condensafvoer; in het uitgebreide model ook zonwering, glasoppervlakte en luchtverversing.
4. **Extra koelmiddel staat bijna altijd gemarkeerd.** Met het standaard leidingprofiel is de equivalente lengte 10,25 m per kamer, net boven de drempel van 10 m. De optionele regel verschijnt dus standaard. Drempel of profiel herbekijken bij de validatie.
5. **Verplaatsing is één vast bedrag**, ongeacht de afstand.
6. **Eén buitenunit per systeem** wordt aangenomen.
7. **Geen verwarmingsberekening**, geen geluids-, condensatie- of luchtstroomberekening.
8. **Geen scherm voor bedrijfsgegevens**, en leveranciers en merken kunnen alleen aangemaakt en (de)geactiveerd worden.
9. **Geen facturatie**, geen voorraadafboeking, geen koppeling met een boekhoudpakket.
10. **Geen AI in de berekening.** Een AI-uitleglaag bestaat als uitgeschakelde optie; ze kan alleen bestaande resultaten verwoorden en wordt gevalideerd.
11. **De productcatalogus bepaalt alles.** Zonder echte producten met prijs, limieten en compatibiliteit is er geen goedkeurbaar voorstel.
12. **Twee btw-keuzes in de voorcalculatie** (6% of 21%); andere tarieven alleen in de offerte-editor.

---

## Hoofdstuk 13 — Voor de ontwikkelaar

Dit hoofdstuk is niet bedoeld voor dagelijks gebruik.

**Services** (`app/Services/Hvac/`): `HvacCalculationService` (orkestratie + momentopname), `HvacInputNormalizer`, `CoolingLoadCalculator` (`simple_v1` / `engineering_v2` via `load_method`), `CapacityClassSelector`, `PipeEstimator`, `ElectricalEstimator`, `ProductSelector`, `AccessorySelector`, `LaborEstimator`, `HvacPricingService`, `MarginCalculator`, `HvacRecommendationBuilder`, `HvacRecommendationReadiness` (goedkeuringsblokkade), `HvacQuoteConversionService`, `HvacManualOverrideService`.

**Sprint 23:** `QuickCoolingEstimator` (pure functie), `HvacSettingsGuide` (uitleg per regel, grenzen, statussen), `HvacSettingsOverview` (voortgang, catalogus per klasse, conceptverschillen), `HvacSettingsExample` (demonstratie in geheugen), `HvacCatalogQuality` (gedeelde kwaliteitsdefinities).

**Regelbron:** `config/hvac.php` vult alleen een lege tabel; services lezen regels uitsluitend via `HvacRuleSetResolver`. Catalogus van regels, eenheden en "belangrijk": `HvacRuleCatalog`.

**Tabellen:** `hvac_rule_sets` (versies), `hvac_rule_validations` (bevestigingen met `validated_value`), `hvac_rule_changes` (wijzigingslog concept, Sprint 23), `hvac_calculations` (`result.rule_set.configuration` = momentopname), `hvac_recommendations`, `hvac_recommendation_items`, `hvac_manual_overrides`.

**Routes** (prefix `admin.hvac.rules.`): `index`, `section`, `advanced`, `example`, `quick-estimate`, `validate`, `unvalidate`, `draft`, `value`, `discard`, `activate`. De URL's blijven `/admin/hvac/rules/...`.

**Migraties Sprint 23:** `2026_09_20_000001` voegt `quick_estimate` toe aan bestaande actieve en conceptversies (additief, geen bestaande sleutel gewijzigd, gearchiveerde versies ongemoeid); `2026_09_20_000002` maakt `hvac_rule_changes`.

**Commando:** `php artisan hvac:seed-v2-rule-set` zet het uitgebreide model klaar als concept.

**Tests:** `tests/Unit/Hvac/QuickCoolingEstimatorTest.php`, `tests/Feature/Hvac/HvacQuickEstimateTest.php`, `tests/Feature/Hvac/HvacQuoteSettingsTest.php`; bestaande motor: `CoolingLoadCalculatorTest`, `HvacCoolingLoadV2Test`, `HvacEngineHardeningTest`, `HvacE2eAcceptanceTest`.

**Tabelwaarden wijzigen** (klassen, gelijktijdigheid, elektrisch, zonwering): maak een concept via het scherm, pas de `configuration` van die conceptversie aan, laat Martin de gewijzigde instellingen bevestigen en het concept activeren. Wijzig nooit de versie die in gebruik is.
