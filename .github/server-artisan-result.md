# Server Artisan Result

- Time: 2026-07-09 16:40:06 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=maitek-group --brand=Greolit --domain=greolit.by --product=20759 --force --replace-specs --min-specs-to-replace=4 --overwrite-images --clear-documents --skip-documents --limit=1 --sleep=1000`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN: source enrichment preview only.
Products with source URLs: 1 (processing 1, offset 0, --force)
[1/1] #20759 Greolit Котел Greolit DEEP plus 20 кВт без автоматики Greolit Котел Greolit DEEP plus 20 кВт без автоматики
  source: https://greolit.by/product/tverdotoplivnyj-kotel-deep-plus-20-40-kvt/
  found: images=4 specs=2 service=1 docs=1 video=0

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 1     |
| enriched         | 1     |
| images_found     | 4     |
| images_saved     | 0     |
| specs_found      | 2     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 0     |
+------------------+-------+

```
