# Server Artisan Result

- Time: 2026-07-10 14:39:17 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --id=19170 --id=19220 --id=19348 --id=19360 --active-only --not-archived --rewrite-seo --limit=0 --sleep=500`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
[1/4] #19170 PS-000.19170 Циркуляционный насос DAB B 50/250.40 M
[2/4] #19220 PS-000.19220 Циркуляционный насос DAB EVOSTA 3 40/180 1”
[3/4] #19348 PS-000.19348 Циркуляционный насос DAB BPH 120/340.65T
[4/4] #19360 PS-000.19360 Циркуляционный насос DAB B 110/250.40 M
APPLY: sanitized content was written.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 4     |
| changed             | 4     |
| written             | 4     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 4     |
+---------------------+-------+
+-------+--------------+-------+---------------------------------------------+
| ID    | SKU          | Brand | Product                                     |
+-------+--------------+-------+---------------------------------------------+
| 19170 | PS-000.19170 | DAB   | Циркуляционный насос DAB B 50/250.40 M      |
| 19220 | PS-000.19220 | DAB   | Циркуляционный насос DAB EVOSTA 3 40/180 1” |
| 19348 | PS-000.19348 | DAB   | Циркуляционный насос DAB BPH 120/340.65T    |
| 19360 | PS-000.19360 | DAB   | Циркуляционный насос DAB B 110/250.40 M     |
+-------+--------------+-------+---------------------------------------------+

```
