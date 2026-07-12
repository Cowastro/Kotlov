# Server Artisan Result

- Time: 2026-07-12 19:58:59 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM16701 --fix-category --category-slug=predokhranitelnaya-i-reguliruyushchaya-armatura --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm16701-safety-valve.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   90fcd03..630906f  main       -> origin/main
Updating 90fcd03..630906f
Fast-forward
 .github/server-artisan-result.md                   | 48 +++++++++++++++-------
 .github/server-artisan-task.json                   |  6 +--
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  1 +
 3 files changed, 38 insertions(+), 17 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 1.
Progress: checked=1 matched=0 missing=0 current=VM16701
+---------+---------+-----------------+-------------------------------+------------------------------------------------------------------------+
| product | article | category        | name                          | official_url                                                           |
+---------+---------+-----------------+-------------------------------+------------------------------------------------------------------------+
| 20730   | VM16701 | Котлы отопления | Varmega VM16701 1/2", 1.5 бар | https://bigstore.by/products/klapan-predohranitelnyj-sbrosnoj-varmega- |
+---------+---------+-----------------+-------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 1     |
| matched          | 1     |
| written          | 1     |
| enriched         | 1     |
| images_found     | 4     |
| images_saved     | 2     |
| specs_found      | 0     |
| attributes_saved | 0     |
| category_changed | 1     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
