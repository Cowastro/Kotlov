# Server Artisan Result

- Time: 2026-07-12 09:09:45 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:sync-rn-profi --apply --price-file=storage/app/supplier-cache/rn-profi-pricelist.xlsx --brand=Varmega --available-only --max-delivery-days=3 --varmega-official --varmega-refresh-index --varmega-deep-index --varmega-deep-pages=0 --varmega-probe-missing --varmega-probe-limit=0 --only-new-source-url-domain=varmega.ru --sync-retail-prices --limit=0`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   f3df15b..4800ad7  main       -> origin/main
Updating f3df15b..4800ad7
Fast-forward
 .github/server-artisan-result.md | 331 +++++++++++++++++++--------------------
 .github/server-artisan-task.json |   6 +-
 2 files changed, 164 insertions(+), 173 deletions(-)
APPLY: matched RN-Profi supplier links will be updated.
Brand filter: 938 of 1941 rows selected only=varmega.
Availability filter: 938 of 938 rows selected max_delivery_days=3.
Official Varmega deep index progress: fetched=50, new_matches=1, still_missing=297.
Official Varmega deep index progress: fetched=100, new_matches=1, still_missing=297.
Official Varmega deep index progress: fetched=150, new_matches=1, still_missing=297.
Official Varmega deep index progress: fetched=200, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=250, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=300, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=350, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=400, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=450, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=500, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=550, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=600, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=650, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=700, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=750, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=800, new_matches=8, still_missing=290.
Official Varmega deep index progress: fetched=850, new_matches=14, still_missing=284.
Official Varmega deep index: fetched=861, new_matches=14, still_missing=284.
Official Varmega index: 7211 article URLs.
Official Varmega URL probes: checked=284 matched=7 skipped=0.
New source URL filter: 520 of 938 rows selected domain=varmega.ru.
+-----------------------------+-------+
| metric                      | count |
+-----------------------------+-------+
| matched_updated             | 520   |
| retail_synced               | 520   |
| created_from_price          | 0     |
| enriched_created            | 0     |
| supplier_price_changed      | 9     |
| supplier_stock_changed      | 520   |
| retail_price_changed        | 9     |
| skipped                     | 0     |
| skipped_not_linked          | 0     |
| skipped_duplicate_article   | 0     |
| missing_marked_out_of_stock | 0     |
| errors                      | 0     |
+-----------------------------+-------+

```
