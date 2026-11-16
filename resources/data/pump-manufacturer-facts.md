# Manufacturer evidence for household pump selection

Checked 2026-11-16 against the read-only catalogue audit (1,163 product IDs and unchanged titles).
`pump-manufacturer-facts.json` now binds **787 products** to exact manufacturer records:
197 borehole pumps, 212 surface pumps, 247 drainage/sewage pumps, 131 stations.
This adds **565 previously unverified products** to the initial 222 bindings. A binding
means that at least some parameters are confirmed, not that every field is complete.

Sources: UNIPUMP's 2026 catalogue and model passports (including Chinese and Russian
ETSV versions separately); WELLMIX's November/October 2026 WQ2, GNWQ, WQ and WQD
catalogues; IMP PUMPS CL technical catalogue (2025); JEELEX model passports; PURITY's
50 Hz WQ tables; DAB Aquajet, Jet and Esybox Mini 3 technical sheets; KOSHIN model
catalogues and the SEY/STY/KTY operation manual. Every record identifies its URL,
downloaded PDF SHA-256 and 1-based physical PDF pages (not printed folios).

`pump-manufacturer-unresolved.json` lists the remaining **376 products**, their stored
SKU/title/ID, the reason the exact configuration could not be established, and what
to request from the supplier. This includes accessories which must not inherit pump
hydraulics. It is a review snapshot, not proof that a manufacturer manual does not exist.
No values were supplied from reseller cards or another brand's similarly named model.

## Presentation and filtering

The four pickers, product cards and product detail tables read the same evidence.
Legacy imported attributes/specifications do not fill missing technical fields or
replace verified data on product pages. Other categories' normalizers are unchanged.
Unknown models remain searchable by name/SKU/brand, price and stock. The interface
states partial coverage and offers a verification facet. Source links lead to the PDF
page containing that model's evidence. Stored descriptions are preserved in the database;
unreviewed imported short/body/meta descriptions are not shown for these four categories.
The public page uses a selection note and the verified technical table; metadata and
structured-data descriptions use the reviewed highlights. A main product's evidence link
must not be inherited by unrelated recommendation cards.

Bindings require the exact product ID, stored title and category. Renaming, moving or
repurposing a product invalidates the binding. There is no runtime model-name inference.
A new import needs its own explicit review. No prices, partners or category assignments
are changed. The picker cache version is bumped when this evidence changes.

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
- Older plain ECO 1–5, MINI ECO 1-144/1-93/2-85/3-34, JS and MH configurations are not
  mapped to a newer or different similarly named table without an exact article/revision.
- Earlier exclusions remain: ECO FLOAT 2 and ARTVORT Q550B conflicting head entries;
  FEKACUT's cuttable material size versus freely passing hard particles; FEKAMAX fibres
  versus hard particles; VINT working depth versus maximum immersion.

To extend: obtain the exact model/article/passport, verify each parameter's definition
and units, add only confirmed fields and physical PDF pages, then bind the unchanged
catalogue identity. Leave unavailable or contradictory fields empty.
