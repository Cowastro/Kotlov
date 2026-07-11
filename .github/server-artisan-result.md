# Server Artisan Result

- Time: 2026-07-11 17:50:03 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-100kaminov --brand=MBS --pages=40 --limit=80 --dry-run`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   3f2d0d7..eb5a146  main       -> origin/main
Updating 3f2d0d7..eb5a146
Fast-forward
 .github/server-artisan-result.md | 103 ++++-----------------------------------
 .github/server-artisan-task.json |   8 +--
 2 files changed, 14 insertions(+), 97 deletions(-)
DRY RUN
Catalog index: 1 brands, 6 products total
  [mbs] sample model keys: OLYMPIA L, OLYMPIA S, OLYMP L, OLYMP PLUS L, ТВЕРДОМ ТОПЛИВЕ THERMO MAGNUM 4D D S ПРАВЫЙ, ТВЕРДОМ ТОПЛИВЕ THERMO MAGNUM 4D L S ЛЕВЫЙ

Category: /ps1026-top-pechej-kaminov?sort=position

Category: /ps1025-top-pechej-dlya?sort=position
  [MBS] Печь-камин MBS Olymp кремовая → model:OLYMP → NO MATCH
  [MBS] Печь-камин MBS Olympia черная → model:OLYMPIA → NO MATCH
  [MBS] Плита на дровах MBS Magnum S (с камнем) → model:MAGNUM S → NO MATCH
  [MBS] Печь-камин MBS Happy кремовый → model:HAPPY → NO MATCH
  [MBS] Плита на дровах MBS Trend (с крышкой) кр → model:TREND → NO MATCH
  [MBS] Печь-камин MBS Olympia S (с камнем) → model:OLYMPIA S → pid=17013
    · Страна производитель: Сербия
    · Вес: 185 кг
    · Водяной контур: Нет
    · Подключение к дымоходу: Заднее
    · images: 9
  [MBS] Плита на дровах MBS Thermo Magnum S (с в → model:THERMO MAGNUM S КОНТУРОМ → NO MATCH

Category: /ps1024-top-pechej-dlya?sort=position

Category: /g6149558-kaminy

Category: /g6364208-reshetki-kaminnye-ventilyatsionnye

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 14    |
| matched  | 1     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 6     |
| errors   | 0     |
+----------+-------+

```
