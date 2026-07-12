# Server Artisan Result

- Time: 2026-07-12 07:22:50 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:sync-ligmet --dry-run --brand=Ferrum --all-categories`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   6b8b3fd..7aee603  main       -> origin/main
Updating 6b8b3fd..7aee603
Fast-forward
 .github/server-artisan-result.md           | 128 +++--------------------------
 .github/server-artisan-task.json           |   4 +-
 app/Console/Commands/SyncLigmetCommand.php |   1 +
 3 files changed, 13 insertions(+), 120 deletions(-)
DRY RUN: database will not be changed.
Using latest Ligmet workbook from Drive folder: https://docs.google.com/spreadsheets/d/1KIhK4gt-FoD4HZMDYhDgLLsRnHIhg7kM/edit?rtpof=true&sd=true
Parsed 511 product rows for requested brands

+------------------+--------+
| метрика          | кол-во |
+------------------+--------+
| строк (товары)   | 29     |
| matched          | 2      |
| create_candidate | 27     |
+------------------+--------+
По брендам:
+--------+-------+
| бренд  | строк |
+--------+-------+
| Ferrum | 29    |
+--------+-------+
По категориям (для новых):
+--------+-----------+-------+
| cat_id | категория | строк |
+--------+-----------+-------+
| 78     | Дымоходы  | 29    |
+--------+-----------+-------+
Матчинг по уверенности:
+-------------+--------+
| confidence  | кол-во |
+-------------+--------+
| brand_model | 2      |
+-------------+--------+
Примеры (12):
+------------+--------+------------------------------------+-------+-------+-----------+------------------+-------------+
| article    | brand  | name                               | опт   | розн  | наличие   | action           | matched_sku |
+------------+--------+------------------------------------+-------+-------+-----------+------------------+-------------+
| 994553673  | Ferrum | Дымоход 1,0 м (430/0,5 мм) Ф100    | 22.45 | 26.00 | low_stock | create_candidate | —           |
| 994553397  | Ferrum | Дымоход 1,0 м (430/0,8 мм) Ф115    | 41.62 | 48.00 | low_stock | create_candidate | —           |
| 994553481  | Ferrum | Дымоход 1,0 м (430/0,8 мм) Ф150    | 54.53 | 63.00 | low_stock | create_candidate | —           |
| 994553392  | Ferrum | Дымоход 1,0 м (430/1,0мм) Ф115     | 51.51 | 59.00 | in_stock  | create_candidate | —           |
| 994553471  | Ferrum | Дымоход 0,25 м (430/0,8 мм) Ф130   | 16.63 | 19.00 | low_stock | create_candidate | —           |
| 994554586  | Ferrum | Дымоход 0,25 м (430/0,8 мм) Ф180   | 22.47 | 26.00 | low_stock | create_candidate | —           |
| 994553435  | Ferrum | Тройник-Д 90° (430/0,8мм) Ф120     | 43.71 | 57.00 | low_stock | create_candidate | —           |
| 994553678  | Ferrum | Колено угол 90° (430/0,5 мм) Ф100  | 14.36 | 19.00 | in_stock  | create_candidate | —           |
| 994553679  | Ferrum | Колено угол 135° (430/0,5 мм) Ф100 | 12.61 | 16.00 | in_stock  | create_candidate | —           |
| 994553622  | Ferrum | Шибер (430/0,5 мм) Ф100            | 22.91 | 30.00 | low_stock | create_candidate | —           |
| 994553395  | Ferrum | Шибер (430/1,0мм) Ф115             | 36.37 | 47.00 | in_stock  | create_candidate | —           |
| 9945559661 | Ferrum | Адаптер ММ (304/0,8 мм) Ф150       | 19.52 | 25.00 | low_stock | create_candidate | —           |
+------------+--------+------------------------------------+-------+-------+-----------+------------------+-------------+

Запусти с --apply (и --create-new для новых).

```
