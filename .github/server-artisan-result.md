# Server Artisan Result

- Time: 2026-07-09 16:11:16 UTC
- Task: `tail-log`
- Artisan args: ``
- Log file: `storage/logs/auto-varmega-source-url-repair.log`
- Exit code: `0`

```text
APPLY: matched RN-Profi supplier links will be updated.
Brand filter: 938 of 1941 rows selected only=varmega.
Availability filter: 938 of 938 rows selected max_delivery_days=3.
Official Varmega index: 7197 article URLs.
Official Varmega URL probes: checked=298 matched=7 skipped=0.
New source URL filter: 506 of 938 rows selected domain=varmega.ru.
+-----------------------------+-------+
| metric                      | count |
+-----------------------------+-------+
| matched_updated             | 506   |
| retail_synced               | 506   |
| created_from_price          | 0     |
| enriched_created            | 0     |
| supplier_price_changed      | 8     |
| supplier_stock_changed      | 506   |
| retail_price_changed        | 8     |
| skipped                     | 0     |
| skipped_not_linked          | 0     |
| skipped_duplicate_article   | 0     |
| missing_marked_out_of_stock | 0     |
| errors                      | 0     |
+-----------------------------+-------+

```
