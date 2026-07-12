# Server Artisan Result

- Time: 2026-07-12 20:12:30 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM16704 --fix-category --category-slug=predokhranitelnaya-i-reguliruyushchaya-armatura --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm16704-safety-valve.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   630906f..7f55c6d  main       -> origin/main
Updating 630906f..7f55c6d
Fast-forward
 .github/server-artisan-result.md                   | 34 +++++++++++-----------
 .github/server-artisan-task.json                   |  6 ++--
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  1 +
 3 files changed, 21 insertions(+), 20 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 1.
Progress: checked=1 matched=0 missing=0 current=VM16704
+---------+---------+-----------------+-----------------------------+------------------------------------------------------------------------+
| product | article | category        | name                        | official_url                                                           |
+---------+---------+-----------------+-----------------------------+------------------------------------------------------------------------+
| 20731   | VM16704 | Котлы отопления | Varmega VM16704 1/2", 3 бар | https://bigstore.by/products/klapan-predohranitelnyj-sbrosnoj-varmega- |
+---------+---------+-----------------+-----------------------------+------------------------------------------------------------------------+
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
