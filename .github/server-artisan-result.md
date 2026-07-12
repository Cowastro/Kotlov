# Server Artisan Result

- Time: 2026-07-12 07:47:10 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:sync-ligmet --dry-run --brand=Ferrum --all-categories --examples=40 --link-existing-suggestions --min-suggestion-score=99.9`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   c6ddff4..654aec0  main       -> origin/main
Updating c6ddff4..654aec0
Fast-forward
 .github/server-artisan-result.md           | 57 ++++++++++--------------------
 .github/server-artisan-task.json           |  4 +--
 app/Console/Commands/SyncLigmetCommand.php | 13 +++++++
 3 files changed, 34 insertions(+), 40 deletions(-)
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
+------------------------+--------+
| confidence             | кол-во |
+------------------------+--------+
| exact_supplier_article | 2      |
+------------------------+--------+
Примеры (40):
+---------------+--------+------------------------------------+--------+--------+-----------+------------------+-------------+
| article       | brand  | name                               | опт    | розн   | наличие   | action           | matched_sku |
+---------------+--------+------------------------------------+--------+--------+-----------+------------------+-------------+
| 994553673     | Ferrum | Дымоход 1,0 м (430/0,5 мм) Ф100    | 22.45  | 26.00  | low_stock | create_candidate | —           |
| 994553397     | Ferrum | Дымоход 1,0 м (430/0,8 мм) Ф115    | 41.62  | 48.00  | low_stock | create_candidate | —           |
| 994553481     | Ferrum | Дымоход 1,0 м (430/0,8 мм) Ф150    | 54.53  | 63.00  | low_stock | create_candidate | —           |
| 994553392     | Ferrum | Дымоход 1,0 м (430/1,0мм) Ф115     | 51.51  | 59.00  | in_stock  | create_candidate | —           |
| 994553471     | Ferrum | Дымоход 0,25 м (430/0,8 мм) Ф130   | 16.63  | 19.00  | low_stock | create_candidate | —           |
| 994554586     | Ferrum | Дымоход 0,25 м (430/0,8 мм) Ф180   | 22.47  | 26.00  | low_stock | create_candidate | —           |
| 994553435     | Ferrum | Тройник-Д 90° (430/0,8мм) Ф120     | 43.71  | 57.00  | low_stock | create_candidate | —           |
| 994553678     | Ferrum | Колено угол 90° (430/0,5 мм) Ф100  | 14.36  | 19.00  | in_stock  | create_candidate | —           |
| 994553679     | Ferrum | Колено угол 135° (430/0,5 мм) Ф100 | 12.61  | 16.00  | in_stock  | create_candidate | —           |
| 994553622     | Ferrum | Шибер (430/0,5 мм) Ф100            | 22.91  | 30.00  | low_stock | create_candidate | —           |
| 994553395     | Ferrum | Шибер (430/1,0мм) Ф115             | 36.37  | 47.00  | in_stock  | create_candidate | —           |
| 9945559661    | Ferrum | Адаптер ММ (304/0,8 мм) Ф150       | 19.52  | 25.00  | low_stock | create_candidate | —           |
| 994553988     | Ferrum | Сэндвич 1,0 м (304/0,8мм + нерж.)  | 129.59 | 149.00 | in_stock  | create_candidate | —           |
| 994553370     | Ferrum | Сэндвич 1,0 м (430/0,8мм + нерж.)  | 176.56 | 202.00 | low_stock | create_candidate | —           |
| 994553359     | Ferrum | Сэндвич 0,5 м (430/0,8мм + нерж.)  | 70.31  | 81.00  | low_stock | create_candidate | —           |
| 2002770413246 | Ferrum | Сэндвич-колено 135° (304/0,8мм + н | 132.35 | 172.00 | low_stock | create_candidate | —           |
| 994553420     | Ferrum | Сэндвич-колено 135° (430/0,8мм + н | 69.59  | 91.00  | low_stock | matched          | PS-007.817  |
| 994553537     | Ferrum | Адаптер стартовый (430/0,8 мм + не | 40.41  | 53.00  | low_stock | create_candidate | —           |
| 994553422     | Ferrum | Старт-сэндвич (430/0,8мм + нерж.)  | 40.58  | 53.00  | low_stock | matched          | PS-007.814  |
| 994553360     | Ferrum | Оголовок (430/0,5 + нерж.) Ф115х20 | 41.47  | 54.00  | in_stock  | create_candidate | —           |
| 994553448     | Ferrum | Хомут обжимной (430/0,5 мм) Ф197 ( | 7.63   | 10.00  | in_stock  | create_candidate | —           |
| 994553876     | Ferrum | Хомут обжимной (430/0,5 мм / эмаль | 17.50  | 23.00  | low_stock | create_candidate | —           |
| 2002770413772 | Ferrum | Кронштейн раздвижной №1 (430/1,0 м | 78.91  | 103.00 | low_stock | create_candidate | —           |
| 2002770413484 | Ferrum | Стеновой хомут ( AISI 430/1мм) Ф12 | 21.78  | 28.00  | in_stock  | create_candidate | —           |
| 9945554677    | Ferrum | Штанга для стен. хомута (AISI 430) | 66.22  | 86.00  | low_stock | create_candidate | —           |
| 9945553735    | Ferrum | Штанга для стен. хомута (AISI 430) | 29.65  | 39.00  | low_stock | create_candidate | —           |
| 994553402     | Ferrum | Консоль К4 (430/2 шт) L-500 (1,5 м | 107.23 | 140.00 | low_stock | create_candidate | —           |
| 994553877     | Ferrum | Экран защитный (430/0,5 мм) 580*58 | 0.00   | 0.00   | low_stock | create_candidate | —           |
| 994554976     | Ferrum | Экран защитный (430/0,5 мм) 580*58 | 50.42  | 66.00  | low_stock | create_candidate | —           |
+---------------+--------+------------------------------------+--------+--------+-----------+------------------+-------------+

Запусти с --apply (и --create-new для новых).

```
