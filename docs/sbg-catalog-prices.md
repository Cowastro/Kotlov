# sbg.by catalog prices

`php artisan deal:sync-catalog-prices` previews the complete remote catalog without writing prices or permanent links. `--apply` updates only the `price` field through the configured Deal.by API, reads the catalog again and persists only verified one-to-one links. KOTLOV prices, remote descriptions, stock, articles and external IDs are never changed.

Matching uses exact product SKU, slug, matched active-supplier article, confirmed active-integration article/barcode (confidence at least 0.99), exact name, existing `kotlov-ID` external ID or the saved registry. Conflicting evidence, duplicate mappings, changed identities, inactive/archived/pricing-hold products, zero local prices, non-BYN currency and remote discounts are blocked. Model suffixes and dimensions are retained; similar names are not sufficient evidence.

The registry is `Storage::disk('local')/deal-by/product-links.json`. Every run saves an item-level plan with skip reasons and old/new prices in `deal-by/price-plans/`. Applying also saves the previous registry before API writes. Files remain private. API errors or failed rereads make the command fail; HTTP acceptance alone is not evidence of success. A partial write can be safely rerun after checking the plan.

Deal.by's API uses the legacy label `BYR` for current BYN amounts. The production diagnostic on 2026-11-13 returned BYR for all 100 sampled products; API product 160512617 reported 1494, matching its public sbg.by offer of 1494 BYN. The loader treats this as a label alias only for the exact `my.deal.by` API host, without multiplying or converting prices. Raw labels are retained in plans; other endpoints and currencies remain blocked. `diagnose-deal-currencies` is a read-only workflow task exposing only allowlisted currency counts.

The scheduler runs at minute 20 of each hour, with command and scheduler overlap locks. Existing supplier-specific jobs remain compatible and use the same KOTLOV retail source. The full-catalog command does not add or delete sbg.by products.

Use `--inspect-id=160512617` to expose only one public item's decision and prices in the safe workflow logs. The example sbg.by item has manufacturer article `4610094706663`, while the KOTLOV Standard 18 Panorama M source photo has article `4610094722250`. This pair must not be forced together without confirming the modification.
