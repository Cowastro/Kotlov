# TD borehole pump characteristics display

Read-only production audit: 37 TD-linked borehole pumps, including 28 AGELESS,
already have technical specifications in product specs and supplier raw descriptions.
All 37 have exact current name/article bindings. None is in the manufacturer passport registry.

The product controller replaces imported attributes with the manufacturer-only pump
presenter; its unverified placeholder consequently hides successfully imported facts.
This is a presentation issue, not a failed CSV download.

Example product 43744, AP01A20, AGELESS-3-3600/87-4/28-A:
supplier description explicitly states 1500 W, maximum head 115 m, maximum immersion
75 m, maximum flow 5.4 m3/h, pressure 11.5 bar, 28 impellers, diameter 3 inches / 7.62 cm,
length 1.73 m. These are supplier statements, not independently verified passport data.
The malformed connection `1 14` is not reconstructed.

The new detail-only presenter reads a conservative whitelist from an exactly linked,
supplier-owned TD offer. It stops before model-decoding examples and does not expose
unrelated accessories. Identity conflicts, renamed models, multiple offers and external
cards fail closed. Manufacturer-verified facts take precedence.

The page identifies TD as the source and keeps manufacturer verification pending.
No new manufacturer evidence is claimed. Numeric selection filters and verified flags
are unchanged. No product, supplier, price, stock, cart or order records are changed.

Validation: HouseholdPumpPickerTest (13 tests) and TdPumpSpecificationsTest (3 tests).
