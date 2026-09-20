# Mastechnics — Van aanvraag tot offerte

**Praktische handleiding voor dagelijks gebruik**

Voor: Martin, zaakvoerder en installateur · Versie september 2026 · Hoort bij de beheeromgeving van mastechnics.be

Deze handleiding beschrijft wat u op het scherm ziet en waar u klikt. Schermnamen en knoppen staan **vet**, precies zoals ze in de applicatie staan. Formules en rekenregels staan in een apart document: de *Technische bijlage — Hoe de berekeningen werken* (`docs/martin-hvac-technische-bijlage.md`). Een samenvatting van één pagina vindt u in de *Snelstart* (`docs/martin-hvac-snelstart.md`).

---

## Hoofdstuk 1 — Welkom

### Wat doet de applicatie?

Een klant vult op de website een airco-aanvraag in: kamers met afmetingen, isolatie van de woning, ligging, ramen, daktype en foto's. De applicatie bewaart die aanvraag en kan er op uw vraag een **voorcalculatie** van maken: een berekend koelvermogen per kamer, een voorstel van toestellen uit úw productcatalogus, de nodige materialen, geraamde werkuren en een prijs. Een goedgekeurd voorstel zet u met één klik om in een conceptofferte met PDF.

### Wat wordt automatisch voorbereid?

- Het koelvermogen per kamer en de bijhorende capaciteitsklasse.
- Tot drie opties — **Budget**, **Aanbevolen**, **Premium** — met toestellen uit uw eigen catalogus.
- De hoeveelheden materiaal (leiding, goot, kabel, beugel, dempers, afvoerslang).
- De geraamde werkuren en de verplaatsing.
- De prijs per regel, het totaal, de btw en uw marge.
- Waarschuwingen bij alles wat een aanname is of wat ontbreekt.

### Wat moet u zelf controleren?

Alles wat hierboven staat. De voorcalculatie is een hulpmiddel, geen ontwerpstudie. U controleert het vermogen, de toestelkeuze en compatibiliteit, de leidinglengtes, de elektrische aansluiting, de plaatsing, de hoeveelheden, de uren, de prijzen en de btw.

### Wat gebeurt nooit automatisch?

> **Nooit automatisch**
> - Er wordt nooit een offerte of e-mail naar de klant gestuurd zonder dat u zelf op **Versturen** klikt.
> - Er wordt nooit een optie goedgekeurd of omgezet naar een offerte zonder uw klik.
> - Er wordt nooit een instelling goedgekeurd omdat het getal "er goed uitziet": u bevestigt elke belangrijke instelling uitdrukkelijk.
> - Er worden nooit nieuwe instellingen geactiveerd zonder uw bevestiging.
> - Het verlaagde btw-tarief wordt nooit vanzelf toegepast.
> - Er wordt nooit een prijs verzonnen: zonder prijs in de catalogus blijft de regel leeg.
> - Er wordt nooit een product verwijderd; producten worden alleen inactief gezet.

---

## Hoofdstuk 2 — De eerste keer instellen

Doorloop deze lijst één keer, in deze volgorde. **Verplicht** betekent: zonder deze stap kunt u geen echte HVAC-offerte goedkeuren.

| # | Stap | Verplicht? |
|---|------|------------|
| 1 | Inloggen | Verplicht |
| 2 | Bedrijfsgegevens controleren | Verplicht (controle) |
| 3 | Offerte-instellingen openen | Verplicht |
| 4 | Uurtarief invullen en bevestigen | Verplicht |
| 5 | Verplaatsingskost controleren | Verplicht |
| 6 | Opslagen op aankoopprijzen controleren | Verplicht |
| 7 | Btw-instelling controleren | Verplicht |
| 8 | Technische regels controleren | Verplicht |
| 9 | Productcatalogus toevoegen | Verplicht |
| 10 | Overige instellingen bevestigen | Optioneel, aanbevolen |
| 11 | Concept activeren | Alleen als u waarden gewijzigd hebt |

### Stap 1 — Inloggen (verplicht)

Ga naar `mastechnics.be/admin/login` en meld u aan met uw e-mailadres en wachtwoord. Bovenaan ziet u het beheermenu: **Aanvragen**, **Contactberichten**, **Beveiligingslog**, **HVAC-producten**, **Account**, **Geblokkeerde e-mails**, **Uitloggen**.

### Stap 2 — Bedrijfsgegevens controleren (verplicht, alleen controle)

Op de offerte-PDF staan de bedrijfsnaam, het telefoonnummer, het e-mailadres en — als het is ingevuld — het ondernemingsnummer.

> **Let op** — Er bestaat in de beheeromgeving géén scherm om bedrijfsgegevens te wijzigen. Ze zijn vast ingesteld door de ontwikkelaar. Open een offerte-PDF (hoofdstuk 7), lees de gegevens na en geef afwijkingen door aan de ontwikkelaar. Onder **Account** wijzigt u alleen uw eigen e-mailadres en wachtwoord.

### Stap 3 — Offerte-instellingen openen (verplicht)

Klik op **HVAC-producten** en daarna in de rij knoppen op **Offerte-instellingen**.

Bovenaan, onder **Waar staat u nu?**, ziet u vier tegels:

- **Belangrijke instellingen goedgekeurd** — bijvoorbeeld "0 van 12".
- **Nog te controleren** — hoeveel belangrijke instellingen nog wachten.
- **Instellingen in gebruik** — de versie waarmee nu gerekend wordt.
- **Automatische aanbevelingen** — *Niet beschikbaar* (geen toestellen in de catalogus), *Controleren* (er wordt gerekend, maar goedkeuren is geblokkeerd) of *Goedgekeurd*.

Daaronder staat **Volgende stap** met één knop die u naar het juiste onderdeel brengt, en de vijf onderdelen: **1. Koelvermogen**, **2. Toestellen**, **3. Installatie**, **4. Werkuren**, **5. Verkoopprijzen**.

Elke instelling heeft één van deze statussen:

| Status | Betekenis |
|--------|-----------|
| **Nog instellen** | Een startwaarde van de software, niet van Mastechnics. Vul uw eigen waarde in of bevestig dat ze klopt. |
| **Controleren** | Een waarde die nog op uw bevestiging wacht, of die gewijzigd is sinds uw laatste bevestiging. |
| **Goedgekeurd** | U hebt deze waarde bevestigd. Uw naam, het tijdstip, de waarde en de versie zijn bewaard. |
| **Niet beschikbaar** | Dit onderdeel kan nu niet gebruikt worden (bijvoorbeeld: lege catalogus). |

Instellingen met het rode label **Belangrijk** blokkeren het goedkeuren van een aanbeveling tot u ze bevestigd hebt.

### Stap 4 — Uurtarief invullen en bevestigen (verplicht)

1. Open **5. Verkoopprijzen**.
2. Zoek onder **Tarieven** de kaart **Uurtarief installatie**. U ziet de huidige waarde, wat ze betekent, waarvoor ze dient, een praktijkvoorbeeld (bv. "6 uur × € 65,00 = € 390,00 exclusief btw") en het gevolg van een verkeerde waarde.
3. **Klopt het tarief?** Vink *Ik bevestig dat € … per uur (excl. btw) het juiste tarief is* aan, schrijf eventueel een notitie voor uzelf en klik **Bevestigen**.
4. **Klopt het niet?** Klik bovenaan op **Waarde wijzigen? Maak een concept**. U komt in het concept terecht (oranje stippellijn bovenaan). Vul bij **Nieuwe waarde** uw tarief in, klik **Waarde opslaan in concept**, en bevestig daarna de nieuwe waarde met het vinkje en **Bevestigen**. Het concept activeert u in stap 11.

> **Voorbeeld** — U rekent € 72,50 per uur. In het concept vult u `72,5` in (een komma mag). Het praktijkvoorbeeld toont meteen "6 uur × € 72,50 = € 435,00".

### Stap 5 — Verplaatsingskost controleren (verplicht)

Zelfde scherm, kaart **Verplaatsingskost**. Het is een **vast bedrag per installatie**; de applicatie rekent niet per kilometer. Voor een verre werf past u de regel "Verplaatsing" later zelf aan in de voorcalculatie of in de offerte.

### Stap 6 — Opslagen controleren (verplicht)

Onder **Opslagen en btw** staan **Opslag op toestellen zonder verkoopprijs** en **Opslag op materialen zonder verkoopprijs**. Ze worden alleen gebruikt wanneer een product in uw catalogus wél een aankoopprijs maar géén verkoopprijs heeft.

> **Let op** — Een opslag is geen marge. Een opslag van 35% op € 1.000,00 geeft € 1.350,00. De winst van € 350,00 is 25,9% van de verkoopprijs, niet 35%. Meer technische uitleg: zie bijlage, hoofdstuk 9.

### Stap 7 — Btw-instelling controleren (verplicht)

Kaart **Standaard btw-tarief**: het tarief waarmee elke voorcalculatie start. Het verlaagde tarief wordt alleen gemeld als mogelijkheid (particulier + woning ouder dan 10 jaar) en nooit automatisch toegepast. U kiest het per aanvraag zelf (hoofdstuk 7).

### Stap 8 — Technische regels controleren (verplicht)

| Onderdeel | Belangrijke instellingen |
|-----------|--------------------------|
| **1. Koelvermogen** | Vier keer **Basisvermogen bij … isolatie** (uitstekend, goed, gemiddeld, beperkt) |
| **2. Toestellen** | **Capaciteitsklassen** en **Gelijktijdigheid bij multi-split** |
| **3. Installatie** | **Zekering en kabel per klasse** |

Deze drie laatste zijn tabellen. U kunt ze bevestigen, maar niet zelf wijzigen op het scherm; aanpassen gebeurt samen met de ontwikkelaar.

> **Let op** — Bevestig geen waarde die u niet herkent. Vergelijk ze met hoe Mastechnics vandaag echt rekent. Een bevestiging intrekken kan altijd via **Bevestiging intrekken**.

### Stap 9 — Productcatalogus toevoegen (verplicht)

Zonder uw eigen producten stelt de applicatie geen toestel voor. Zie hoofdstuk 8. Onder **2. Toestellen** ziet u daarna per capaciteitsklasse hoeveel toestellen in aanmerking komen, en welke productgegevens ontbreken.

### Stap 10 — Overige instellingen (optioneel, aanbevolen)

- **4. Werkuren**: geen enkele instelling blokkeert hier iets, maar de uren bepalen uw arbeidskost. Loop ze minstens één keer na.
- Onder elke groep klikt u op **Toon … overige instellingen in deze groep** voor de minder belangrijke waarden.
- **Snelle inschatting**: bevestig uw vier vuistregels per m³ onder **1. Koelvermogen**.

### Stap 11 — Concept activeren (alleen als u waarden gewijzigd hebt)

Ga naar **Offerte-instellingen** en scrol naar **Instellingen wijzigen**. Bij uw concept ziet u:

- **Wat verandert er?** — oude waarde doorstreept, nieuwe waarde ernaast.
- Welke belangrijke instellingen **nog niet bevestigd** zijn in dit concept.
- **Gevolgen van activeren**.

Staat er **Klaar om te activeren**, vink dan *Ik heb de wijzigingen gecontroleerd…* aan en klik **Concept activeren**. Hebt u niets gewijzigd en alleen bevestigd, dan is er geen concept en hoeft u niets te activeren.

> **Tip** — Wilt u eerst zien wat uw instellingen doen? Klik op **Bekijk voorbeeld**. U ziet een fictieve ruimte van 5 × 4 × 2,5 m doorgerekend tot een offerteprijs, met uw echte uurtarief en opslagen maar met een verzonnen toestel. Die pagina bewaart niets.

---

## Hoofdstuk 3 — Een aanvraag ontvangen

### Waar verschijnen nieuwe aanvragen?

Klik op **Aanvragen**. Bovenaan staan tegels met onder meer **Nieuwe aanvragen**; daaronder de tabel met **Datum, Naam, E-mail, Telefoon, Aanvraag, Urgentie, Status**. Met **Filters** zoekt u op naam, status, dienst of datum.

Staat er bij een aanvraag het label **Te controleren**, dan heeft de formulierbeveiliging ze als twijfelgeval opgeslagen en is er géén e-mail verstuurd. Open de aanvraag en kies **Vrijgeven…** of **Markeer als spam**.

### Wat ziet u in een aanvraag?

- **Klantgegevens**: naam, e-mail, telefoon, klanttype. Naast het telefoonnummer staat **WhatsApp ↗**.
- **Samenvatting** en **Aanvraaggegevens**: wat de klant invulde, per kamer.
- **Bijlagen**: de foto's en PDF's van de klant. Klik **Openen** of **Downloaden**.
- **Interne memo**, **Interne notities**, **Tijdlijn** en **Afspraken** voor uw eigen opvolging.
- Bij een airco-offerte: de kaart **Automatische airco-voorcalculatie**.

### Wat betekent een onvolledige aanvraag?

De kaart **Ontbrekende informatie** verschijnt alleen wanneer er iets ontbreekt, bijvoorbeeld *Geen foto's of bestanden toegevoegd*, *Geen telefoonnummer ingevuld* of *Locatiegegevens zijn onvolledig*. De applicatie vraagt die gegevens niet zelf op bij de klant.

### Hoe volgt u ontbrekende informatie op?

Bel de klant, stuur een bericht via **WhatsApp ↗** of gebruik het **Standaardantwoord**. Noteer wat u afsprak in **Interne notities** en zet de status op **Gecontacteerd**.

> **Voorbeeld** — Mevrouw Peeters (fictief) vraagt een airco voor een slaapkamer van 4 × 5 m, 2,5 m hoog, zuidgericht, grote ramen, goede isolatie. Onder **Ontbrekende informatie** staat *Geen foto's of bestanden toegevoegd*. U stuurt via WhatsApp: "Kunt u een foto sturen van de buitenmuur waar de buitenunit kan komen en van de zekeringkast?" Pas met die foto's kunt u leidingtraject en elektrische aansluiting beoordelen.

---

## Hoofdstuk 4 — Koelvermogen bepalen

Er zijn twee methodes. Ze staan los van elkaar; de ene vervangt de andere niet.

### Snelle inschatting

**Wanneer gebruiken?** Voor een eerste idee: aan de telefoon, bij een plaatsbezoek, of om een aanvraag snel op grootte te schatten.

**Waar?** **HVAC-producten → Offerte-instellingen → Snelle inschatting** (knop in de rij bovenaan, of via **1. Koelvermogen**).

**Wat invullen?** Lengte, breedte en hoogte van de ruimte in meter, en één situatie:

| Situatie | Vuistregel |
|----------|-----------:|
| Goed geïsoleerd | 30 W/m³ |
| Zuidgericht | 35 W/m³ |
| Veel zon en slecht geïsoleerd | 40 W/m³ |
| Veel zon, slechte isolatie en onder dak | 45 W/m³ |

Kies de situatie die het best past. De situaties worden niet opgeteld of met elkaar vermenigvuldigd. Klik **Inschatting berekenen**.

**Hoe leest u het resultaat?** U ziet het **Indicatief koelvermogen** in kW en de volledige berekening:

> **Voorbeeld** — 5 m × 4 m × 2,5 m = 50 m³. Goed geïsoleerd → 30 W/m³. 50 m³ × 30 W/m³ = 1.500 W = 1,5 kW.

Daaronder staat een **niet-bindende indicatie van de klasse** (bv. 2,5 kW).

**Waarom is dit nog geen toestelkeuze?** De vuistregel kent alleen het volume en één situatie. Grote glaspartijen, veel personen of toestellen, ventilatie, vocht en bijzondere ruimtes (veranda, keuken, praktijkruimte) vragen een uitgebreidere beoordeling. De pagina kiest daarom geen toestel, maakt geen offerte en bewaart niets. De vier waarden zijn uw eigen vuistregels, geen technische norm. Meer technische uitleg: zie bijlage, hoofdstuk 2.

### Gedetailleerde berekening

**Wanneer nodig?** Altijd wanneer u een offerte wilt maken. Alleen deze methode leidt tot een toestelvoorstel.

**Waar?** Open de airco-aanvraag, zoek de kaart **Automatische airco-voorcalculatie** en klik **Voorcalculatie uitvoeren** (of **Opnieuw berekenen**).

**Welke informatie is vereist?** Per kamer: breedte, lengte en hoogte, ligging, ramen en daktype; voor de woning: het isolatieniveau. Ontbreekt iets essentieels, dan meldt de applicatie *De berekening kon niet uitgevoerd worden — zie de blokkerende punten hieronder*. Vul de gegevens aan na contact met de klant.

**Wat controleert u?**

1. De tabel per kamer: afmetingen, vermogen in W en kW, en de **Doelklasse**.
2. Alles waar **(aanname)** bij staat: aantal personen, toestellen in de kamer en het leidingtraject worden niet gevraagd in het formulier.
3. De **waarschuwingen**, bijvoorbeeld bij een zolderkamer (er is nog geen bevestigde dakcorrectie) of wanneer het vermogen boven het klassenbereik ligt.
4. Onderaan: met welke versie van de instellingen is gerekend.

Vindt u het vermogen van een kamer niet realistisch? Klik bij die kamer op **Vermogen aanpassen**, vul het vermogen in **Watt** in met een **Reden (verplicht)** en klik **Opslaan**. De opties worden herrekend en uw aanpassing blijft zichtbaar onder **Handmatige aanpassingen**.

Meer technische uitleg over de formules: zie bijlage, hoofdstuk 3 en 4.

---

## Hoofdstuk 5 — Een toestel kiezen

### Wat is een capaciteitsklasse?

Een standaardvermogen waarnaar gezocht wordt: 2,5 · 3,5 · 5,0 · 6,0 · 7,1 kW. Een berekende koellast van 2,6 kW valt bijvoorbeeld in klasse 3,5 kW. De klasse is een zoekdoel, geen toestelkeuze. Onder **Offerte-instellingen → 2. Toestellen** ziet u de tabel en hoeveel van uw toestellen per klasse in aanmerking komen.

### Hoe worden producten voorgesteld?

De applicatie zoekt in uw catalogus naar actieve toestellen waarvan het koelvermogen de berekende koellast dekt en niet te ver boven de klasse ligt. Ze toont tot drie opties: **Budget** (goedkoopste), **Aanbevolen** (beste rangschikking, voorraad eerst) en **Premium** (duurste). Is er maar één geldige combinatie, dan ziet u alleen **Aanbevolen**.

### Waarom is een product soms geblokkeerd?

- Het heeft geen koelvermogen, geen prijs of geen leiding- en hoogtelimieten.
- Het zit alleen in een gearchiveerde productlijst of staat inactief.
- Het werd bij de import gemarkeerd als **controleren** (bv. onduidelijke prijsbetekenis).
- De geschatte leidinglengte of het hoogteverschil overschrijdt de limiet van het product.

### Wat betekent ontbrekende compatibiliteit?

Een losse binnenunit wordt alleen voorgesteld met een buitenunit waarvoor ú de compatibiliteit hebt ingevoerd. Ontbreekt die, dan meldt de applicatie *geen gekoppelde buitenunit in de catalogus … handmatige controle vereist* en kan de optie niet goedgekeurd worden.

> **Let op** — Onbekende compatibiliteit is géén bevestigde compatibiliteit. De applicatie raadt nooit een combinatie. Compatibiliteit voegt u toe op de productpagina (**Compatibiliteit → Regel toevoegen**) of via het compatibiliteitsbestand onder **Import**.

### Een ander toestel kiezen

In een optie kiest u bij een toestel **Ander product (zelfde type)…** en klikt u **Product wijzigen**, met een reden. De applicatie controleert compatibiliteit en limieten opnieuw; past het niet, dan kan de optie niet goedgekeurd worden.

### Geen geschikt toestel gevonden?

1. Kijk onder **2. Toestellen** of er in die klasse toestellen zijn.
2. Controleer onder **HVAC-producten → Alle producten** de kwaliteitstegels (**Zonder prijs**, **Zonder leidinglimieten**, **Zonder compatibiliteit** …) en vul aan.
3. Ligt de koellast boven 7,1 kW of is de ruimte bijzonder, kies dan zelf en maak de offerte handmatig via **+ Offerte aanmaken**.

Meer technische uitleg: zie bijlage, hoofdstuk 5.

---

## Hoofdstuk 6 — Installatiekosten controleren

Het aanvraagformulier vraagt niet waar de buitenunit komt of hoe de leiding loopt. De applicatie rekent daarom met een standaardsituatie en meldt dat telkens.

| Onderdeel | Wat wordt automatisch geschat? | Wat bevestigt u op basis van de werf? |
|-----------|-------------------------------|----------------------------------------|
| **Leidingen** | Aangenomen traject (standaard 5 m, 4 bochten, 2,5 m stijging per kamer); goot en kabel afgeleid van die lengte | Werkelijke lengte, bochten, hoogteverschil; limieten van de fabrikant |
| **Elektrische aansluiting** | Indicatie van zekering en kabel per klasse, of de fabrikantgegevens van het product | Altijd: aparte kring, zekering, kabelsectie, toestand van de kast |
| **Muurbeugels** | Eén beugel en één set dempers per buitenunit (muurmontage aangenomen) | Muur, plat dak of grond? Andere steun nodig? |
| **Condensafvoer** | Afvoerslang per binnenunit; condensaatpomp als *optionele* regel | Kan de afvoer op natuurlijk verval? Pomp nodig? |
| **Extra koelmiddel** | Alleen gemarkeerd als *optioneel* boven een bepaalde leidinglengte, zonder hoeveelheid | Hoeveelheid volgens de fabrikant |
| **Extra werkuren** | Basis, extra binnenunits, leiding boven 5 m, boringen, elektriciteit, eventueel tweede technieker en toeslag dak/zolder | Bereikbaarheid, afwerking, boorwerk in beton |
| **Verplaatsing** | Eén vast bedrag | Aanpassen bij een verre werf |

Een hoeveelheid of prijs aanpassen doet u in de optie via **Waarde aanpassen**: vul **Aantal** en/of **Prijs** in, geef een **Reden (verplicht)** en klik **Opslaan**. De totalen worden herrekend.

> **Let op** — Prijzen van materialen komen alleen uit uw catalogus. Staat een materiaal er niet in, dan ziet u de hoeveelheid maar geen prijs, en staat de optie op *Handmatige controle vereist*.

Meer technische uitleg: zie bijlage, hoofdstuk 6, 7 en 8.

---

## Hoofdstuk 7 — De offerte maken

### Van voorcalculatie tot verzonden offerte

1. **Optie kiezen.** Vergelijk Budget, Aanbevolen en Premium. Bekijk per optie de regels, het **Totaal incl. btw** en de **Marge**. Een negatieve marge staat in het rood.
2. **Aanpassen wat nodig is** (alleen zolang de optie niet goedgekeurd is): **Waarde aanpassen**, **Product wijzigen**, **Korting toevoegen** (bedrag + reden) en **Btw aanpassen** (21% of 6%, met reden).
3. **Waarschuwingen bevestigen.** Staat de optie op *Handmatige controle vereist*, lees dan de waarschuwingen, los op wat kan, en klik **Waarschuwing bevestigen** met een reden.
4. **Optie goedkeuren.** Klik **Optie goedkeuren**. Is de knop grijs, dan staat de reden erbij (zie hoofdstuk 10).
5. **Omzetten naar offerte.** Klik **Omzetten naar offerte**. Er ontstaat een **conceptofferte** met een offertenummer. Er vertrekt géén e-mail. Aankoopprijzen en marges worden nooit naar de offerte gekopieerd.
6. **Offerte nalezen en bijwerken.** In de kaart **Offerte** klikt u **✏ Bewerken**. U mag wijzigen: titel, omschrijving, **Geldig tot**, en elke offerteregel (omschrijving, aantal, prijs excl. btw, btw-tarief 0, 6, 12 of 21%). Met **+ Regel toevoegen** voegt u regels toe. Klik **Offerte opslaan**.
7. **PDF controleren.** Klik **↓ PDF** en lees de PDF volledig: klantgegevens, regels, totalen, btw, geldigheid, bedrijfsgegevens. De PDF staat in de taal van de aanvraag (NL, FR of EN).
8. **Versturen.** Klik **✉ Offerte versturen**. Controleer **Aan**, **Onderwerp** en **Bericht**; de PDF wordt als bijlage toegevoegd. Klik **Versturen**. Pas dan gaat de e-mail weg en krijgt de offerte de status **Verstuurd**.
9. **Opvolgen.** Met **Gewonnen ▸** of **Verloren ▸** sluit u de offerte af.

> **Nooit automatisch** — De applicatie mailt een offerte nooit vanzelf naar de klant. Verzenden gebeurt uitsluitend via **✉ Offerte versturen → Versturen**.

### Wat mag u nog wijzigen, en wanneer?

| Moment | Wat kan nog? |
|--------|--------------|
| Optie in concept | Alles: hoeveelheden, prijzen, producten, korting, btw, vermogen per kamer |
| Optie goedgekeurd | Niets meer aan de optie. Wilt u toch iets wijzigen: **Optie afwijzen** of opnieuw berekenen en opnieuw goedkeuren |
| Conceptofferte | Alles in de offerte-editor |
| Offerte verstuurd | Niets meer: *Een verstuurde offerte kan niet meer bewerkt worden.* |

### Hoe controleert u de prijzen?

- Per regel: klopt de verkoopprijs met uw prijslijst? Staat er een waarschuwing *geen verkoopprijs in de catalogus — …% marge op aankoopprijs toegepast*, dan is de prijs berekend met uw opslag. Kijk ze na.
- **Marge**: productmarge in € en als % van het totaal excl. btw. *onvolledig — aankoopprijzen ontbreken* betekent dat minstens één aankoopprijs ontbreekt.
- Btw: standaard 21%. Bij de melding *Mogelijk 6% btw van toepassing* controleert u zelf de voorwaarden en past u het tarief toe via **Btw aanpassen**.

### Wanneer is de offerte klaar voor verzending?

Wanneer de optie goedgekeurd is, de offerte is nagelezen, de PDF volledig klopt en u de btw hebt gecontroleerd.

> **Let op** — Per aanvraag bestaat er één offerte. Bestaat er al een, dan weigert de applicatie een tweede omzetting: bestaande offertes worden nooit stilzwijgend overschreven. Een offerte uit de testcatalogus krijgt `[TESTCATALOGUS]` in de titel en kan niet verstuurd worden.

---

## Hoofdstuk 8 — Producten en leveranciers beheren

### Nieuwe leverancier

**HVAC-producten → Leveranciers**. Vul **Naam** (verplicht), eventueel **Code**, **E-mail** en **Telefoon** in en klik **+ Leverancier toevoegen**. Een leverancier kunt u daarna alleen **Deactiveren** of **Activeren**; wijzigen of verwijderen is niet voorzien. U kunt de leverancier ook gewoon invullen tijdens de import.

### Nieuwe productlijst importeren (CSV of Excel)

**HVAC-producten → Import**, kaart **Leveranciersbestand importeren**. Kies het bestand, vul eventueel de leverancier in en klik **Bestand analyseren**. Er wordt niets bewaard vóór u op **Importeren** klikt. De wizard heeft vijf stappen:

1. **Bestand** — de applicatie herkent scheidingsteken, tabblad en kolomkoppen. Alleen bij twijfel stelt ze een vraag.
2. **Producten** — *Welke producten wilt u importeren?* Vink de productgroepen aan die met klimatisatie te maken hebben. De rest wordt niet ingelezen. Klik **Verder naar controle**.
3. **Controle** — onder **Herkende gegevens** ziet u welke kolom waarvoor dient. Onder **Nog te beantwoorden** staat de belangrijkste vraag: de prijsbetekenis (zie hieronder). Onder **Geavanceerde koppelingen** kunt u een kolom anders koppelen. Klik **Verder naar importeren**.
4. **Importeren** — *Klaar om te importeren*: u ziet hoeveel producten nieuw zijn, hoeveel bijgewerkt worden (gewijzigd/ongewijzigd) en hoeveel overgeslagen. Kies **Nieuwe productlijst** en geef ze een naam (bv. "Leverancier X 2026–2027"). Klik **Importeren**.
5. **Resultaat** — *Import voltooid*, met de aantallen. Wilt u de instellingen van deze leverancier onthouden, bewaar ze dan: een volgend bestand wordt automatisch herkend.

### Prijsbetekenis controleren

De wizard vraagt: *Wat betekent "…" in dit bestand?*

| Keuze | Gevolg |
|-------|--------|
| **Brutoprijs van de leverancier (catalogusprijs)** | Prijs wordt bewaard, maar **niet** gebruikt voor automatische offertes |
| **Netto aankoopprijs (wat wij betalen)** | Wordt de aankoopprijs; de verkoopprijs komt uit uw opslag tenzij u er zelf een invult |
| **Verkoopprijs (wat de klant betaalt)** | Wordt de verkoopprijs |
| **Weet ik niet** | Prijs wordt bewaard, maar **niet** gebruikt |

> **Let op** — Kies nooit "netto" of "verkoop" op gevoel. Een brutoprijs als aankoopprijs gebruiken maakt elke offerte fout. Vraag het bij twijfel aan de leverancier.

### Producten nakijken na de import

Onder **HVAC-producten → Alle producten** tonen de tegels wat ontbreekt: **Zonder prijs**, **Zonder voorraad**, **Zonder elektrische data**, **Zonder leidinglimieten**, **Zonder compatibiliteit**, **Klaar voor aanbeveling**, **Geblokkeerd**. Klik een tegel aan, open een product met **Bewerken** en vul aan. Aankoop- en verkoopprijs staan onder **Prijs, voorraad & opties**. Onder **Bron** ziet u uit welk bestand het product komt.

### Bestaande lijst bijwerken

Importeer het nieuwe bestand en kies in stap 4 **Bestaande productlijst bijwerken** en de juiste lijst. Optioneel vinkt u aan: *Producten die niet meer in dit bestand staan inactief zetten* — dat gebeurt alleen voor producten die in geen enkele andere actieve lijst zitten.

### Oude lijst archiveren

**HVAC-producten → Productlijsten**, klik **Archiveren** op de lijst. De lijst verdwijnt uit het gewone overzicht (tabblad **Gearchiveerd**); producten die alleen in gearchiveerde lijsten zitten, worden niet meer voorgesteld. **Activeren** maakt het ongedaan.

### Waarom worden ontbrekende producten niet verwijderd?

Oude voorcalculaties en offertes verwijzen naar die producten. Verwijderen zou die historiek breken, en de database weigert het ook. Daarom wordt een product alleen **inactief** gezet: het blijft zichtbaar in oude dossiers, maar wordt niet meer voorgesteld.

---

## Hoofdstuk 9 — Instellingen wijzigen

### Wat gebeurt er als u uw uurtarief aanpast?

1. **Offerte-instellingen → 5. Verkoopprijzen → Waarde wijzigen? Maak een concept.** Er ontstaat een concept: een kopie van uw instellingen. Uw eerdere bevestigingen gaan mee.
2. Vul bij **Uurtarief installatie** de nieuwe waarde in en klik **Waarde opslaan in concept**. Wie wat wanneer wijzigde, wordt bewaard.
3. De bevestiging van die ene instelling vervalt: de waarde is veranderd. Bevestig ze opnieuw in het concept.
4. Ga naar **Offerte-instellingen → Instellingen wijzigen**, lees **Wat verandert er?** en klik **Concept activeren** met het vinkje.

Zolang het concept niet geactiveerd is, verandert er niets: nieuwe voorcalculaties rekenen nog met het oude tarief. Met **Concept annuleren** zet u het concept opzij zonder iets te wijzigen. Er bestaat telkens maar één concept.

### Wat gebeurt er met oude offertes?

Niets. Elke voorcalculatie bewaart de instellingen waarmee ze gemaakt is, en een offerte bewaart haar eigen regels en prijzen. Een nieuw tarief geldt alleen voor berekeningen die u **na** de activering uitvoert. Wilt u voor een lopende, nog niet goedgekeurde aanvraag het nieuwe tarief gebruiken, klik dan in die aanvraag op **Opnieuw berekenen**.

### Wanneer moet u een nieuwe versie activeren?

Alleen wanneer u een **waarde** wijzigt. Enkel bevestigen (de waarde klopt al) doet u rechtstreeks op de actieve instellingen; daarvoor is geen concept en geen activering nodig.

### Hoe controleert u of de wijziging correct werkt?

1. Open **Bekijk voorbeeld**: de tabel **Werkuren volgens uw instellingen** toont het nieuwe tarief.
2. Open een airco-aanvraag, klik **Opnieuw berekenen** en controleer onderaan de kaart de versie van de instellingen en in de optie de regel *Installatie en indienststelling (… u × € …/u)*.

> **Let op** — Activeert u een concept waarin nog belangrijke instellingen onbevestigd zijn, dan blijven voorcalculaties werken maar kunt u geen aanbeveling goedkeuren tot u ze bevestigt. De samenvatting waarschuwt u daarvoor.

Meer technische uitleg over versies: zie bijlage, hoofdstuk 11.

---

## Hoofdstuk 10 — Problemen oplossen

**Waarom kan ik een offerte niet goedkeuren?**
Mogelijke oorzaak → wat u doet:

- *Niet alle belangrijke instellingen zijn door u bevestigd* → **Offerte-instellingen**, volg **Volgende stap** tot alles op *Goedgekeurd* staat.
- *Technische validatie ontbreekt* → compatibiliteit of limieten ontbreken; vul het product aan of kies een ander toestel.
- *Minstens één verplichte regel heeft geen prijs uit de catalogus* → voeg het materiaal met prijs toe aan de catalogus, of vul de prijs in via **Waarde aanpassen**.
- Status *Handmatige controle vereist* → klik eerst **Waarschuwing bevestigen**.
- *Deze optie hoort bij een verouderde berekening* → klik **Opnieuw berekenen** en beoordeel de nieuwe opties.

**Waarom vind ik geen geschikt toestel?**
Geen toestel in die klasse, toestellen zonder koelvermogen of prijs, een gearchiveerde lijst, of een koellast boven het klassenbereik → kijk onder **2. Toestellen** en de kwaliteitstegels onder **HVAC-producten → Alle producten**; vul aan of kies handmatig.

**Waarom staat er "Nog te controleren" of "Controleren"?**
De instelling is nog nooit bevestigd, of haar waarde is gewijzigd sinds uw bevestiging → open de instelling, lees het voorbeeld, vink aan en klik **Bevestigen**.

**Waarom ontbreekt een prijs?**
Het product of materiaal heeft geen prijs in de catalogus, of de prijs werd bij de import als *bruto* of *weet ik niet* aangeduid en mag dus niet gebruikt worden → open het product via **Bewerken** en vul aankoop- of verkoopprijs in.

**Waarom kan ik een instelling niet wijzigen of activeren?**
U bent niet in een concept (wijzigen kan alleen daar) → klik **Waarde wijzigen? Maak een concept**. Of de instelling is een tabel (klassen, gelijktijdigheid, zekering/kabel) → aanpassen samen met de ontwikkelaar. Of u vergat het bevestigingsvinkje bij **Concept activeren**. Of de waarde ligt buiten de toegelaten grenzen (het scherm toont ze).

**Waarom verschijnt een product niet in aanbevelingen?**
Inactief, alleen in een gearchiveerde lijst, zonder koelvermogen, te groot of te klein voor de klasse, bij import gemarkeerd als *controleren*, of zonder compatibiliteit → open het product en controleer **Capaciteit**, **Prijs, voorraad & opties**, **Compatibiliteit** en **Bron**.

**Wat doe ik wanneer de berekening onrealistisch lijkt?**
Controleer eerst de afmetingen in de aanvraag (een typfout van de klant komt vaak voor) en alles met **(aanname)**. Vergelijk met de **Snelle inschatting**. Pas het vermogen van de kamer aan via **Vermogen aanpassen** met een reden. Blijft een instelling verkeerd uitkomen, wijzig ze dan via een concept (hoofdstuk 9). Vertrouw bij bijzondere ruimtes op uw eigen beoordeling.

**De snelle inschatting toont "Niet beschikbaar".**
De instellingen in gebruik bevatten geen vuistregels per m³ → neem contact op met de ontwikkelaar.

---

## Hoofdstuk 11 — Dagelijkse snelstart

| Stap | Waar | Wat |
|------|------|-----|
| 1. Nieuwe aanvraag | **Aanvragen** | Open de aanvraag, lees **Samenvatting** en **Bijlagen** |
| 2. Gegevens controleren | kaart **Ontbrekende informatie** | Ontbreekt iets? Bel of **WhatsApp ↗** |
| 3. Berekening | **Automatische airco-voorcalculatie** | **Voorcalculatie uitvoeren**; controleer kamers, **(aanname)** en waarschuwingen |
| 4. Toestel | opties Budget / Aanbevolen / Premium | Controleer vermogen, compatibiliteit, limieten |
| 5. Kosten | regels in de optie | Hoeveelheden, uren, prijzen, **Marge**; aanpassen via **Waarde aanpassen** |
| 6. Offerte | **Optie goedkeuren** → **Omzetten naar offerte** | Er ontstaat een concept; er vertrekt geen mail |
| 7. Goedkeuring | **✏ Bewerken** | Titel, regels, geldigheid, btw |
| 8. PDF | **↓ PDF** | Volledig nalezen |
| 9. Verzenden | **✉ Offerte versturen** → **Versturen** | Alleen u verstuurt |

De eenbladversie om af te drukken: `docs/martin-hvac-snelstart.md`.

---

## Hoofdstuk 12 — Belangrijke veiligheidsregels

1. **Controleer technische berekeningen.** De voorcalculatie is een hulpmiddel met aannames. Bij veel glas, zolders, keukens en praktijkruimtes beslist u.
2. **Gebruik geen onbevestigde instellingen voor definitieve offertes.** De applicatie blokkeert het goedkeuren zolang belangrijke instellingen niet bevestigd zijn. Bevestig alleen wat u echt nagekeken hebt.
3. **Controleer productcompatibiliteit.** Onbekend is niet bevestigd. Ga af op de gegevens van de fabrikant.
4. **Controleer aankoop- en verkoopprijzen.** Let op de prijsbetekenis bij import en op het verschil tussen opslag en marge.
5. **Controleer de toepasselijke btw.** 6% is uw beslissing en uw verantwoordelijkheid, per klant en per woning.
6. **Verstuur pas na goedkeuring.** Lees elke PDF volledig voordat u op **Versturen** klikt.
7. **Werk nooit met de testcatalogus voor een echte klant.** De rode balk *Testcatalogus — niet gebruiken voor echte offertes* betekent dat er nog TEST-producten actief zijn.
8. **De snelle inschatting is een eerste idee, geen ontwerp.**

---

*Andere documenten: de algemene beheerhandleiding (`docs/martin-handleiding.md`) beschrijft ook contactberichten, account en opvolging. Bij tegenstrijdigheid over Offerte-instellingen, koelvermogen of de weg van aanvraag tot offerte geldt deze handleiding.*
