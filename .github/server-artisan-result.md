# Server Artisan Result

- Time: 2026-07-11 15:02:13 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-specs-rusklimat --active-only --brand=Royal --limit=12 --sleep=300`
- Log file: `storage/logs/server-artisan-rusklimat-royal-specs-dry.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   ec8d876..0864876  main       -> origin/main
Updating ec8d876..0864876
Fast-forward
 .github/server-artisan-result.md | 239 +++++++++++++++++++++++++++++++++++----
 .github/server-artisan-task.json |   6 +-
 2 files changed, 217 insertions(+), 28 deletions(-)
DRY RUN: nothing will be written.

Products without specs: 4 (processing 4, offset 0)

id=14324 Royal Thermo ТЭН ESH 2,0 кВт для бойлера 1 1/2"
  page: https://b2b.rusklimat.com/catalog/product/boyler-kosvennogo-nagreva-royal-thermo
  scraped specs: 6
    · Фото: Наименование
    · Бойлер косвенного нагрева Roya: НС-1728774
    · Бойлер косвенного нагрева Roya: НС-1728777
    · Бойлер косвенного нагрева Roya: НС-1728779
  [dry-run] would save: specs

id=16071 Трап душевой Royal Thermo с сухим затвором Compact Line 
  page: https://b2b.rusklimat.com/catalog/product/lotok-dushevoy-royal-thermo-s-sukhim-z
  scraped specs: 4
    · Фото: Наименование
    · Лоток душевой Royal Thermo с с: НС-1650432
    · Лоток душевой Royal Thermo с с: НС-1650431
    · Лоток душевой Royal Thermo с с: НС-1650430
  [dry-run] would save: specs

id=16072 Трап душевой Royal Thermo с сухим затвором Compact Line 
  page: https://b2b.rusklimat.com/catalog/product/lotok_dushevoy_royal_thermo_s_sukhim_z
  scraped specs: 4
    · Фото: Наименование
    · Лоток душевой Royal Thermo с с: НС-1650432
    · Лоток душевой Royal Thermo с с: НС-1650431
    · Лоток душевой Royal Thermo с с: НС-1650429
  [dry-run] would save: specs

id=16073 Трап душевой Royal Thermo с сухим затвором Compact Line 
  page: https://b2b.rusklimat.com/catalog/product/lotok-dushevoy-royal-thermo-s-sukhim-z
  scraped specs: 4
    · Фото: Наименование
    · Лоток душевой Royal Thermo с с: НС-1650432
    · Лоток душевой Royal Thermo с с: НС-1650429
    · Лоток душевой Royal Thermo с с: НС-1650430
  [dry-run] would save: specs

+-------------+-------+
| metric      | count |
+-------------+-------+
| processed   | 4     |
| page_found  | 4     |
| specs_saved | 4     |
| short_saved | 0     |
| not_found   | 0     |
| errors      | 0     |
+-------------+-------+

```
