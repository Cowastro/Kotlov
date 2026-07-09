# Server Artisan Result

- Time: 2026-07-09 21:15:42 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:sanitize-content-html --slug-like=truba-teplov-i-suhov-termo-tt-r-430-08-430-05-200-260-l250 --active-only --not-archived --extract-media --show-samples=5 --limit=1`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN: database will not be changed.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 1     |
| changed             | 1     |
| written             | 0     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 0     |
+---------------------+-------+
+------+------------+----------------+-------------------------------------------------------------------+
| ID   | SKU        | Brand          | Product                                                           |
+------+------------+----------------+-------------------------------------------------------------------+
| 9044 | PS-009.044 | Теплов и Сухов | Труба Теплов и Сухов термо ТТ-Р 430, 0.8/430, 0.5, Ø 200/260 L250 |
+------+------------+----------------+-------------------------------------------------------------------+

```
