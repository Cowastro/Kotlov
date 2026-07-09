# Server Artisan Result

- Time: 2026-07-09 21:12:23 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --slug-like=teplov-i-suhov --active-only --not-archived --extract-media --overwrite-media --rewrite-seo --show-samples=5 --limit=5 --sleep=1000`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
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
+------+------------+----------------+------------------------------------------------------+
| ID   | SKU        | Brand          | Product                                              |
+------+------------+----------------+------------------------------------------------------+
| 8985 | PS-008.985 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 120 |
| 8986 | PS-008.986 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 150 |
| 8987 | PS-008.987 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 180 |
| 8988 | PS-008.988 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 200 |
| 8989 | PS-008.989 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 250 |
+------+------------+----------------+------------------------------------------------------+

```
