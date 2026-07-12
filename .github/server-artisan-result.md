# Server Artisan Result

- Time: 2026-07-12 11:01:49 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --category=Пресс-фитинги --rn-profi-fallback --rn-profi-search-limit=30 --limit=30`
- Log file: `storage/logs/varmega-official-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7d5ed0b..525af67  main       -> origin/main
Updating 7d5ed0b..525af67
Fast-forward
 .github/server-artisan-result.md                   |  61 ++++++++++---
 .github/server-artisan-task.json                   |   4 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    | 100 +++++++++++++++++++++
 3 files changed, 152 insertions(+), 13 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 30.
+---------+-------------+---------------+-----------------------------------+-----------------------------------------------------------------------+
| product | article     | category      | name                              | official_url                                                          |
+---------+-------------+---------------+-----------------------------------+-----------------------------------------------------------------------+
| 20425   | 2171610     | Пресс-фитинги | Varmega 2171610 3/4"EK*16х2.2     | https://rn-profi.by/index.php?route=product/search&search=2171610     |
| 20426   | 2172010     | Пресс-фитинги | Varmega 2172010 3/4"EK*20х2.8     | https://rn-profi.by/index.php?route=product/search&search=2172010     |
| 20427   | VM52501     | Пресс-фитинги | Varmega VM52501 16х2.2/250        | https://rn-profi.by/index.php?route=product/search&search=VM52501     |
| 20428   | VM52603     | Пресс-фитинги | Varmega VM52603 20х2.8-16х2.2/250 | https://rn-profi.by/index.php?route=product/search&search=VM52603     |
| 20429   | VM52604     | Пресс-фитинги | Varmega VM52604 16х2.2-20х2.8/250 | https://rn-profi.by/index.php?route=product/search&search=VM52604     |
| 20456   | VM701001515 | Пресс-фитинги | Varmega VM701001515 15x15         | https://rn-profi.by/index.php?route=product/search&search=VM701001515 |
| 20457   | VM701001818 | Пресс-фитинги | Varmega VM701001818 18x18         | https://rn-profi.by/index.php?route=product/search&search=VM701001818 |
| 20458   | VM701002222 | Пресс-фитинги | Varmega VM701002222 22x22         | https://rn-profi.by/index.php?route=product/search&search=VM701002222 |
| 20459   | VM701002828 | Пресс-фитинги | Varmega VM701002828 28x28         | https://rn-profi.by/index.php?route=product/search&search=VM701002828 |
| 20460   | VM701003535 | Пресс-фитинги | Varmega VM701003535 35x35         | https://rn-profi.by/index.php?route=product/search&search=VM701003535 |
| 20461   | VM701004242 | Пресс-фитинги | Varmega VM701004242 42x42         | https://rn-profi.by/index.php?route=product/search&search=VM701004242 |
| 20462   | VM701005454 | Пресс-фитинги | Varmega VM701005454 54x54         | https://rn-profi.by/index.php?route=product/search&search=VM701005454 |
| 20463   | VM702001815 | Пресс-фитинги | Varmega VM702001815 18x15         | https://rn-profi.by/index.php?route=product/search&search=VM702001815 |
| 20464   | VM702002215 | Пресс-фитинги | Varmega VM702002215 22x15         | https://rn-profi.by/index.php?route=product/search&search=VM702002215 |
| 20465   | VM702002218 | Пресс-фитинги | Varmega VM702002218 22x18         | https://rn-profi.by/index.php?route=product/search&search=VM702002218 |
| 20466   | VM702002815 | Пресс-фитинги | Varmega VM702002815 28x15         | https://rn-profi.by/index.php?route=product/search&search=VM702002815 |
| 20467   | VM702002822 | Пресс-фитинги | Varmega VM702002822 28x22         | https://rn-profi.by/index.php?route=product/search&search=VM702002822 |
| 20468   | VM702003528 | Пресс-фитинги | Varmega VM702003528 35x28         | https://rn-profi.by/index.php?route=product/search&search=VM702003528 |
| 20469   | VM702004235 | Пресс-фитинги | Varmega VM702004235 42x35         | https://rn-profi.by/index.php?route=product/search&search=VM702004235 |
| 20470   | VM702005442 | Пресс-фитинги | Varmega VM702005442 54x42         | https://rn-profi.by/index.php?route=product/search&search=VM702005442 |
+---------+-------------+---------------+-----------------------------------+-----------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 30    |
| matched          | 30    |
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
