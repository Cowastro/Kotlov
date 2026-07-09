# Server Artisan Result

- Time: 2026-07-09 20:11:44 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-source-products --supplier=akvatermex --domain=teplodvor.by --product=21165 --force --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=1`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN: source enrichment preview only.
Products with source URLs: 1 (processing 1, offset 0, --force)
[1/1] #21165 4670007717674 THERMEX N 10 O
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-n-10-o-pro
  found: images=4 specs=26 service=5 docs=1 video=1

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 1     |
| enriched         | 1     |
| images_found     | 4     |
| images_saved     | 0     |
| specs_found      | 26    |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 0     |
+------------------+-------+

```
