# Server Artisan Result

- Time: 2026-07-09 18:45:13 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-100kaminov --brand=Blist --source-url=/g768157-pechi-kaminy --pages=3 --limit=30 --apply`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
APPLY
Catalog index: 1 brands, 44 products total

Category: /g768157-pechi-kaminy
  [Blist] Печь-камин Blist Ambasador R бежевая с д → model:AMBASADOR R БЕЖЕВАЯ BLIST_BEIGE → NO MATCH
  [Blist] Печь-камин Blist Ekonomik Lux бежевая → model:EKONOMIK LUX БЕЖЕВАЯ BLIST_BEIGE → NO MATCH
  [Nordflam] Печь-камин Nordflam Brema → model:BREMA → NO MATCH
  [Kratki] Печь-камин Kratki BJORN → model:BJORN → NO MATCH
  [Nordflam] Печь-камин Nordflam Palermo → model:PALERMO → NO MATCH
  [Nordflam] Печь-камин Nordflam Palestro Patine → model:PALESTRO → NO MATCH
  [MBS] Плита на дровах MBS Thermo Magnum S (с в → model:THERMO MAGNUM S КОНТУРОМ → NO MATCH
  [Blist] Печь-камин Blist B Max 2 → model:B MAX 2 → NO MATCH
  [Blist] Печь-камин Blist Berna Lux S → model:BERNA LUX S → pid=16990
  [Panadero] Печь-камин Panadero Iris → model:IRIS → NO MATCH
  [Blist] Печь-камин Blist Modena красная → model:MODENA КРАСНАЯ BLIST_RED → pid=16996
  [Kratki] Печь-камин Kratki RUNA BLACK → model:RUNA → NO MATCH
  [MBS] Печь-камин MBS Olympia черная → model:OLYMPIA → NO MATCH
  [Invicta] Печь-камин Invicta Itaya → model:ITAYA → NO MATCH

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 28    |
| matched  | 2     |
| enriched | 2     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 12    |
| errors   | 0     |
+----------+-------+

```
