# Server Artisan Result

- Time: 2026-07-12 18:33:12 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM04302 --fix-category --category-slug=filtry --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm04302-filter.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   0c230f2..1daa53e  main       -> origin/main
Updating 0c230f2..1daa53e
Fast-forward
 .github/server-artisan-result.md                   | 178 ++++++++++++++++-----
 .github/server-artisan-task.json                   |   8 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |   1 +
 app/Services/ProductSourceEnricher.php             |  42 +++++
 4 files changed, 183 insertions(+), 46 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 1.
Progress: checked=1 matched=0 missing=0 current=VM04302
+---------+---------+-----------------+----------------------+------------------------------------------------------------------------+
| product | article | category        | name                 | official_url                                                           |
+---------+---------+-----------------+----------------------+------------------------------------------------------------------------+
| 20717   | VM04302 | Котлы отопления | Varmega VM04302 3/4" | https://b2b.rusklimat.com/catalog/product/filtr-mekhanicheskoy-ochistk |
+---------+---------+-----------------+----------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 1     |
| matched          | 1     |
| written          | 1     |
| enriched         | 1     |
| images_found     | 4     |
| images_saved     | 2     |
| specs_found      | 5     |
| attributes_saved | 4     |
| category_changed | 1     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
