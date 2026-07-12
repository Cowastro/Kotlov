# Server Artisan Result

- Time: 2026-07-12 19:46:47 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM16664 --fix-category --category-slug=predokhranitelnaya-i-reguliruyushchaya-armatura --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm16664-safety-valve.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   a61bfd9..90fcd03  main       -> origin/main
Updating a61bfd9..90fcd03
Fast-forward
 .github/server-artisan-result.md                   | 49 +++++++---------------
 .github/server-artisan-task.json                   |  8 ++--
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  1 +
 3 files changed, 19 insertions(+), 39 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 1.
Progress: checked=1 matched=0 missing=0 current=VM16664
+---------+---------+-----------------+----------------------------------------+------------------------------------------------------------------------+
| product | article | category        | name                                   | official_url                                                           |
+---------+---------+-----------------+----------------------------------------+------------------------------------------------------------------------+
| 20729   | VM16664 | Котлы отопления | Varmega VM16664 1 1/4" х 1 1/2", 3 бар | https://feniks-trade.ru/predohranitelnye-klapany-varmega/predohranitel |
+---------+---------+-----------------+----------------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 1     |
| matched          | 1     |
| written          | 1     |
| enriched         | 1     |
| images_found     | 4     |
| images_saved     | 1     |
| specs_found      | 10    |
| attributes_saved | 10    |
| category_changed | 1     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
