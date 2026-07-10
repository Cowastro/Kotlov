# Server Artisan Result

- Time: 2026-07-10 11:01:51 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --sku=PS-000.18213 --active-only --not-archived --rewrite-seo --limit=1 --sleep=500`
- Log file: `storage/logs/ferroli-torino-18213-seo-standard.log`
- Exit code: `0`

```text
[1/1] #18213 PS-000.18213 Газовый котёл Ferroli Torino Classic 25
APPLY: sanitized content was written.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 1     |
| changed             | 1     |
| written             | 1     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 1     |
+---------------------+-------+
+-------+--------------+---------+-----------------------------------------+
| ID    | SKU          | Brand   | Product                                 |
+-------+--------------+---------+-----------------------------------------+
| 18213 | PS-000.18213 | Ferroli | Газовый котёл Ferroli Torino Classic 25 |
+-------+--------------+---------+-----------------------------------------+

```
