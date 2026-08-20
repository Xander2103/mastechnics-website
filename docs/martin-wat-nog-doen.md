# Mastechnics HVAC — Wat moet Martin nog doen?

Dit is geen handleiding maar een opstartchecklist. De handleiding staat in
`docs/martin-handleiding.md`.

Stand van zaken: de techniek is klaar en getest, maar de catalogus bevat
**nog geen echte leveranciersdata** en de rekenregels zijn **nog niet door
u gevalideerd**. Het systeem blokkeert daarom bewust echte offertes tot de
punten hieronder in orde zijn.

---

## VERPLICHT VOOR DE EERSTE ECHTE HVAC-OFFERTE

- [ ] **Echte leverancierslijsten importeren** — minstens één echt
  gamma (bv. Fujitsu, Daikin, Cairox) via HVAC-producten → Import.
  Nu zit er alleen testdata in.
- [ ] **Bij elke import de prijsvraag juist beantwoorden** (brutoprijs /
  netto aankoopprijs / verkoopprijs) en daarna de prijzen steekproefsgewijs
  vergelijken met de papieren prijslijst.
- [ ] **Producten met "Controleren" nakijken** en verbeteren.
- [ ] **Verkoopprijzen invullen of controleren** — producten zonder
  bruikbare prijs blokkeren elke offerte.
- [ ] **Compatibiliteit ingeven of importeren** — welke binnenunits op
  welke buitenunits mogen, volgens de fabrikantdocumentatie. Zonder dit
  doet het systeem géén voorstellen. (Import → Compatibiliteit
  binnen- en buitenunits, of per product.)
- [ ] **Alle kritieke berekeningsregels valideren** onder
  HVAC-producten → Berekeningsregels — pas nadat u elke waarde vergeleken
  hebt met hoe Mastechnics echt rekent:
  - [ ] vermogen per m² per isolatieniveau (koellast)
  - [ ] uurtarief en werkuren per installatietype (arbeid)
  - [ ] verplaatsingskost
  - [ ] marge-/prijsregels
  - [ ] toebehorenregels (beugel, leidingset, goot, afvoer…)
- [ ] **Eén volledige proefofferte doorlopen**: echte aanvraag (of eigen
  test op de website) → Voorcalculatie uitvoeren → optie controleren →
  goedkeuren → Omzetten naar offerte → PDF nakijken → NIET versturen.
  Klopt alles (vermogens, producten, prijzen, btw, marge)?
- [ ] **TEST-producten deactiveren of hun lijst archiveren** zodra de
  echte catalogus werkt (de rode banner "Testcatalogus" moet weg zijn).
- [ ] Doorloop de **Checklist** in het beheer (HVAC-producten →
  Checklist) — 18 stappen vóór het eerste echte gebruik.

## PER NIEUWE LEVERANCIER

- [ ] Leverancier aanmaken of bij de import invullen.
- [ ] Eerste bestand importeren via **Nieuwe productlijst**, met een
  duidelijke naam ("Cairox HVAC 2026").
- [ ] Prijsbetekenis juist beantwoorden.
- [ ] Alleen de relevante productgroepen aanvinken.
- [ ] "Controleren"-producten nakijken.
- [ ] Compatibiliteit voor de nieuwe toestellen ingeven/importeren.
- [ ] **"Deze instellingen onthouden" → Ja** — volgende imports gaan dan
  vanzelf.
- [ ] Steekproef: 5 producten vergelijken met de officiële prijslijst.

## PER NIEUWE PRIJSLIJST / JAARLIJST (zelfde leverancier)

- [ ] Importeren en **"Bestaande productlijst bijwerken"** kiezen — géén
  nieuwe lijst, geen kopieën.
- [ ] Het voorbeeld nakijken: nieuw / gewijzigd / ongewijzigd / niet meer
  in bestand.
- [ ] Vinkje "Producten die niet meer in dit bestand staan inactief
  zetten" alleen aanvinken als die producten echt uit het gamma zijn.
- [ ] Bij een echte jaarwissel (2026 → 2027): nieuwe lijst aanmaken en de
  oude daarna **archiveren**.
- [ ] Prijssteekproef na de import.

## VOOR ELKE HVAC-OFFERTE

- [ ] Kamerinvoer nakijken — alles met "(aanname)" checken bij de klant.
- [ ] Waarschuwingen lezen en oplossen of bewust bevestigen (met reden).
- [ ] Voorgestelde toestellen herkennen en de marge controleren
  (rood = verkoopprijs dekt de kosten niet).
- [ ] Optie goedkeuren → Omzetten naar offerte.
- [ ] PDF openen en volledig nalezen.
- [ ] Zelf versturen via "✉ Offerte versturen" — het systeem mailt nooit
  automatisch.

## PERIODIEK (bv. maandelijks)

- [ ] Verouderde productlijsten archiveren.
- [ ] Nieuwe prijslijsten van leveranciers importeren (bijwerken).
- [ ] De datakwaliteitskaarten nalopen (Zonder prijs, Zonder
  compatibiliteit, Geblokkeerd) en wegwerken.
- [ ] "Controleren"-producten wegwerken.
- [ ] Bij nieuwe modellen: compatibiliteit aanvullen.
- [ ] Openstaande offertes opvolgen (widget "Offertes wachten op
  antwoord").
