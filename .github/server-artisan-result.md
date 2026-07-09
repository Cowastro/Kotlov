# Server Artisan Result

- Time: 2026-07-09 18:24:49 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-100kaminov --brand=Blist --source-url=/g768157-pechi-kaminy --pages=3 --limit=30 --dry-run`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN
Catalog index: 1 brands, 39 products total
  [blist] sample model keys: ATENE G, BERNA LUX S, BERNA LUX, B1, MODENA, NAPOLI, PADOVA E, ROMA E, ROMA S, ТРУБА 0 5М СЕРБИЯ, ROMA G, БАКЕЛИТОВАЯ РУЧКА КОД 2943, ВЕРМИКУЛИТ ЗАДНЮЮ СТЕНКУ POLAR, ЗОЛЬНЫЙ ЯЩИК OGANJ КРУГЛЫМ РЕГУЛЯТОРОМ ПОДАЧИ ВОЗДУХА, КОЛОСНИКОВАЯ РЕШЕТКА 160Х295 EKONOMIK LUX, КОЛОСНИКОВАЯ РЕШЕТКА 315X320 КОД 2804 ZAR, КОЛОСНИКОВАЯ РЕШЕТКА 320X338 КОД 3064, КОЛОСНИКОВАЯ РЕШЕТКА 325Х170 ATENE CODE 1273, КРАСКА ROBERLO ДЛЯ АЭРОЗОЛЬ, НАКОНЕЧНИК НИКЕЛИРОВАННЫЙ К ЗОЛЬНОМУ ЯЩИКУ, СТЕКЛО ТЕРМОСТОЙКОЕ 202X172 КОД 2983 2965, СТЕКЛО ТЕРМОСТОЙКОЕ 240X200 КОД 2966, СТЕКЛО ТЕРМОСТОЙКОЕ 270X240 КОД 0669 0890, СТЕКЛО ТЕРМОСТОЙКОЕ 330X160 КОД 2862, СТЕКЛО ТЕРМОСТОЙКОЕ POLAR 350X275, ФИКСАТОР СТЕКЛА, КРЫШКА MODENA ZAR КОД 003584, ШАМОТНЫЙ КИРПИЧ B1 145Х340ММ КОД 3681 1199, ШАМОТНЫЙ КИРПИЧ B1N 155Х340ММ КОД 3677 4108, ШАМОТНЫЙ КИРПИЧ B2 135Х370ММ КОД 2879 …

Category: /g768157-pechi-kaminy
  [Blist] Печь-камин Blist Ambasador R бежевая с д → model:AMBASADOR R → NO MATCH
  [Blist] Печь-камин Blist Ekonomik Lux бежевая → model:EKONOMIK LUX → NO MATCH
  [Nordflam] Печь-камин Nordflam Brema → model:BREMA → NO MATCH
  [Kratki] Печь-камин Kratki BJORN → model:BJORN → NO MATCH
  [Nordflam] Печь-камин Nordflam Palermo → model:PALERMO → NO MATCH
  [Nordflam] Печь-камин Nordflam Palestro Patine → model:PALESTRO → NO MATCH
  [MBS] Плита на дровах MBS Thermo Magnum S (с в → model:THERMO MAGNUM S КОНТУРОМ → NO MATCH
  [Blist] Печь-камин Blist B Max 2 → model:B MAX 2 → NO MATCH
  [Blist] Печь-камин Blist Berna Lux S → model:BERNA LUX S → pid=16990
    · Страна производитель: Сербия
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 31 кг
    · images: 9
  [Panadero] Печь-камин Panadero Iris → model:IRIS → NO MATCH
  [Blist] Печь-камин Blist Modena красная → model:MODENA → pid=16997
    · Страна производитель: Сербия
    · Водяной контур: Нет
    · Подключение к дымоходу: Верхнее
    · Вес: 64 кг
    · images: 12
  [Kratki] Печь-камин Kratki RUNA BLACK → model:RUNA → NO MATCH
  [MBS] Печь-камин MBS Olympia черная → model:OLYMPIA → NO MATCH
  [Invicta] Печь-камин Invicta Itaya → model:ITAYA → NO MATCH

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 28    |
| matched  | 2     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 12    |
| errors   | 0     |
+----------+-------+

```
