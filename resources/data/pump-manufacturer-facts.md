# Manufacturer evidence for household pump selection

Checked 2026-11-17 against the read-only catalogue audit of 1,163 unchanged IDs/titles.
The current pass reviews all **376** previously unresolved cards:

- **151** receive manufacturer-confirmed parameters (some records remain partial).
- **48** are accessories or an empty collection reservoir, moved to
  `komplektuyushchie-dlya-nasosov` by an ID/title/source-category guarded migration.
- **177** remain unconfirmed; no values are invented or copied from another execution.

After the accessory move there are **1,115 pumps**, with at least some confirmed
parameters for **938**: 204 borehole, 275 surface, 303 drainage/sewage, 156 stations.
The original 787 bindings and model records remain unchanged. This pass adds 56
UNIPUMP, 25 WELLMIX, 30 PURITY, 20 MERAN, 15 KOSHIN, 3 IBO, 1 JEMIX and 1 Wilo card.

`pump-remaining-review.json` accounts for every one of the 376 original cards with
its outcome, supplier identifiers, source/pages/confirmed fields or the unresolved
reason. `pump-manufacturer-unresolved.json` and `.md` list the remaining 177 by
ID/SKU/name, with supplier articles, specific conflicts and the evidence to request.
A supplier article is not automatically a factory article. The classification manifest
lists the 48 accessories independently; it contains no hydraulic assignments.

Sources include original UNIPUMP passports/catalogue and official article pages;
WELLMIX 2025/2026 tables; PURITY's global 50 Hz original catalogue; MERAN's brand
publisher catalogue; KOSHIN original model sheets/catalogues; IBO/DAMBAT's IP card;
the manufacturer-authored JEMIX catalogue; and Wilo's exact 4231480 product page.
Original manufacturer PDFs archived by a distributor (KOSHIN/JEMIX) are distinct
from reseller-generated descriptions. Every source has a downloaded-content SHA-256.
PDF page numbers are 1-based physical pages, not printed folios. An explicitly typed
HTML source has no fabricated PDF page and identifies the exact manufacturer article.
UNIPUMP article pages additionally link their published operating-manual PDFs.

## Presentation and filtering

The four pickers, product cards and product detail tables read the same evidence.
Legacy imported attributes/specifications do not fill missing technical fields or
replace verified data on product pages. Other categories' normalizers are unchanged.
Unknown models remain searchable by name/SKU/brand, price and stock. The interface
states partial coverage and offers a verification facet. Source links lead to the manufacturer page or the PDF
page containing that model's evidence. Stored descriptions are preserved in the database;
unreviewed imported short/body/meta descriptions are not shown for these four categories.
The public page uses a selection note and the verified technical table; metadata and
structured-data descriptions use the reviewed highlights. A main product's evidence link
must not be inherited by unrelated recommendation cards.

Bindings require the exact product ID, stored title and category. Renaming, moving or
repurposing a product invalidates the binding. There is no runtime model-name inference.
A new import needs its own explicit review. No prices or partners are changed.
The only category moves are the 48 exact accessory cards in the manifest; the shared
TSK classifier now recognizes their accessory types before pump-family shortcuts.
Existing product routes redirect an old category URL to the new canonical URL. The picker cache version is bumped when this evidence changes.

## Parameter semantics

- `head` / `flow` mean manufacturer-confirmed maxima (m / m³/h), reached at different
  operating points. A table at Q=0 establishes shut-off head; the last nonzero Q point
  does not establish maximum flow. No curve extrapolation is used.
- `rated_head` / `rated_flow` are explicitly nominal Q/H, e.g. WELLMIX tables. They are
  labelled nominal and never supply maximum-H/Q filters.
- `tabulated_head` / `tabulated_flow` retain a manufacturer's table entry where the
  document does not explicitly call it a maximum or a nominal value (e.g. PURITY WQ,
  KOSHIN ratings). They remain separate from both maximum and nominal fields.
- `free_passage` is mechanical free passage, not automatically an allowed hard-particle
  size. Only explicitly documented `solids` supplies the hard-particle facet.
- `power_kw` is the table's motor power; `input_power_kw` is explicitly electrical input
  power (P1 / consumed power). Gross shipping weight is not used as pump mass.
- Body diameter is distinct from minimum bore diameter. Cable length and working depth
  are not maximum permitted immersion under the water surface.
- Suction height is not discharge head. Pressure-vessel capacity is not usable water
  reserve; a sewage collection reservoir or a fuel tank is not a hydroaccumulator.
- Motor phase/configuration and suffixes are preserved. IMP PUMPS rows supply power,
  flange DN and mass where explicitly tabulated; their partial Q/H grid is not treated
  as pump maxima. PURITY A, QG, WQV and /2 or /4 executions are not interchangeable.
- JEELEX Comfort's lower head comes from its own table, not the base Jumbo pump.
  JEELEX l/min values are converted to m³/h by multiplying by 0.06.

## Conflicts and exclusions

- MINI ECO: the current passport states 40 m immersion, while the 2026 catalogue states
  80 m. The former stored 80 m is removed; neither value supplies the immersion filter.
  Both source URLs are retained under `conflicts`, and details explain the discrepancy.
- ECO RUS SWIM: catalogue introduction and maximum table disagree (4 vs 6 m³/h).
  Other confirmed fields are retained; maximum flow is omitted.
- Older plain ECO 1–5 and unbound variants remain unresolved. Older MINI ECO, JS and
  MH rows were added only where the supplier's exact factory article matched the
  official primary product table. No current-series value is inferred from a short name.
- Six WELLMIX articles identify a different model/nominal head in the 2025 table than
  the stored title. IMP 979526569 identifies 125-285 in the current table versus the
  stored 125-280. PURITY 100WQ65-17-5.5QG does not match the 65-15 primary row.
  These remain unbound and are itemized in the unresolved report.
- PURITY QG short labels appear in the manufacturer's performance-curve captions;
  the same catalogue supplies full /2 rows. Only their nominal Q/H, motor power and
  connection DN are used; voltage is not inferred from a missing phase suffix.
- WELLMIX 50WQ25 is the WQ family, not WQ2. Its physical page 6 explicitly gives
  power 3 kW, mass 41 kg, free passage 20 mm; merged-table parsing must preserve
  the family-specific column order.
- Earlier exclusions remain: ECO FLOAT 2 and ARTVORT Q550B conflicting head entries;
  FEKACUT's cuttable material size versus freely passing hard particles; FEKAMAX fibres
  versus hard particles; VINT working depth versus maximum immersion.

To extend: obtain the exact model/article/passport, verify each parameter's definition
and units, add only confirmed fields and physical PDF pages, then bind the unchanged
catalogue identity. Leave unavailable or contradictory fields empty.
