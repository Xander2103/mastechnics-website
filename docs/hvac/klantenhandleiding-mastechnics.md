# Mastechnics — Handleiding automatische airco-voorcalculatie en offertevoorbereiding

**Versie:** 1.0
**Datum:** 6 augustus 2026
**Doelgroep:** Martin (zaakvoerder/installateur Mastechnics) en medewerkers met beheerderstoegang
**Systeem:** Mastechnics-website, beheeromgeving (admin)

> **Belangrijk — lees dit eerst**
>
> Deze module ondersteunt de voorcalculatie en offertevoorbereiding. Ze
> vervangt geen plaatsbezoek, fabrikantcontrole, elektrische controle of
> professionele eindbeoordeling. Elke automatische berekening is een
> **schatting** die u zelf controleert vóór u een offerte verstuurt.

---

## Inhoud

1. [Wat doet het systeem (en wat niet)](#1-wat-doet-het-systeem-en-wat-niet)
2. [De volledige werkwijze in 18 stappen](#2-de-volledige-werkwijze-in-18-stappen)
3. [Het beheermenu](#3-het-beheermenu)
4. [Het aanvraagscherm](#4-het-aanvraagscherm)
5. [De koellastberekening uitgelegd](#5-de-koellastberekening-uitgelegd)
6. [Regelversies](#6-regelversies)
7. [De productcatalogus](#7-de-productcatalogus)
8. [Producten importeren via CSV](#8-producten-importeren-via-csv)
9. [Compatibiliteit](#9-compatibiliteit)
10. [Hoe het systeem producten kiest](#10-hoe-het-systeem-producten-kiest)
11. [Budget / Aanbevolen / Premium](#11-budget--aanbevolen--premium)
12. [Toebehoren en materialen](#12-toebehoren-en-materialen)
13. [Arbeidsuren](#13-arbeidsuren)
14. [Prijs, btw en marge](#14-prijs-btw-en-marge)
15. [Waarschuwingengids](#15-waarschuwingengids)
16. [Waarden handmatig aanpassen](#16-waarden-handmatig-aanpassen)
17. [Statussen van een optie](#17-statussen-van-een-optie)
18. [Van goedgekeurde optie naar offerte](#18-van-goedgekeurde-optie-naar-offerte)
19. [PDF en e-mail](#19-pdf-en-e-mail)
20. [AI-uitleg](#20-ai-uitleg)
21. [Testcatalogus (oefenmodus)](#21-testcatalogus-oefenmodus)
22. [Acht praktijkscenario's](#22-acht-praktijkscenarios)
23. [Probleemoplossing](#23-probleemoplossing)
24. [Regels die u vóór productie moet valideren](#24-regels-die-u-vóór-productie-moet-valideren)
25. [Veiligheid en verantwoordelijkheden](#25-veiligheid-en-verantwoordelijkheden)
26. [Back-up en ondersteuning](#26-back-up-en-ondersteuning)
27. [Checklists](#27-checklists)
28. [Verklarende woordenlijst](#28-verklarende-woordenlijst)
29. [De website: werkgebied en gemeentepagina's](#29-de-website-werkgebied-en-gemeentepaginas)

---

## 1. Wat doet het systeem (en wat niet)

**Wat het systeem doet:**

- Het leest de antwoorden die een klant invult in het airco-aanvraagformulier
  (kamers, afmetingen, isolatie, ligging, ramen, dak).
- Het berekent per kamer een **geschatte koelbehoefte** in watt en kW.
- Het zoekt in **uw eigen productcatalogus** naar toestellen die passen én
  waarvan de compatibiliteit vastligt.
- Het rekent toebehoren, arbeidsuren, verplaatsing, prijs, btw en marge uit.
- Het toont u één tot drie opties (Budget / Aanbevolen / Premium).
- Ná uw goedkeuring zet het een optie om in een **conceptofferte** in het
  bestaande offertesysteem.

**Wat het systeem uitdrukkelijk níét doet:**

- Het verstuurt **nooit** automatisch een offerte of e-mail naar de klant.
  Versturen blijft altijd uw eigen, aparte handeling.
- Het verzint **nooit** producten, prijzen of compatibiliteit. Staat iets
  niet in uw catalogus, dan wordt er niets voorgesteld.
- Het vervangt geen plaatsbezoek: leidinglengtes, elektrische voeding,
  afvoer en plaatsing zijn **aannames** tot u ze ter plaatse controleert.
- Het is geen gecertificeerde warmteverliesberekening.

Waarom kan een "technisch geldige" optie tóch handmatige controle vragen?
Omdat het systeem eerlijk is over wat het niet weet: onbekende
leidinglimieten, ontbrekende prijzen of niet-gevalideerde rekenregels
blokkeren de goedkeuring tot u ze bevestigd hebt.

---

## 2. De volledige werkwijze in 18 stappen

[SCREENSHOT 1 — Adminnavigatie: de bovenbalk met Aanvragen, HVAC-producten, Account]

1. **De klant** vult op de website het aanvraagformulier "airco laten
   plaatsen" in (kamers, afmetingen, isolatie…).
2. De aanvraag verschijnt in het beheer onder **Aanvragen** met status
   "Nieuw".
3. U opent de aanvraag door op **Bekijken** te klikken.
4. U controleert de kamers, afmetingen en eventuele foto's van de klant.
5. In het blok **"Automatische airco-voorcalculatie"** klikt u op
   **Voorcalculatie uitvoeren**.
6. Het systeem berekent per kamer de koellast (u ziet elke factor).
7. Het systeem kiest per kamer een **doelklasse** (bv. 3,5 kW).
8. Het systeem zoekt passende, aantoonbaar compatibele producten in uw
   catalogus.
9. Het voegt toebehoren en materialen toe, elk met reden en hoeveelheid.
10. Het schat de arbeidsuren en verplaatsing.
11. Het berekent verkoopprijs, btw en uw marge (marge ziet alleen u).
12. U ziet één of meerdere **opties** (Budget / Aanbevolen / Premium).
13. U leest **alle waarschuwingen** — die vertellen wat het systeem heeft
    aangenomen of niet weet.
14. Waar nodig past u waarden aan (altijd met een reden, zie hoofdstuk 16).
15. U klikt **Optie goedkeuren** bij de optie die u wil aanbieden.
16. U klikt **Omzetten naar offerte** — er ontstaat een **concept**offerte.
17. U opent de offerte, controleert ze en bekijkt de **PDF**.
18. U verstuurt de offerte zelf via de bestaande knop "Offerte versturen".
    Zonder die klik vertrekt er niets naar de klant.

**Wat kan niet ongedaan gemaakt worden?** Het versturen van een e-mail naar
de klant. Al de rest (berekening, goedkeuring, zelfs de conceptofferte) kan
opnieuw of anders.

---

## 3. Het beheermenu

| Menu | Waarvoor | Let op |
|---|---|---|
| **Aanvragen** | Alle klantaanvragen; hier staat ook het HVAC-paneel per airco-aanvraag. | Verwijderen van een aanvraag kan niet zolang er een offerte aan hangt. |
| **HVAC-producten → Producten** | Uw catalogus: toestellen en toebehoren met prijzen, voorraad en technische limieten. | Producten **deactiveren**, nooit verwijderen (hoofdstuk 7). |
| **HVAC-producten → Merken** | Merkenlijst. | Alleen aanmaken/deactiveren. |
| **HVAC-producten → Leveranciers** | Leveranciersgegevens. | Producten worden herkend op leverancier + SKU. |
| **HVAC-producten → Import** | CSV-import voor producten én compatibiliteit. | Altijd eerst het voorbeeld controleren vóór u bevestigt. |
| **HVAC-producten → Berekeningsregels** | Alle rekenregels met status; hier valideert u regels. | Wijzig geen regel zonder de impact te begrijpen (hoofdstuk 6 en 24). |
| **HVAC-producten → Checklist** | De 18-stappen acceptatiechecklist. | Doorloop ze bij elke nieuwe catalogus. |
| **Account** | Uw e-mailadres en wachtwoord. | Deel uw login nooit. |
| **Geblokkeerde e-mails** | Afzenders blokkeren voor het contactformulier. | Ongewijzigd t.o.v. vroeger. |

"HVAC-berekeningen" als apart menu bestaat niet: berekeningen horen bij de
aanvraag en staan op de aanvraagpagina zelf. "Compatibiliteit" beheert u op
de productpagina van het hoofdtoestel of via de compatibiliteits-CSV.

---

## 4. Het aanvraagscherm

[SCREENSHOT 2 — Aanvraagdetail met het HVAC-paneel opengeklapt]

Boven ziet u de klantgegevens, de taal van de aanvraag (NL/FR/EN — die taal
krijgt ook de offerte), de categorie en de gewenste termijn. Daaronder per
kamer: afmetingen (breedte × lengte × hoogte), berekende oppervlakte,
isolatiegraad van de woning, ligging, daktype, ramen. Foto's van de klant
staan in de kaart **Bijlagen**.

**Ontbrekende gegevens.** Het systeem is streng op wat écht nodig is:

| Ontbreekt | Gevolg |
|---|---|
| Breedte, lengte of **hoogte** van een kamer | Berekening **geblokkeerd** — oudere aanvragen (vóór de formulierverbetering) hebben soms geen hoogte. Vraag ze op of laat de klant opnieuw indienen. |
| Isolatiegraad | Berekening **geblokkeerd**. |
| Onmogelijke waarden (bv. hoogte 12 m) | Berekening **geblokkeerd** — controleer de invoer. |
| Ligging of raamtype "andere/onbekend" | Berekening loopt door met een neutrale factor + **waarschuwing**. |
| Aantal personen, toestellen, leidinglengte, elektrische voeding, afvoer | Niet gevraagd in het formulier: het systeem gebruikt **aannames** en zegt dat er telkens duidelijk bij ("aanname"). Controleer ter plaatse. |

---

## 5. De koellastberekening uitgelegd

Het systeem rekent **deterministisch**: dezelfde invoer met dezelfde
regelversie geeft altijd exact hetzelfde resultaat. U ziet elke tussenstap
in de tabel "Berekening" — nooit alleen een eindgetal.

[SCREENSHOT 3 — Berekeningstabel met alle factoren per kamer]

De formule in woorden:

> oppervlakte × basislast per m² (volgens isolatie) × hoogtefactor ×
> liggingsfactor × raamfactor × dakfactor
> **+** warmte van personen **+** warmte van toestellen
> **=** koellast in watt

**Uitgewerkt voorbeeld (fictieve waarden):**

| Gegeven | Waarde |
|---|---|
| Kamer | Woonkamer, 5 m × 6 m × 2,5 m → 30 m² |
| Isolatie | Goed → 90 W/m² |
| Hoogtefactor | 2,5 / 2,5 = 1,00 |
| Ligging | Zuid → factor 1,12 |
| Ramen | Grote ramen → factor 1,18 |
| Basis | 30 × 90 × 1,00 × 1,12 × 1,18 = **3 572 W** |
| Personen | 3 aangenomen → 2 extra × 120 W = **+240 W** |
| Toestellen | Tv aangenomen → **+100 W** |
| **Totaal** | **3 912 W = 3,91 kW** |
| Doelklasse | 3,91 ≤ 4,6 → **klasse 5,0 kW** |

> **Let op:** de gebruikte getallen (90 W/m², factoren, 120 W per persoon…)
> zijn **startwaarden die u nog moet valideren** — zie hoofdstuk 24. De
> dakfactor staat bewust op 1,00 tot u een gevalideerde correctie aanlevert;
> bij zolder- of platdakkamers verschijnt daarom altijd een waarschuwing.

De **doelklasse** is een zoekdoel voor de catalogus, geen productkeuze: een
toestel wordt alleen voorgesteld als het voldoende vermogen heeft, niet
absurd overgedimensioneerd is én de compatibiliteit vastligt.

---

## 6. Regelversies

Alle rekenwaarden samen heten een **regelset**. Regelsets hebben een
versienummer. Elke berekening slaat een volledige kopie ("momentopname") op
van de regelset waarmee ze gemaakt is. Daardoor geldt:

- Oude berekeningen **veranderen nooit**, ook niet als u later regels
  aanpast.
- Onder elke berekening ziet u welke versie gebruikt werd ("Regelset …
  versie …" in het auditblok).
- Een **conceptversie** is een kopie waarin de ontwikkelaar nieuwe waarden
  kan zetten; ze doet niets zolang u ze niet activeert.
- **Activeren** doet u op de pagina Berekeningsregels, met een expliciet
  bevestigingsvinkje. Alleen **nieuwe** berekeningen gebruiken daarna de
  nieuwe versie.

> **Waarschuwing:** wijzig geen berekeningsregel zonder de technische impact
> te begrijpen. Overleg bij twijfel met de ontwikkelaar.

---

## 7. De productcatalogus

[SCREENSHOT 4 — Productcatalogus met datakwaliteitskaarten bovenaan]

### Productlijsten (standaardweergave)

**HVAC-producten** opent op **Productlijsten**: één kaart per geïmporteerde
leverancierslijst (naam, leverancier, aantal producten, hoeveel actief en
hoeveel "controleren", laatste import). Klik **Openen** om alleen de
producten van die lijst te zien, met filters (type, merk, koelvermogen,
status, zonder prijs, zonder compatibiliteit, te controleren) en de
**importgeschiedenis** van die lijst. Via de tab **Alle producten** blijft
het volledige overzicht beschikbaar; **Merken** en **Leveranciers** hebben
hun eigen tabs. Bij **Leveranciers** ziet u per leverancier hoeveel
productlijsten en actieve producten er zijn, wanneer de laatste import
was, en de lijsten zelf — met één klik opent u zo'n lijst.

- **Hernoemen** geeft een lijst een vriendelijke naam ("Fujitsu 2026").
- **Archiveren** verplaatst een verouderde lijst naar het filtertabblad
  **Gearchiveerd** (boven het overzicht kiest u Actief / Gearchiveerd /
  Alle). Alles blijft leesbaar en historiek blijft intact, maar producten
  die *alleen* nog in gearchiveerde lijsten zitten worden **niet meer
  voorgesteld** in nieuwe aanbevelingen.
- **Bestaande lijst bijwerken**: importeer het nieuwe bestand en kies bij de
  bevestiging "Bestaande productlijst bijwerken". U ziet vooraf hoeveel
  producten nieuw, gewijzigd, ongewijzigd of niet meer in het bestand zijn.
  Er wordt **nooit** iets automatisch verwijderd; ontbrekende producten
  inactief zetten kan alleen via het aparte vinkje.
- **Bron per product**: op de productpagina toont het blok "Bron" uit welke
  lijst, welk bestand, welke rij en met welk importprofiel het product
  geïmporteerd werd, en wat de prijs in het bronbestand betekende.
- **Dubbelklikken bij een import kan geen kwaad**: de knop vergrendelt
  zichzelf en een tweede klik maakt nooit een dubbele lijst of dubbele
  import aan — u komt gewoon op de resultaatpagina terecht.

Elk product heeft: merk, leverancier, SKU (artikelcode), model, type
(binnenunit, buitenunit, single-split-set, multi-split-buitenunit of een
toebehorentype), koelvermogen, prijzen, voorraad, levertijd, geluidsniveau,
SEER/SCOP (rendement), Wi-Fi, elektrische gegevens en leidinglimieten.

**Belangrijke principes:**

- **Deactiveren, niet verwijderen.** Een product dat ooit in een aanbeveling
  of offerte zat, kan niet verwijderd worden — zo blijven oude offertes
  kloppen. Zet het op "Inactief" als u het niet meer verkoopt.
- **Aankoopprijzen ziet alleen u.** Ze komen nooit op offertes of PDF's.
- **Ontbrekende technische data blokkeert aanbevelingen.** Een toestel
  zonder leidinglimieten of zonder prijs wordt nooit stilzwijgend
  goedgekeurd — u krijgt een waarschuwing en de optie blijft "handmatige
  controle".

**Voorbeeld compleet product:** SKU, model, type, koelvermogen 3,5 kW,
aankoop €900, verkoop €1450, voorraad 5, levertijd 3 dagen, max.
leidinglengte 20 m, max. hoogteverschil 12 m, 230V mono, 16 A, 3G2.5.
**Voorbeeld onvolledig product:** zelfde toestel zonder prijzen en zonder
leidinglimieten → verschijnt bij "Zonder prijs" en "Zonder leidinglimieten"
in het kwaliteitsoverzicht en houdt aanbevelingen tegen.

De **datakwaliteitskaarten** bovenaan de catalogus tonen hoeveel producten
klaar zijn en hoeveel er iets missen; klik op een kaart om meteen de
onvolledige producten te zien.

---

## 8. Producten importeren

[SCREENSHOT 5 — Importpagina met voorbeeldweergave]

### 8a. Leveranciersbestand importeren (aanbevolen)

U hoeft het bestand van uw leverancier **niet** aan te passen — upload het
gewoon (CSV of Excel, ook grote prijslijsten met tienduizenden rijen):

1. **Bestand** — kies het bestand, vul eventueel de leverancier in en klik
   **Bestand analyseren**. Het systeem herkent zelf het bestandstype, de
   kolommen en de kopregel; alleen als iets echt onduidelijk is krijgt u een
   korte vraag.
2. **Producten kiezen** — staat er meer in het bestand dan klimatisatie
   (sanitair, verwarming…), dan kiest u welke productgroepen u wilt
   importeren. Groepen die op airco lijken staan al aangevinkt; niets wordt
   stilzwijgend uitgesloten.
3. **Controle** — het systeem toont wat het herkend heeft (✓) en vraagt
   alleen wat het niet zeker weet, zoals **wat de prijs betekent**
   (brutoprijs / netto aankoopprijs / verkoopprijs / weet ik niet). Een
   brutoprijs of onbekende prijs wordt bewaard, maar **nooit** gebruikt voor
   automatische offertes.
4. **Importeren** — geef de productlijst een naam (of werk een bestaande
   lijst bij) en bevestig. Vóór deze klik wordt er niets bewaard.
5. **Resultaat** — u ziet wat toegevoegd, bijgewerkt of overgeslagen is.
   Kies **"Ja, onthouden"** zodat het volgende bestand van deze leverancier
   automatisch herkend wordt en de stappen overslaat.

Producten met de markering **"controleren"** (bv. vermogen afgeleid uit de
productnaam) controleert u vóór u er offertes mee maakt.

### 8b. Importeren met MAS-sjabloon (geavanceerd)

Voor bestanden die exact ons sjabloon volgen (onder **Geavanceerd** op de
importpagina):

1. Download het **CSV-sjabloon**.
2. Open het in Excel of een ander rekenblad.
3. Vul de kolommen in; **verander de kopregel niet**.
4. Verwijder de twee TEST-voorbeeldrijen.
5. Bewaar als **CSV UTF-8** (in Excel: "CSV UTF-8 (door komma's gescheiden)").
6. Upload het bestand, kies het importgedrag (aanmaken / bijwerken / beide).
7. Bekijk het **voorbeeld**: elke rij toont "nieuw", "bijwerken" of "fout".
8. Los rijfouten op in uw bestand en upload opnieuw indien nodig.
9. Klik **Import bevestigen**. Rijen met fouten worden nooit geïmporteerd;
   de rest wordt in één keer veilig weggeschreven.
10. Lees het eindrapport (aangemaakt / bijgewerkt / overgeslagen / fouten)
    en download zo nodig het foutenrapport.

Producten worden herkend op **leverancier + SKU**: dezelfde combinatie
opnieuw importeren = bijwerken, nooit een duplicaat.

**Veelvoorkomende fouten:**

| Fout | Oorzaak / oplossing |
|---|---|
| "Dubbele rij" | Zelfde leverancier + SKU twee keer in één bestand. Verwijder één rij. |
| "geen geldig positief getal" | Tekst of negatieve waarde in een getalkolom. Zowel komma als punt als decimaalteken mag. |
| "Onbekend producttype" | Gebruik exact de types uit het sjabloon (bv. `single_split_set`). |
| "vereist een koelvermogen" | Units en sets moeten `cooling_capacity_kw` hebben. |
| "geen geldige ja/nee-waarde" | Gebruik `ja` of `nee` bij `wifi_included` en `active`. |
| "spanning wordt niet herkend" | Schrijf bv. `230V mono` of `400V tri`. |
| "lijkt op een spreadsheetformule" | Een cel begint met `=`, `@` of `+`. Maak er gewone tekst van. |
| Rare tekens (Ã©, â€¦) | Bestand niet als UTF-8 bewaard. |

**Datawoordenboek (belangrijkste kolommen):**

| Kolom | Betekenis | Verplicht | Voorbeeld |
|---|---|---|---|
| supplier | Leveranciersnaam | ja | Koeltech BV |
| brand | Merknaam | ja | (uw merk) |
| sku | Artikelcode van de leverancier | ja | ABC-25-SET |
| model / name | Model en omschrijving | ja | … |
| product_type | Type (vaste lijst, zie sjabloon) | ja | single_split_set |
| cooling_capacity_kw | Koelvermogen | ja bij units/sets | 2.5 |
| minimum/maximum_capacity_kw | Aansluitvenster (multi-split) | multi | 3.0 / 6.0 |
| purchase/sale_price_excl_vat | Prijzen excl. btw | aanbevolen | 780.00 |
| stock_quantity / lead_time_days | Voorraad en levertijd | aanbevolen | 4 / 3 |
| voltage / phase / breaker_a / cable | Elektrisch | aanbevolen | 230V mono / 1 / 16 / 3G2.5 |
| max_pipe_length_m (+ per unit) / max_height_difference_m | Leidinglimieten | aanbevolen | 20 / 15 |
| max_connected_indoor_units | Max. binnenunits (multi) | multi | 4 |
| sound_level_db / seer / scop | Comfort en rendement | optioneel | 19.5 / 6.5 / 4.6 |
| wifi_included / active | ja/nee | optioneel | ja |
| notes | Vrije notitie | optioneel | … |

---

## 9. Compatibiliteit

Het systeem koppelt binnen- en buitenunits **uitsluitend** via
compatibiliteitsregels die u zelf invoert of importeert — **nooit** omdat
kW-getallen op elkaar lijken. Twee toestellen van 3,5 kW zijn niet
automatisch combineerbaar; alleen de fabrikant weet dat.

[SCREENSHOT 6 — Compatibiliteitsbeheer op de productpagina]

- **Single-split-set** (`single_split_set`): fabriekscombinatie in één
  product — geen aparte regel nodig.
- **indoor_outdoor**: deze buitenunit ondersteunt deze binnenunit (single
  split, aparte producten).
- **multi_split_indoor**: deze multi-split-buitenunit ondersteunt dit
  binnenmodel; vul ook max. units en het aansluitvenster (min/max kW) in.

Toevoegen kan per product (onderaan de productpagina) of in bulk via de
**compatibiliteits-CSV** (Import-pagina, eigen sjabloon met `parent_sku`,
`compatible_sku`, type…). Beide producten moeten al bestaan.

**"Handmatige controle vereist"** bij een optie betekent meestal: het
systeem kán niet bewijzen dat de combinatie mag (regel ontbreekt) of kent
de limieten niet. Vul de catalogusdata aan of beoordeel zelf en bevestig de
waarschuwing met een reden.

---

## 10. Hoe het systeem producten kiest

In gewone taal, in volgorde:

1. **Aantoonbaar compatibel** (set of compatibiliteitsregel) — anders valt
   het af of wordt het "handmatige controle".
2. **Genoeg vermogen** voor de berekende koellast, zonder absurde
   overdimensionering.
3. Bij multi-split: **juist aantal binnenunits** en de som van de
   binnenvermogens binnen het aansluitvenster van de buitenunit.
4. **Leidinglengte en hoogteverschil** binnen de productlimieten (op basis
   van de aangenomen leidingloop — controleer ter plaatse).
5. **Elektrisch**: de voeding van de woning wordt (nog) niet gevraagd in het
   formulier, dus dit blijft altijd "controleren" — u ziet wel de zekering-
   en kabelindicatie van het product of de generieke tabel.
6. **Actieve** producten alleen.
7. Voorkeur voor **voorraad**, daarna kortste **levertijd**, daarna prijs.

Mogelijke uitkomsten: één geldige optie, meerdere opties, geen enkele optie
("vul de catalogus aan of kies handmatig") of opties die op handmatige
controle staan. *Rangschikken op merkvoorkeur, geluidsniveau of rendement:
niet beschikbaar in deze versie* (de gegevens worden wel getoond zodra ze in
de catalogus staan).

---

## 11. Budget / Aanbevolen / Premium

[SCREENSHOT 7 — Drie aanbevelingsopties onder elkaar]

- **Aanbevolen** = de best gerangschikte geldige combinatie (voorraad en
  levertijd wegen mee).
- **Budget** = de goedkoopste geldige combinatie, als die verschilt.
- **Premium** = de duurste/rijkste geldige combinatie, als die verschilt.

Alle opties zijn technisch geldig; het systeem maakt **geen** kunstmatige
verschillen om drie kaartjes te vullen. Is er maar één geldig systeem, dan
ziet u er één. Per optie ziet u toestellen (met voorraad en levertijd),
materialen, arbeid, prijs, marge, waarschuwingen en de technische status.

---

## 12. Toebehoren en materialen

Elke materiaalregel toont **wat, hoeveel, waarom, prijsbron** en of ze
verplicht of optioneel is:

| Toebehoren | Wanneer | Verplicht? |
|---|---|---|
| Muurbeugel + trillingsdempers | Per buitenunit (muurmontage aangenomen zolang de plaatsing onbekend is) | Ja |
| Koelleiding, leidinggoot, kabel | Op basis van de (aangenomen) leidingloop | Ja |
| Condensafvoerslang | Per binnenunit | Ja |
| Condensaatpomp | Afvoer onbekend → voorgesteld tot u het ter plaatse weet | Optioneel |
| Extra koelmiddel | Bij langere leidinglopen — hoeveelheid bepaalt u met fabrikantdata | Optioneel |
| Wi-Fi-module | Alleen als het gekozen toestel aantoonbaar géén Wi-Fi heeft | Optioneel |
| Vloersteun / daksteun, lift/stelling, moeilijke toegang, parkeren | *Niet beschikbaar in deze versie* als automatische regel — voeg ze zo nodig toe als handmatige regel op de offerte | — |

Een toebehoren zonder catalogusprijs blijft **zonder prijs** staan (met
waarschuwing); het systeem verzint nooit een bedrag. Hoeveelheden en
prijzen past u aan via "Waarde aanpassen" (met reden).

---

## 13. Arbeidsuren

De arbeidsschatting is opgebouwd uit regels die u kunt openklappen
("Arbeidsdetail"): basisinstallatie, extra binnenunits, extra leidingwerk,
doorboringen, condensaatpomp, elektrische aansluiting, toeslag dak/zolder en
een tweede technieker vanaf drie binnenunits. Alles maal het uurtarief, plus
het verplaatsingsforfait.

> Het uurtarief (€ 65) en het forfait (€ 35) zijn **startwaarden** die u in
> de Berekeningsregels moet valideren vóór echte offertes.

Uren aanpassen: bij de arbeidsregel → "Waarde aanpassen" → nieuw aantal
uren + **reden** (verplicht). De oorspronkelijke schatting blijft bewaard in
de audittrail.

---

## 14. Prijs, btw en marge

[SCREENSHOT 8 — Prijsoverzicht met interne kost en marge]

Per optie ziet u (alleen in het beheer):

| Regel | Betekenis |
|---|---|
| Aankoopkost toestellen + materiaal | Wat ú betaalt (interne info). |
| Totale interne kost | Aankoop + arbeid + verplaatsing. |
| Toestellen / Materialen / Arbeid / Verplaatsing excl. btw | Verkoopbedragen per blok. |
| Korting | Handmatig toegevoegd, altijd met reden. |
| Subtotaal excl. btw → btw → **Totaal incl. btw** | Wat de klant ziet. |
| **Marge** | Verkoop min aankoop van toestellen en materialen (arbeid rekenen we intern aan kostprijs). |

**Klein voorbeeld:** toestel verkoop €1 450 (aankoop €900), materialen
verkoop €240 (aankoop €120), arbeid €585, verplaatsing €35 → subtotaal
€2 310; btw 21% = €485,10; totaal €2 795,10; **marge** = (1 450 + 240) −
(900 + 120) = **€670** (29 % van het subtotaal).

- **Opslag** ("markup") = percentage bovenop de aankoopprijs; **marge** =
  verschil tussen verkoop en aankoop. Een product zonder verkoopprijs krijgt
  automatisch de ingestelde opslag op de aankoopprijs — duidelijk gelabeld.
- **Btw**: standaard 21 %. Bij een woning ouder dan 10 jaar van een
  particulier **suggereert** het systeem 6 %, maar past dit nooit zelf toe —
  u bevestigt het tarief met de btw-knop (met reden).
- Prijzen worden altijd **op de server herrekend**; AI rekent nooit mee; en
  aankoopprijzen verschijnen nooit op klantendocumenten.

---

## 15. Waarschuwingengids

| Waarschuwing (kern) | Betekenis | Mag u verder? | Wat controleren |
|---|---|---|---|
| Hoogte/afmetingen ontbreken of onmogelijk | Kritieke invoer ontbreekt | **Nee** — berekening geblokkeerd | Vraag gegevens op |
| Isolatiegraad ontbreekt | Kritieke invoer ontbreekt | **Nee** | Idem |
| Isolatie/ligging/ramen "onbekend" | Neutrale terugvalwaarde gebruikt | Ja | Schat zelf realistisch in |
| Zolder/plat dak — geen gevalideerde dakcorrectie | Vermogen kan te laag zijn | Ja, voorzichtig | Overweeg hogere klasse |
| Bezetting / toestellen / leidingloop aangenomen | Formulier vraagt dit niet | Ja | Ter plaatse controleren |
| Elektrische voeding onbekend | Mono/tri en zekeringen ongekend | Ja | Elektrische controle verplicht |
| Afvoer onbekend | Pomp mogelijk nodig | Ja | Ter plaatse |
| Equivalente leidinglengte boven drempel | Lange leidingloop | Ja | Fabrikantlimiet + koelmiddel |
| Compatibiliteitsdata ontbreekt | Combinatie niet bewijsbaar | Goedkeuren geblokkeerd tot bevestiging | Catalogus aanvullen of zelf beoordelen |
| Leiding-/hoogtelimieten onbekend | Productdata onvolledig | Idem | Productfiche aanvullen |
| Geen verkoop- of aankoopprijs | Prijs kan niet bepaald worden | Goedkeuren geblokkeerd | Prijs invullen |
| Kritieke regels niet gevalideerd | Rekenregels nog niet bevestigd | Goedkeuren geblokkeerd (behalve testcatalogus) | Hoofdstuk 24 |
| Geen geschikt product | Catalogus dekt de klasse niet | n.v.t. | Catalogus aanvullen |
| Uurtarief/urenregels niet gevalideerd | Standaardwaarden actief | Ja, met zorg | Valideer de regels |

Waarschuwingen bij een optie "bevestigt" u met **Waarschuwing bevestigen** +
reden; dat wordt geregistreerd in de audittrail.

---

## 16. Waarden handmatig aanpassen

[SCREENSHOT 9 — Formulier "Waarde aanpassen" bij een regel]

U kunt aanpassen: het **berekende vermogen per kamer** (de doelklasse volgt
automatisch en de opties worden herrekend), **hoeveelheid of prijs** van
elke regel, het **product** (alleen uit de actieve catalogus), **arbeid**
(uren/tarief via de arbeidsregel), **korting** en het **btw-tarief**.

Bij élke aanpassing geldt:

- een **reden is verplicht** (minstens 5 tekens);
- de **oorspronkelijke automatische waarde blijft bewaard** en zichtbaar;
- uw e-mailadres en het tijdstip worden geregistreerd;
- alles staat in **"Handmatige aanpassingen"** in het auditblok.

Gepast: na een plaatsbezoek, bij een afgesproken prijs, bij een beter
passend toestel. Niet gepast: een waarschuwing "wegwerken" zonder de
oorzaak te begrijpen, of prijzen verlagen zonder zicht op de marge.

---

## 17. Statussen van een optie

| Status | Betekenis | Wat kunt u doen |
|---|---|---|
| **Concept** | Berekend, nog niet klaar | Controleren, aanpassen |
| **Handmatige controle vereist** | Er is iets onzeker (compatibiliteit, prijs…) | Waarschuwingen bevestigen met reden → wordt Concept |
| **Technisch gevalideerd — prijs onvolledig** | Techniek ok, prijzen niet | Prijzen aanvullen |
| **Prijs gevalideerd — regels niet gevalideerd** | Alles ok behalve regelvalidatie | Regels valideren (hoofdstuk 24) |
| **Klaar voor offerte** | Alle controles groen | **Optie goedkeuren** |
| **Goedgekeurd** | Door u goedgekeurd | **Omzetten naar offerte** of afwijzen |
| **Afgewezen** | Door u afgewezen | Niets meer (herbereken voor nieuwe opties) |
| **Omgezet naar offerte** | Conceptofferte bestaat | Offerte afwerken en zelf versturen |
| *(testcatalogus)* | Achtervoegsel bij TEST-producten | Alleen oefenen — nooit echt versturen |

Goedkeuren is pas mogelijk wanneer de optie "Klaar voor offerte" is; de
blokkerende punten staan er altijd bij.

---

## 18. Van goedgekeurde optie naar offerte

[SCREENSHOT 10 — Knop "Omzetten naar offerte" en de nieuwe conceptofferte]

Klik bij een **goedgekeurde** optie op **Omzetten naar offerte**:

- Alle regels (toestellen mét SKU, materialen, arbeid, verplaatsing,
  eventuele korting) worden offerteregels met dezelfde aantallen, prijzen en
  btw.
- De offerte krijgt een nummer (OFF-jaar-…) en de **taal van de klant**
  (NL/FR/EN) — niet de taal van het beheer.
- De aanbeveling toont voortaan het offertenummer; alles blijft traceerbaar.
- Bestaat er al een offerte voor de aanvraag, dan wordt de omzetting
  **geweigerd** — er wordt nooit iets stilzwijgend overschreven. Verwerk of
  verwijder eerst de bestaande offerte.
- Dubbelklikken kan geen tweede offerte veroorzaken.

De offerte is een **concept**: er vertrekt geen e-mail. U controleert de
offerte, opent de **PDF**, en verstuurt daarna zelf via de bestaande
verzendknop (hoofdstuk 19).

---

## 19. PDF en e-mail

Alles werkt zoals u het al kent van het offertesysteem: PDF met logo en
offertenummer, e-mail in de taal van de aanvraag (NL/FR/EN, terugval NL),
onderwerp en tekst die u vóór verzending kunt aanpassen, en een maillog per
aanvraag. Mislukt een verzending, dan blijft de offerte gewoon in concept
staan en probeert het systeem **nooit** blind opnieuw — u beslist.

**Controleer vóór elke verzending:** ontvanger, taal, totaalbedrag,
btw-tarief, geldigheidsdatum en de PDF zelf (checklist C in hoofdstuk 27).

---

## 20. AI-uitleg

- AI is **optioneel** en staat standaard **uit** (de "nul-aanbieder"). Alle
  berekeningen, prijzen en keuzes zijn puur wiskundig en werken volledig
  zonder AI — u merkt dan simpelweg geen AI-uitlegblok.
- Als een AI-aanbieder actief is, mag die **alleen tekst schrijven** over de
  al gevalideerde resultaten: een klantvriendelijke uitleg, vergelijking of
  vertaling. U ziet die als blauw blok "Gevalideerde AI-uitleg".
- AI kan **nooit** producten of prijzen verzinnen, waarschuwingen weglaten,
  goedkeuren of versturen. Elke AI-tekst wordt eerst automatisch
  gecontroleerd (juiste taal, alleen bestaande producten, geen vreemde
  inhoud) en anders volledig geweigerd.
- Vrije tekst van de klant wordt als **niet-vertrouwde data** behandeld —
  ze kan het systeem geen opdrachten geven.
- Een AI-storing heeft geen enkel effect op de berekening.

---

## 21. Testcatalogus (oefenmodus)

De ontwikkelaar kan een **fictieve testcatalogus** laden (alle namen en
SKU's beginnen met TEST). Daarmee oefent u het hele proces: single split
met drie opties, een multi-split met twee kamers, toebehoren, prijzen en
compatibiliteit.

- Zolang er TEST-producten actief zijn, staat overal de rode banner
  **"Testcatalogus — niet gebruiken voor echte offertes"** en krijgen
  opties het label *(testcatalogus)*.
- TEST-opties mag u goedkeuren zonder regelvalidatie — uitsluitend om te
  oefenen.
- **Verstuur nooit** een offerte op basis van TEST-producten.
- Verwijderen: deactiveer de TEST-producten (of vraag de ontwikkelaar); de
  banner verdwijnt dan vanzelf. In de echte productieomgeving kan de
  testcatalogus überhaupt niet geladen worden.

---

## 22. Acht praktijkscenario's

**Scenario 1 — Eenvoudige woonkamer (single split).**
Aanvraag openen → gegevens kloppen → "Voorcalculatie uitvoeren" →
berekening en 1–3 opties verschijnen → waarschuwingen lezen → optie
"Klaar voor offerte" goedkeuren → omzetten → PDF → zelf versturen.

**Scenario 2 — Twee kamers (multi-split).**
Zelfde werkwijze; het systeem leidt "multi split" af uit het aantal kamers
(met waarschuwing — twee aparte single splits kunnen ook, dat beslist u).
Controleer het aansluitvenster en de gelijktijdigheidsberekening in het
"Systeem"-overzicht.

**Scenario 3 — Isolatie ontbreekt (oude aanvraag).**
De berekening blokkeert met de melding dat isolatie (of kamerhoogte)
ontbreekt. Vraag de klant de gegevens, of noteer ze zelf na contact; het
formulier van nieuwe aanvragen vraagt alles al.

**Scenario 4 — Geen compatibele buitenunit.**
"Geen geldige productcombinaties gevonden" of een optie op handmatige
controle met "compatibiliteitsdata ontbreekt". Oplossing: importeer of
registreer de fabrikantcombinaties (hoofdstuk 9) en klik "Opnieuw
berekenen".

**Scenario 5 — Product zonder aankoopprijs.**
De marge toont "onvolledig" en de kwaliteitskaart "Zonder prijs" telt het
product mee. Vul de prijs in op de productpagina of via een CSV-update en
herbereken.

**Scenario 6 — Aanpassing na plaatsbezoek.**
U zag ter plaatse een langere leidingloop en een derde persoon in het
bureau: pas het kamervermogen aan ("Vermogen aanpassen", reden verplicht) —
de opties worden herrekend met de nieuwe doelklasse; pas daarna eventueel
materiaalhoeveelheden aan.

**Scenario 7 — Goedgekeurde optie omzetten.**
Zie hoofdstuk 18. Onthoud: bestaat er al een offerte, verwerk die eerst;
en versturen blijft altijd uw aparte, laatste stap.

**Scenario 8 — Leverancier stuurt een nieuwe prijslijst.**
Zet de lijst in het CSV-sjabloon (zelfde SKU's!), kies bij de import
"Enkel bestaande producten bijwerken", controleer het voorbeeld en
bevestig. Bestaande offertes veranderen niet; nieuwe berekeningen gebruiken
de nieuwe prijzen.

---

## 23. Probleemoplossing

| Probleem | Waarschijnlijke oorzaak | Wat doen |
|---|---|---|
| Geen HVAC-paneel op de aanvraag | Geen airco-**installatie**aanvraag (bv. onderhoud) | Normaal gedrag |
| Berekening geblokkeerd | Hoogte/afmetingen/isolatie ontbreken of onmogelijk | Gegevens aanvullen; meldingen staan erbij |
| "Er loopt al een berekening" | Dubbelklik | Even wachten, opnieuw proberen |
| Geen aanbevelingen | Catalogus leeg of dekt de klasse niet | Producten/compatibiliteit importeren, herberekenen |
| Optie blijft "Handmatige controle" | Onbewezen compatibiliteit, onbekende limieten of prijs ontbreekt | Blokkerende punten staan bij de optie; data aanvullen of bevestigen met reden |
| Goedkeuren geblokkeerd: regels | Kritieke regels niet gevalideerd | Berekeningsregels → valideren (hoofdstuk 24) |
| Product na import onvindbaar | Rijfout (zie rapport), of filter actief | Foutenrapport lezen; filters wissen |
| CSV geweigerd | Kopregel gewijzigd, verkeerde codering, formulecellen | Sjabloon opnieuw downloaden, UTF-8 bewaren |
| "Dubbele rij" bij import | Zelfde leverancier+SKU dubbel in het bestand | Eén rij verwijderen |
| Omzetten geblokkeerd: bestaande offerte | Er hangt al een offerte aan de aanvraag | Eerst verwerken/verwijderen — nooit stilzwijgend overschreven |
| PDF-fout | Zeldzaam; meestal een ontbrekend gegeven op de offerte | Offerte controleren; anders ontwikkelaar |
| E-mail niet verzonden | Mailprobleem; offerte blijft concept | Later opnieuw proberen; maillog bekijken |
| AI-uitleg leeg | AI staat uit (normaal) of uitvoer werd afgekeurd | Geen actie nodig |
| Berekening lijkt verouderd na wijziging | Berekeningen zijn bewust momentopnames | "Opnieuw berekenen" klikken |
| Verkeerde voorraad/prijs | Catalogus niet bijgewerkt | CSV-update of productpagina |
| Geen toegang | Sessie verlopen of geen admin | Opnieuw aanmelden; ontwikkelaar bij aanhoudend probleem |

Geraakt u er niet uit: contacteer de ontwikkelaar (hoofdstuk 26). Voer
nooit zelf databank- of servercommando's uit.

---

## 24. Regels die u vóór productie moet valideren

Open **HVAC-producten → Berekeningsregels**. Elke regel toont waarde,
eenheid, uitleg en status:

- **Placeholder** — startwaarde van de ontwikkelaar, nog door niemand
  bevestigd;
- **Te valideren** — bedrijfsregel die u moet bevestigen;
- **Fabrikantspecifiek** — de echte waarde komt uit productdata zodra die
  geïmporteerd is;
- **Gevalideerd** — door u bevestigd (met naam, datum en notitie).

Regels met de rode markering **KRITIEK** (isolatiewaarden,
capaciteitsklassen, gelijktijdigheid, uurtarief, verplaatsing, marges, btw,
elektrische tabel) blokkeren de goedkeuring van echte aanbevelingen zolang
ze niet gevalideerd zijn. Valideren: klap "Valideren" open, zet eventueel
een notitie ("bevestigd met leverancier X op …") en klik. Intrekken kan
altijd.

Waarom belangrijk? Een foute basislast geeft te kleine of te grote
toestellen; een fout uurtarief of foute marge kost u rechtstreeks geld; een
fout btw-tarief is een administratief risico. Wie valideert: uzelf, waar
nodig met uw leverancier of elektricien. De volledige lijst met huidige
waarden staat ook in `docs/hvac/rules-to-validate.md`.

---

## 25. Veiligheid en verantwoordelijkheden

- Houd uw beheerlogin strikt persoonlijk; deel geen wachtwoorden en wijzig
  ze via **Account** bij twijfel.
- Meld af op gedeelde computers.
- Upload alleen CSV-bestanden waarvan u de herkomst kent, en controleer
  leveranciersdata vóór u ze importeert.
- Aankoopprijzen en marges zijn interne informatie — toon uw beheerscherm
  niet aan klanten.
- Keur nooit "blind" goed: elke waarschuwing bestaat om een reden.
- Controleer vóór elke verzending het e-mailadres van de ontvanger.
- Gebruik TEST-producten nooit voor echte klanten.

---

## 26. Back-up en ondersteuning

Het systeem bewaart aanvragen, berekeningen (met momentopnames), de
catalogus, offertes en logboeken in de databank van de website. Regelmatige
back-ups (afspraak met de ontwikkelaar/hosting) zijn belangrijk — zeker
vóór een grote catalogusimport: noteer dan datum, bestandsnaam en aantal
rijen.

**Iets vreemds gezien?** Geef de ontwikkelaar door: het aanvraagnummer
(MT-…), datum en uur, wat u deed, de exacte melding of waarschuwing en een
schermafbeelding. Stuur **nooit** wachtwoorden of klantgeheimen mee. Pas
zelf geen bestanden op de server aan.

---

## 27. Checklists

**A. Vóór het eerste echte gebruik**

☐ Leveranciersproducten geïmporteerd (incl. toebehoren met prijzen)
☐ Compatibiliteit geïmporteerd of ingevoerd
☐ Prijzen steekproefsgewijs gecontroleerd
☐ Voorraad en levertijden kloppen
☐ Alle KRITIEKE regels gevalideerd
☐ Uurtarief en verplaatsingsforfait bevestigd
☐ Marges en btw-beleid bevestigd
☐ Eén single-split-test volledig doorlopen
☐ Eén multi-split-test volledig doorlopen
☐ PDF gecontroleerd
☐ Testmail naar eigen adres gecontroleerd
☐ TEST-producten gedeactiveerd (geen rode banner meer)

**B. Vóór het goedkeuren van een optie**

☐ Kamergegevens en aannames kloppen (of ter plaatse gecontroleerd)
☐ Vermogen en doelklasse plausibel
☐ Compatibiliteit bevestigd
☐ Elektrische situatie gecontroleerd of ingepland
☐ Leidinglengtes t.o.v. productlimieten
☐ Toebehoren en hoeveelheden
☐ Arbeidsuren realistisch
☐ Prijs én marge aanvaardbaar
☐ Alle waarschuwingen begrepen/bevestigd
☐ Foto's van de klant bekeken

**C. Vóór het versturen van een offerte**

☐ Klantgegevens en e-mailadres juist
☐ Taal van de offerte = taal van de klant
☐ Juiste (goedgekeurde) optie omgezet
☐ Beschikbaarheid van de producten gecheckt
☐ Totaal en btw-tarief gecontroleerd
☐ Geldigheidsdatum ok
☐ PDF geopend en volledig nagelezen
☐ Voorwaarden/vermeldingen ok
☐ Bewust en handmatig op "Versturen" geklikt

---

## 28. Verklarende woordenlijst

| Term | Uitleg |
|---|---|
| Koelvermogen / kW | Hoeveel warmte een toestel per uur kan afvoeren; 1 kW = 1000 W. |
| W/m² | Benodigd vermogen per vierkante meter vloer; afhankelijk van isolatie. |
| Binnenunit / buitenunit | Het toestel in de kamer / de compressor buiten. |
| Single split / multi split | Eén binnenunit op één buitenunit / meerdere binnenunits op één buitenunit. |
| Nominale capaciteit | Het officiële vermogen van een toestel volgens de fabrikant. |
| Connected capacity | Het totaal aan binnenvermogen dat een multi-split-buitenunit aankan (venster met minimum en maximum). |
| Gelijktijdigheidsfactor (diversity) | Korting op de som van binnenvermogens omdat niet alle kamers tegelijk vol draaien. |
| Leidinglengte / hoogteverschil | Koelleidingtraject tussen binnen en buiten; fabrikanten stellen maxima. |
| Compatibiliteit | Door de fabrikant bevestigde combineerbaarheid van units. |
| SKU | Artikelcode van de leverancier; uniek per leverancier. |
| SEER / SCOP | Rendement bij koelen / verwarmen (hoger = zuiniger). |
| Marge / opslag | Verkoop min aankoop / percentage bovenop de aankoopprijs. |
| Btw | Belasting over de toegevoegde waarde (21 % of, onder voorwaarden, 6 %). |
| Regelset / versie | De volledige set rekenwaarden; elke wijziging = nieuwe versie. |
| Momentopname (snapshot) | De bewaarde kopie van invoer en regels bij elke berekening. |
| Override | Handmatige aanpassing, altijd met reden en bewaard origineel. |
| Audittrail | Het logboek van wie wat wanneer deed. |
| Conceptofferte | Offerte die bestaat maar nog niet verstuurd is. |
| CSV | Kommagescheiden tekstbestand, te bewerken in Excel. |
| Voorraad / levertijd | Beschikbaarheid bij de leverancier; weegt mee in de rangschikking. |

---

## 29. De website: werkgebied en gemeentepagina's

Sinds augustus 2026 staat het opvallende blok "Werkgebied" (met de knoppen
Tervuren, Overijse, Hoeilaart, …) **niet meer** op de homepage en in de
footer. Dat is een bewuste keuze: het oogde te veel als zoekmachinetruc.

Belangrijk om te weten:

- **De gemeentepagina's bestaan nog steeds** (bv. `/nl/tervuren`,
  `/nl/overijse`) en blijven vindbaar voor Google via de sitemap, de
  kruimelpaden en de werkgebiedpagina (`/nl/werkgebied`). Uw lokale
  vindbaarheid verandert hier niet door.
- Onderaan elke pagina staat één rustige zin — "Actief in de Druivenstreek
  en omliggende gemeenten." — die naar de werkgebiedpagina linkt.
- Op de dienstenpagina's blijft de rubriek "Waar we deze dienst uitvoeren"
  staan; die is informatief voor bezoekers én goed voor lokale SEO.
- Verwijder de gemeentepagina's dus **niet** en vraag ook niet om ze uit de
  sitemap te halen: zij dragen de lokale zoekresultaten.

---

*Einde van de handleiding. De technische bijlage voor de ontwikkelaar staat
in `docs/hvac/technische-supportbijlage.md`; de acceptatiechecklist ook los
in `docs/hvac/acceptatiechecklist-martin.md`.*
