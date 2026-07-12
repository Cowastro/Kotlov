# Server Artisan Result

- Time: 2026-07-12 13:21:26 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --article-prefix=VM703 --http-timeout=5 --limit=0`
- Log file: `storage/logs/varmega-vm703-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   75e79ca..2779628  main       -> origin/main
Updating 75e79ca..2779628
Fast-forward
 .github/server-artisan-result.md | 31 +++++++++++++++----------------
 .github/server-artisan-task.json |  8 ++++----
 2 files changed, 19 insertions(+), 20 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 7.
Progress: checked=1 matched=0 missing=0 current=VM703001515
+---------+-------------+---------------+---------------------------+---------------------------------------------------------------------+
| product | article     | category      | name                      | official_url                                                        |
+---------+-------------+---------------+---------------------------+---------------------------------------------------------------------+
| 20471   | VM703001515 | Пресс-фитинги | Varmega VM703001515 15x15 | https://rn-profi.by/index.php?route=product/product&product_id=1031 |
| 20472   | VM703001818 | Пресс-фитинги | Varmega VM703001818 18x18 | https://rn-profi.by/index.php?route=product/product&product_id=1031 |
| 20473   | VM703002222 | Пресс-фитинги | Varmega VM703002222 22x22 | https://rn-profi.by/index.php?route=product/product&product_id=1031 |
| 20474   | VM703002828 | Пресс-фитинги | Varmega VM703002828 28x28 | https://rn-profi.by/index.php?route=product/product&product_id=1031 |
| 20475   | VM703003535 | Пресс-фитинги | Varmega VM703003535 35x35 | https://rn-profi.by/index.php?route=product/product&product_id=1031 |
| 20476   | VM703004242 | Пресс-фитинги | Varmega VM703004242 42x42 | https://rn-profi.by/index.php?route=product/product&product_id=1031 |
| 20477   | VM703005454 | Пресс-фитинги | Varmega VM703005454 54x54 | https://rn-profi.by/index.php?route=product/product&product_id=1031 |
+---------+-------------+---------------+---------------------------+---------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 7     |
| matched          | 7     |
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
