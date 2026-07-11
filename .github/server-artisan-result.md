# Server Artisan Result

- Time: 2026-07-11 18:05:59 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-100kaminov --brand=Panadero --pages=40 --limit=80 --dry-run`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   cafa884..5b51da0  main       -> origin/main
Updating cafa884..5b51da0
Fast-forward
 .github/server-artisan-result.md                 | 76 ++++++++++++++++++++----
 .github/server-artisan-task.json                 |  2 +-
 app/Console/Commands/Enrich100KaminovCommand.php |  2 +-
 3 files changed, 68 insertions(+), 12 deletions(-)
DRY RUN
Catalog index: 1 brands, 7 products total
  [panadero] sample model keys: AKITA, MAJA-S, ONIX WALL, OSAKA, OVAL, SUERTE, 101-S

Category: /ps1026-top-pechej-kaminov?sort=position
  [Panadero] Печь-камин Panadero Oval → model:OVAL → pid=17028
    · Страна производитель: Испания
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 131 кг
    · images: 6
  [Panadero] Каминная топка Panadero Hogar 101-S → model:101-S → pid=17041
    · Страна производитель: Испания
    · Вес: 164 кг
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · images: 10
  [Panadero] Печь-камин Panadero Suerte → model:SUERTE → pid=17029
    · Страна производитель: Испания
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 123 кг
    · images: 10
  [Panadero] Печь-камин Panadero Osaka → model:OSAKA → pid=17027
    · Страна производитель: Испания
    · Вес: 133 кг
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · images: 11
  [Panadero] Печь-камин Panadero Maja-S → model:MAJA-S → pid=17025
    · Страна производитель: Испания
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 142 кг
    · images: 5
  [Panadero] Печь-камин Panadero Akita → model:AKITA → pid=17024
    · Страна производитель: Испания
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 133 кг
    · images: 4

Category: /ps1025-top-pechej-dlya?sort=position

Category: /ps1024-top-pechej-dlya?sort=position

Category: /g6149558-kaminy

Category: /g6364208-reshetki-kaminnye-ventilyatsionnye

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 13    |
| matched  | 6     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 0     |
| errors   | 0     |
+----------+-------+

```
