# Server Artisan Result

- Time: 2026-07-12 13:15:43 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --article-prefix=VM702 --http-timeout=5 --limit=0`
- Log file: `storage/logs/varmega-vm702-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   5575f3e..a552534  main       -> origin/main
Updating 5575f3e..a552534
Fast-forward
 .github/server-artisan-result.md                        | 13 +++++++------
 .github/server-artisan-task.json                        |  6 +++---
 app/Console/Commands/RepairVarmegaSourceUrlsCommand.php |  5 ++---
 3 files changed, 12 insertions(+), 12 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 8.
Progress: checked=1 matched=0 missing=0 current=VM702001815
+---------+-------------+---------------+---------------------------+---------------------------------------------------------------------+
| product | article     | category      | name                      | official_url                                                        |
+---------+-------------+---------------+---------------------------+---------------------------------------------------------------------+
| 20463   | VM702001815 | Пресс-фитинги | Varmega VM702001815 18x15 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
| 20464   | VM702002215 | Пресс-фитинги | Varmega VM702002215 22x15 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
| 20465   | VM702002218 | Пресс-фитинги | Varmega VM702002218 22x18 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
| 20466   | VM702002815 | Пресс-фитинги | Varmega VM702002815 28x15 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
| 20467   | VM702002822 | Пресс-фитинги | Varmega VM702002822 28x22 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
| 20468   | VM702003528 | Пресс-фитинги | Varmega VM702003528 35x28 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
| 20469   | VM702004235 | Пресс-фитинги | Varmega VM702004235 42x35 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
| 20470   | VM702005442 | Пресс-фитинги | Varmega VM702005442 54x42 | https://rn-profi.by/index.php?route=product/product&product_id=1452 |
+---------+-------------+---------------+---------------------------+---------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 8     |
| matched          | 8     |
| written          | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
