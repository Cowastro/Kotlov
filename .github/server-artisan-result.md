# Server Artisan Result

- Time: 2026-07-12 16:26:26 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM721350735 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm721350735.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   4fd4c67..9cc8fe7  main       -> origin/main
Updating 4fd4c67..9cc8fe7
Fast-forward
 .github/server-artisan-result.md                   | 78 +++++++++++-----------
 .github/server-artisan-task.json                   |  6 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  4 ++
 3 files changed, 47 insertions(+), 41 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 1.
Progress: checked=1 matched=0 missing=0 current=VM721350735
+---------+-------------+---------------+----------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                             | official_url                                                           |
+---------+-------------+---------------+----------------------------------+------------------------------------------------------------------------+
| 20655   | VM721350735 | Пресс-фитинги | Varmega VM721350735 35x1 1/4"x35 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
+---------+-------------+---------------+----------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 1     |
| matched          | 1     |
| written          | 1     |
| enriched         | 1     |
| images_found     | 2     |
| images_saved     | 2     |
| specs_found      | 11    |
| attributes_saved | 10    |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
