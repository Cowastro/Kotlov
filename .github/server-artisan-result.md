# Server Artisan Result

- Time: 2026-07-10 12:48:45 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --sku=KOTLOV-000778 --active-only --not-archived --rewrite-seo --limit=1 --sleep=500`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
[1/1] #13056 KOTLOV-000778 Ballu BWH/S 30 Lorica
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
+-------+---------------+-------+-----------------------+
| ID    | SKU           | Brand | Product               |
+-------+---------------+-------+-----------------------+
| 13056 | KOTLOV-000778 | Ballu | Ballu BWH/S 30 Lorica |
+-------+---------------+-------+-----------------------+

```
