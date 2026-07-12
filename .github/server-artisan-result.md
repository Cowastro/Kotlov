# Server Artisan Result

- Time: 2026-07-12 14:41:10 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-source-products --supplier=rn-profi --brand=Varmega --category=Пресс-фитинги --product=20510 --force --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --skip-ai --limit=1`
- Log file: `storage/logs/varmega-vm706001804-enrich-preview.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   8ae3669..fb97184  main       -> origin/main
Updating 8ae3669..fb97184
Fast-forward
 .github/server-artisan-result.md | 221 ++++++++++++++++++++++++++++++++++++---
 .github/server-artisan-task.json |   6 +-
 2 files changed, 207 insertions(+), 20 deletions(-)
DRY RUN: source enrichment preview only.
Products with source URLs: 1 (processing 1, offset 0, --force)
[1/1] #20510 skipped generic source URL: https://rn-profi.by/

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 1     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 1     |
| errors           | 0     |
+------------------+-------+

```
