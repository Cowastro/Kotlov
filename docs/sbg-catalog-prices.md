# sbg.by catalog prices

`php artisan deal:sync-catalog-prices` previews the complete remote catalog without writing prices or permanent links. `--apply` updates only the `price` field through the configured Deal.by API, reads the catalog again and persists only verified one-to-one links. KOTLOV prices, remote descriptions, stock, articles and external IDs are never changed.

Matching uses exact product SKU, slug, matched active-supplier article, exact name, existing `kotlov-ID` external ID or the saved registry. Conflicting evidence, duplicate mappings, changed identities, inactive/archived/pricing-hold products, zero local prices, non-BYN currency and remote discounts are blocked. Model suffixes and dimensions are retained; similar names are not sufficient evidence.

The registry is `Storage::disk('local')/deal-by/product-links.json`. Every run saves an item-level plan with skip reasons and old/new prices in `deal-by/price-plans/`. Applying also saves the previous registry before API writes. Files remain private. API errors or failed rereads make the command fail; HTTP acceptance alone is not evidence of success. A partial write can be safely rerun after checking the plan.

The scheduler runs at minute 20 of each hour, with command and scheduler overlap locks. Existing supplier-specific jobs remain compatible and use the same KOTLOV retail source. The full-catalog command does not add or delete sbg.by products.

Use `--inspect-id=160512617` to expose only one public item's decision and prices in the safe workflow logs. The example sbg.by item has manufacturer article `4610094706663`, while the KOTLOV Standard 18 Panorama M source photo has article `4610094722250`. This pair must not be forced together without confirming the modification.
