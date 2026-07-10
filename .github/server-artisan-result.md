# Server Artisan Result

- Time: 2026-07-10 16:13:20 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --brand=CENTROMETAL --active-only --not-archived --rewrite-seo --limit=0 --sleep=500`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
[1/2] #11173 PS-011.173 Тепловой насос Centrometal 6 кВт
[2/2] #11174 PS-011.174 Тепловой насос Centrometal 8 кВт
APPLY: sanitized content was written.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 2     |
| changed             | 2     |
| written             | 2     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 2     |
+---------------------+-------+
+-------+------------+-------------+----------------------------------+
| ID    | SKU        | Brand       | Product                          |
+-------+------------+-------------+----------------------------------+
| 11173 | PS-011.173 | CENTROMETAL | Тепловой насос Centrometal 6 кВт |
| 11174 | PS-011.174 | CENTROMETAL | Тепловой насос Centrometal 8 кВт |
+-------+------------+-------------+----------------------------------+

```
