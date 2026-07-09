# Server Artisan Result

- Time: 2026-07-09 18:11:49 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:sanitize-content-html --with-source-only --extract-media --active-only --not-archived --limit=0`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN: database will not be changed.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 3136  |
| changed             | 0     |
| written             | 0     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
+---------------------+-------+

```
