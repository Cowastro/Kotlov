# Server Artisan Result

- Time: 2026-07-09 18:09:46 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --with-source-only --extract-media --active-only --not-archived --limit=0`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
APPLY: sanitized content was written.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 3136  |
| changed             | 5     |
| written             | 5     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
+---------------------+-------+
+-------+---------------+----------+------------------------------------------------+
| ID    | SKU           | Brand    | Product                                        |
+-------+---------------+----------+------------------------------------------------+
| 17041 | KOTLOV-004763 | Panadero | Panadero Камин 101-S Ecodesign                 |
| 20754 | KOTLOV-005920 | СТЭН     | СТЭН Заглушка свободного патрубка обратки G 1¼ |
| 20755 | KOTLOV-005921 | СТЭН     | СТЭН Заглушка свободного патрубка обратки G 1½ |
| 21322 | KOTLOV-006488 | Thermex  | Фильтр THERMEX ION SL 5"                       |
| 21323 | KOTLOV-006489 | Thermex  | Фильтр THERMEX ION SL 10"                      |
+-------+---------------+----------+------------------------------------------------+

```
