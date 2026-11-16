# Household pump selection evidence

Checked 2026-11-16. `pump-manufacturer-facts.json` holds 222 exact product bindings:
55 borehole pumps, 32 surface pumps, 57 drainage/sewage pumps, 78 stations.
Sources are hosted by UNIPUMP: its 2026 domestic equipment catalogue and WQ/WQR leaflet.
Each model lists its PDF page numbers (1-based physical PDF pages, not printed folios).
The catalogue SHA-256 identifies the downloaded edition. Documents are linked in the picker and cards.

The public picker reads only these evidence records for these four categories. Legacy
specifications and attribute values do not fill gaps. Products without evidence remain
searchable by name/SKU/brand, price and availability. Their technical filter fields are
empty; the interface displays coverage and provides a data-verification facet.
The existing circulation/multistage normalizer is unchanged.

Bindings require product ID, exact stored title and the expected category. Renaming,
moving or repurposing a product invalidates the binding. Adding new imports requires
an explicit evidence review; manufacturer model digits are never parsed at runtime.
The registry changes catalogue presentation only, without rewriting product data or prices.

Rules used during review:

- Nominal Q/H is not maximum Q/H. WQ/WQR contribute voltage, max immersion and particles only.
- Pump body diameter differs from minimum bore diameter; both are recorded separately.
- Cable length, working immersion and maximum permitted immersion differ. VINT working
  depths do not supply the max-immersion facet. Cable extensions require separate checks.
- Maximum suction height is not discharge head, and tank capacity is not usable water reserve.
- Tank sizes are added only for documented configurations. A station title without a tank
  suffix/article does not establish capacity. The catalogue explicitly identifies base pumps.
- JS and MH 500/600 C have different manufacturer tables/revisions under similar names;
  ambiguous standalone models/configurations are excluded rather than borrowing another table.
- ECO FLOAT 2 and ARTVORT Q550B have conflicting head entries in the catalogue; no
  conflicting head is used. ECO FLOAT is not bound; Q550B has flow/immersion/particles only.
- FEKACUT's freely passing hard particles (9 mm) differ from cuttable material (30 mm).
  FEKAMAX hard particles differ from its larger fibrous-inclusion passage. Only the former
  supplies the hard-particle facet. No claim is made about sand/fibres or liquid temperature.

To extend: verify the exact manufacturer model and configuration, add individual confirmed
numbers/units and source pages, then bind the unchanged catalogue title/ID/category. Do not
copy values from an adjacent model, reseller table, or incomplete series-wide range.
