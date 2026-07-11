# Server Artisan Result

- Time: 2026-07-11 15:46:37 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-100kaminov --brand=Blist --urls=https://100kaminov.by/p124137348-pech-kamin-blist.html,https://100kaminov.by/p124137364-pech-kamin-blist.html,https://100kaminov.by/p124137382-pech-kamin-blist.html,https://100kaminov.by/p124137478-pech-kamin-blist.html,https://100kaminov.by/p126748755-pech-kamin-blist.html,https://100kaminov.by/p126748774-pech-kamin-blist.html,https://100kaminov.by/p126748832-pech-kamin-blist.html,https://100kaminov.by/p126748852-pech-kamin-blist.html,https://100kaminov.by/p126748894-pech-kamin-blist.html,https://100kaminov.by/p126748918-pech-kamin-blist.html,https://100kaminov.by/p126749660-pech-kamin-blist.html,https://100kaminov.by/p127020214-pech-kamin-blist.html,https://100kaminov.by/p127164480-pech-kamin-blist.html,https://100kaminov.by/p127164619-pech-kamin-blist.html,https://100kaminov.by/p127164648-pech-kamin-blist.html,https://100kaminov.by/p127164662-pech-kamin-blist.html,https://100kaminov.by/p127287685-pech-kamin-blist.html,https://100kaminov.by/p127289906-pech-kamin-blist.html,https://100kaminov.by/p127290325-pech-kamin-blist.html,https://100kaminov.by/p127292448-pech-kamin-blist.html,https://100kaminov.by/p127293349-pech-kamin-blist.html,https://100kaminov.by/p127293382-pech-kamin-blist.html,https://100kaminov.by/p127293417-pech-kamin-blist.html,https://100kaminov.by/p127293792-pech-kamin-blist.html,https://100kaminov.by/p127293846-pech-kamin-blist.html --limit=25 --sleep=300`
- Log file: `storage/logs/server-artisan-ligmet-blist-direct-dry.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7bf367e..331124c  main       -> origin/main
Updating 7bf367e..331124c
Fast-forward
 .github/server-artisan-result.md                 | 46 +++++++++++++++++-------
 .github/server-artisan-task.json                 |  2 +-
 app/Console/Commands/Enrich100KaminovCommand.php | 28 ++++++++++-----
 3 files changed, 54 insertions(+), 22 deletions(-)
DRY RUN
Catalog index: 1 brands, 44 products total
  [blist] sample model keys: ATENE G СЕРАЯ BLIST_GREY, BERNA LUX S, BERNA LUX БЕЖЕВАЯ BLIST_BEIGE, BERNA LUX КРАСНАЯ BLIST_RED, BERNA LUX СЕРАЯ BLIST_GREY, B1, MODENA БЕЖЕВАЯ BLIST_BEIGE, MODENA КРАСНАЯ BLIST_RED, MODENA СЕРАЯ BLIST_GREY, NAPOLI, PADOVA E, ROMA E БЕЖЕВАЯ BLIST_BEIGE, ROMA S БЕЖЕВАЯ BLIST_BEIGE, ТРУБА 0 5М СЕРБИЯ BLIST_GREY, ROMA G БЕЖЕВАЯ BLIST_BEIGE, БАКЕЛИТОВАЯ РУЧКА КОД 2943, ВЕРМИКУЛИТ ЗАДНЮЮ СТЕНКУ POLAR, ЗОЛЬНЫЙ ЯЩИК OGANJ КРУГЛЫМ РЕГУЛЯТОРОМ ПОДАЧИ ВОЗДУХА, КОЛОСНИКОВАЯ РЕШЕТКА 160Х295 EKONOMIK LUX, КОЛОСНИКОВАЯ РЕШЕТКА 315X320 КОД 2804 ZAR, КОЛОСНИКОВАЯ РЕШЕТКА 320X338 КОД 3064, КОЛОСНИКОВАЯ РЕШЕТКА 325Х170 ATENE CODE 1273, КРАСКА ROBERLO ДЛЯ АЭРОЗОЛЬ, НАКОНЕЧНИК НИКЕЛИРОВАННЫЙ К ЗОЛЬНОМУ ЯЩИКУ, СТЕКЛО ТЕРМОСТОЙКОЕ 202X172 КОД 2983 2965, СТЕКЛО ТЕРМОСТОЙКОЕ 240X200 КОД 2966, СТЕКЛО ТЕРМОСТОЙКОЕ 270X240 КОД 0669 0890, СТЕКЛО ТЕРМОСТОЙКОЕ 330X160 КОД 2862, СТЕКЛО ТЕРМОСТОЙКОЕ POLAR 350X275, ФИКСАТОР СТЕКЛА …

Direct product links: 25
  [Blist] Печь-камин Blist Ekonomik Lux бежевая → model:EKONOMIK LUX БЕЖЕВАЯ BLIST_BEIGE → NO MATCH
  [Blist] Печь-камин Blist Atene G красная → model:ATENE G КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Modena красная → model:MODENA КРАСНАЯ BLIST_RED → pid=16996
    · Страна производитель: Сербия
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 64 кг
    · images: 12
  [Blist] Печь-камин Blist Atene G ceramic красная → model:ATENE G КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Berna Lux S → model:BERNA LUX S → pid=16990
    · Страна производитель: Сербия
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 31 кг
    · images: 9
  [Blist] Печь-камин Blist Atene S серая → model:ATENE S СЕРАЯ BLIST_GREY → NO MATCH
  [Blist] Печь-камин Blist Atene S ceramic бежевый → model:ATENE S БЕЖЕВЫЙ BLIST_BEIGE → NO MATCH
  [Blist] Печь-камин Blist Ambasador LM N красный → model:AMBASADOR LM N КРАСНЫЙ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Vienna красная → model:VIENNA КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist B Max 1 → model:B MAX 1 → NO MATCH
  [Blist] Печь-камин Blist B Max 2 → model:B MAX 2 → NO MATCH
  [Blist] Печь-камин Blist Berna Lux серая → model:BERNA LUX СЕРАЯ BLIST_GREY → pid=16993
    · Страна производитель: Сербия
    · Назначение печи: Отопительно-варочная
    · Подключение к дымоходу: Заднее
    · Водяной контур: Нет
    · images: 11
  [Blist] Печь-камин Blist B1 → model:B1 → pid=16994
    · Страна производитель: Сербия
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 39 кг
    · images: 5
  [Blist] Печь-камин Blist Basel → model:BASEL → NO MATCH
  [Blist] Печь-камин Blist B10 → model:B10 → NO MATCH
  [Blist] Печь-камин Blist Padova → model:PADOVA → NO MATCH
  [Blist] Печь-камин Blist Roma S красная (с духов → model:ROMA S КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Ambasador R бежевая с д → model:AMBASADOR R БЕЖЕВАЯ BLIST_BEIGE → NO MATCH
  [Blist] Печь-камин Blist Napoli с духовым шкафом → model:NAPOLI ДУХОВЫМ ШКАФОМ → pid=16998
    · Страна производитель: Сербия
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 94 кг
    · images: 5
  [Blist] Печь-камин Blist BRM серая (с духовкой) → model:BRM СЕРАЯ BLIST_GREY → NO MATCH
  [Blist] Печь-камин Blist B2 E с водяным контуром → model:B2 E КОНТУРОМ → NO MATCH
  [Blist] Печь-камин Blist Padova E с водяным конт → model:PADOVA E КОНТУРОМ → pid=16999
    · Страна производитель: Сербия
    · Водяной контур: Да
    · Подключение к дымоходу: Верхнее
    · Вес: 81 кг
    · images: 4
  [Blist] Печь-камин Blist Milano E с теплообменни → model:MILANO E ТЕПЛООБМЕННИКОМ И → NO MATCH
  [Blist] Печь-камин Blist Roma E бежевая (с водян → model:ROMA E БЕЖЕВАЯ КОНТУРОМ BLIST_BEIGE → pid=17000
    · Страна производитель: Сербия
    · Водяной контур: Да
    · Подключение к дымоходу: Верхнее
    · Вес: 90 кг
    · images: 6
  [Blist] Печь-камин Blist B MAX E с водяным конту → model:B MAX E КОНТУРОМ → NO MATCH

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 0     |
| matched  | 7     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 18    |
| errors   | 0     |
+----------+-------+

```
