# Guided supplier import wizard (Sprint 15)

Non-technical flow: the system does the technical work, the admin only makes
business decisions.

## Steps

1. **Bestand** — upload + optional supplier name → automatic analysis:
   file type, delimiter (`CsvDelimiterDetector`: `;` `,` tab `|`,
   quote-aware, multi-line consistency scoring), encoding (UTF-8 / cp1252 /
   BOM), worksheet, header row (`HeaderRowDetector`), column mapping
   (`ColumnMappingSuggester` aliases), profile recognition (header
   signature). Questions (delimiter / worksheet / header row) appear ONLY
   when detection is genuinely unsure.
2. **Producten** — `CategoryDetector` finds the category-like column
   (low-cardinality text + name hints such as GroupName/FamilyName) and shows
   value counts. HVAC-like values are pre-checked; nothing is excluded
   silently (empty cells become "(zonder groep)"). Selection filters rows
   streaming — 50k-row files stay cheap.
3. **Controle** — recognized-field summary (✓ herkend / ! controleren /
   — niet beschikbaar), open questions only for what the system cannot know:
   price meaning ("Wat betekent 'BrutPrice'?" — bruto/netto/verkoop/weet ik
   niet), missing supplier/brand, type fallback. Full manual mapping lives
   under "Geavanceerde koppelingen" (collapsed). Preview of ~10
   representative rows + summary cards.
4. **Importeren** — list name (suggested from filename/supplier/year), nieuwe
   of bestaande productlijst, existing-products mode, optional
   deactivate-missing (explicit, never automatic, never products that live in
   another active list). First database write happens here, in one
   transaction (products + catalog links + import run).
5. **Resultaat** — friendly counts, buttons, and the profile prompt ("Deze
   instellingen onthouden?") which saves delimiter, mapping, category filter,
   price semantics, type fallback and header signature.

## Derivation rules (explicit, provenance-tracked, visible)

- supplier / brand: wizard input when no column carries them (`manual`)
- name: LabelNL-style primary, falls back to LabelFR-style secondary
- model: falls back to SKU
- product type: conservative keyword inference (binnenunit/buitenunit/...),
  then the admin's fallback; never fuzzy compatibility guessing
- cooling capacity: parsed from the product name ("2,5 kW", "10500W",
  watts only in 500–30000 range) — ALWAYS review-flagged
- price: routed by confirmed meaning; bruto/onbekend NEVER fills
  purchase/sale price columns (product not safely priced for quotations)
- "-" cells are supplier placeholders → treated as empty

Provenance per product in `metadata.import`: file, timestamp, profile,
catalog id, source row, price column/raw/meaning, per-field origin
(column / derived / manual), needs_review.

## Productlijsten (catalog hierarchy)

`hvac_import_catalogs` + `hvac_import_catalog_product` (source facts per
list) + `hvac_import_runs` (history). Products page defaults to the list
overview; the flat table stays under "Alle producten". Archiving is a status
change only; missing products are never deleted; deactivation is explicit
and skips products in other active lists.

## Known limits / cannot safely automate

- Units whose label carries no capacity are rejected (visible per-row
  reason) — the engine must never select units with unknown capacity.
- Indoor↔outdoor compatibility is never inferred from names.
- Derived capacities and unknown price meanings stay "controleren" until
  Martin confirms them.
- Regression fixtures: `tests/Support/CatalogFrFixture.php` (sanitized
  CatalogFR.csv structure); `HvacRealCatalogFrQaTest` runs the real file
  end-to-end when present on this machine (skipped elsewhere).
