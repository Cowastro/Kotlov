# Server Artisan Result

- Time: 2026-07-09 19:31:17 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=rusklimat --domain=rusklimat.by --max-current-attrs=2 --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=20`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN: source enrichment preview only.
Products with source URLs: 2 (processing 2, offset 0)
[1/2] #15428 skipped generic source URL: https://rusklimat.by/
[2/2] #19628 skipped generic source URL: https://rusklimat.by/

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 2     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 2     |
| errors           | 0     |
+------------------+-------+

```
