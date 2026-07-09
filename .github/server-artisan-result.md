# Server Artisan Result

- Time: 2026-07-09 18:21:10 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-100kaminov --brand=Blist --source-url=/ --pages=3 --limit=30 --dry-run`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN
Catalog index: 1 brands, 39 products total
  [blist] sample model keys: ATENE G, BERNA LUX S, BERNA LUX, B1, MODENA, NAPOLI, PADOVA E, ROMA E, ROMA S, ТРУБА 0 5М СЕРБИЯ, ROMA G, БАКЕЛИТОВАЯ РУЧКА КОД 2943, ВЕРМИКУЛИТ ЗАДНЮЮ СТЕНКУ POLAR, ЗОЛЬНЫЙ ЯЩИК OGANJ КРУГЛЫМ РЕГУЛЯТОРОМ ПОДАЧИ ВОЗДУХА, КОЛОСНИКОВАЯ РЕШЕТКА 160Х295 EKONOMIK LUX, КОЛОСНИКОВАЯ РЕШЕТКА 315X320 КОД 2804 ZAR, КОЛОСНИКОВАЯ РЕШЕТКА 320X338 КОД 3064, КОЛОСНИКОВАЯ РЕШЕТКА 325Х170 ATENE CODE 1273, КРАСКА ROBERLO ДЛЯ АЭРОЗОЛЬ, НАКОНЕЧНИК НИКЕЛИРОВАННЫЙ К ЗОЛЬНОМУ ЯЩИКУ, СТЕКЛО ТЕРМОСТОЙКОЕ 202X172 КОД 2983 2965, СТЕКЛО ТЕРМОСТОЙКОЕ 240X200 КОД 2966, СТЕКЛО ТЕРМОСТОЙКОЕ 270X240 КОД 0669 0890, СТЕКЛО ТЕРМОСТОЙКОЕ 330X160 КОД 2862, СТЕКЛО ТЕРМОСТОЙКОЕ POLAR 350X275, ФИКСАТОР СТЕКЛА, КРЫШКА MODENA ZAR КОД 003584, ШАМОТНЫЙ КИРПИЧ B1 145Х340ММ КОД 3681 1199, ШАМОТНЫЙ КИРПИЧ B1N 155Х340ММ КОД 3677 4108, ШАМОТНЫЙ КИРПИЧ B2 135Х370ММ КОД 2879 …

Category: /
  [MBS] Печь-камин MBS Vesta Eco бордовая → model:VESTA ECO БОРДОВАЯ → NO MATCH
  [Panadero] Печь-камин Panadero Osaka → model:OSAKA → NO MATCH
  [MBS] Печь-камин MBS Olymp кремовая → model:OLYMP → NO MATCH
  [Blist] Печь-камин Blist Basel → model:BASEL → NO MATCH

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 8     |
| matched  | 0     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 4     |
| errors   | 0     |
+----------+-------+

```
