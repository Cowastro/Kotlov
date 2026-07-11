# Server Artisan Result

- Time: 2026-07-11 17:56:45 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-100kaminov --brand=Nordflam --pages=40 --limit=80 --dry-run`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   521bd17..b5eaf43  main       -> origin/main
Updating 521bd17..b5eaf43
Fast-forward
 .github/server-artisan-result.md                 | 53 ++++++++++++++----------
 .github/server-artisan-task.json                 |  2 +-
 app/Console/Commands/Enrich100KaminovCommand.php |  2 +
 3 files changed, 33 insertions(+), 24 deletions(-)
DRY RUN
Catalog index: 1 brands, 10 products total
  [nordflam] sample model keys: CARINI, FROVI, PALERMO, PALESTRO, TORIA, VERA CAPPUCCINO, ETNA P, РЕШЕТКА AERO 90 600 400 L, РЕШЕТКА AERO 90 600, РЕШЕТКА AERO 90 800

Category: /ps1026-top-pechej-kaminov?sort=position
  [Nordflam] Печь-камин Nordflam Toria → model:TORIA → pid=17022
    · Страна производитель: Польша
    · Водяной контур: Нет
    · Вес: 175 кг
    · Ширина: 773 мм
    · images: 3
  [Nordflam] Печь-камин Nordflam Palermo → model:PALERMO → pid=17020
    · Страна производитель: Польша
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 116 кг
    · images: 4

Category: /ps1025-top-pechej-dlya?sort=position
  [Nordflam] Печь-камин Nordflam Palestro Patine → model:PALESTRO → pid=17021
    · Страна производитель: Польша
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 58 кг
    · images: 7

Category: /ps1024-top-pechej-dlya?sort=position

Category: /g6149558-kaminy
  [Nordflam] Каминная топка Nordflam Etna Right → model:ETNA P → pid=17040
    · Страна производитель: Польша
    · Водяной контур: Нет
    · Вес: 81 кг
    · Подвод воздуха извне: Опция
    · images: 7

Category: /g6364208-reshetki-kaminnye-ventilyatsionnye

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 8     |
| matched  | 4     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 0     |
| errors   | 0     |
+----------+-------+

```
