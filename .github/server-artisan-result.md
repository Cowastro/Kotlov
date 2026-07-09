# Server Artisan Result

- Time: 2026-07-09 19:29:10 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=akvatermex --domain=teplodvor.by --max-current-attrs=2 --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=20`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN: source enrichment preview only.
Products with source URLs: 0 (processing 0, offset 0)

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 0     |
+------------------+-------+

```
