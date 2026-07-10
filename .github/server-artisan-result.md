# Server Artisan Result

- Time: 2026-07-10 16:18:41 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --brand=de-dietrich --active-only --not-archived --rewrite-seo --limit=0 --sleep=500`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
[1/5] #8773 PS-008.773 Газовый котел De Dietrich MS 24 FF
[2/5] #8774 PS-008.774 Газовый котел De Dietrich MS 24 MI
[3/5] #8777 PS-008.777 Газовый котел De Dietrich MS 24 MI FF
[4/5] #8778 PS-008.778 Газовый котел De Dietrich MSL 31 FF
[5/5] #11557 PS-011.557 Газовый котел De Dietrich MS 24
APPLY: sanitized content was written.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 5     |
| changed             | 5     |
| written             | 5     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 5     |
+---------------------+-------+
+-------+------------+-------------+---------------------------------------+
| ID    | SKU        | Brand       | Product                               |
+-------+------------+-------------+---------------------------------------+
| 8773  | PS-008.773 | De Dietrich | Газовый котел De Dietrich MS 24 FF    |
| 8774  | PS-008.774 | De Dietrich | Газовый котел De Dietrich MS 24 MI    |
| 8777  | PS-008.777 | De Dietrich | Газовый котел De Dietrich MS 24 MI FF |
| 8778  | PS-008.778 | De Dietrich | Газовый котел De Dietrich MSL 31 FF   |
| 11557 | PS-011.557 | De Dietrich | Газовый котел De Dietrich MS 24       |
+-------+------------+-------------+---------------------------------------+

```
