# Server Artisan Result

- Time: 2026-07-11 15:25:28 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-100kaminov --brand=Blist --sitemap --limit=40 --sleep=300`
- Log file: `storage/logs/server-artisan-ligmet-blist-sitemap-dry.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   b19b6a3..0ebfe4f  main       -> origin/main
Updating b19b6a3..0ebfe4f
Fast-forward
 .github/server-artisan-result.md                 | 14 ++++++++------
 .github/server-artisan-task.json                 |  2 +-
 app/Console/Commands/Enrich100KaminovCommand.php |  3 ++-
 3 files changed, 11 insertions(+), 8 deletions(-)
DRY RUN
Catalog index: 1 brands, 44 products total
  [blist] sample model keys: ATENE G СЕРАЯ BLIST_GREY, BERNA LUX S, BERNA LUX БЕЖЕВАЯ BLIST_BEIGE, BERNA LUX КРАСНАЯ BLIST_RED, BERNA LUX СЕРАЯ BLIST_GREY, B1, MODENA БЕЖЕВАЯ BLIST_BEIGE, MODENA КРАСНАЯ BLIST_RED, MODENA СЕРАЯ BLIST_GREY, NAPOLI, PADOVA E, ROMA E БЕЖЕВАЯ BLIST_BEIGE, ROMA S БЕЖЕВАЯ BLIST_BEIGE, ТРУБА 0 5М СЕРБИЯ BLIST_GREY, ROMA G БЕЖЕВАЯ BLIST_BEIGE, БАКЕЛИТОВАЯ РУЧКА КОД 2943, ВЕРМИКУЛИТ ЗАДНЮЮ СТЕНКУ POLAR, ЗОЛЬНЫЙ ЯЩИК OGANJ КРУГЛЫМ РЕГУЛЯТОРОМ ПОДАЧИ ВОЗДУХА, КОЛОСНИКОВАЯ РЕШЕТКА 160Х295 EKONOMIK LUX, КОЛОСНИКОВАЯ РЕШЕТКА 315X320 КОД 2804 ZAR, КОЛОСНИКОВАЯ РЕШЕТКА 320X338 КОД 3064, КОЛОСНИКОВАЯ РЕШЕТКА 325Х170 ATENE CODE 1273, КРАСКА ROBERLO ДЛЯ АЭРОЗОЛЬ, НАКОНЕЧНИК НИКЕЛИРОВАННЫЙ К ЗОЛЬНОМУ ЯЩИКУ, СТЕКЛО ТЕРМОСТОЙКОЕ 202X172 КОД 2983 2965, СТЕКЛО ТЕРМОСТОЙКОЕ 240X200 КОД 2966, СТЕКЛО ТЕРМОСТОЙКОЕ 270X240 КОД 0669 0890, СТЕКЛО ТЕРМОСТОЙКОЕ 330X160 КОД 2862, СТЕКЛО ТЕРМОСТОЙКОЕ POLAR 350X275, ФИКСАТОР СТЕКЛА …
Could not fetch sitemap index, trying product sitemap directly.
Sitemap diagnostics: maps=1 raw_products=0 brand_matched=0 brand_filter=blist

Sitemap product links: 0

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 0     |
| matched  | 0     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 0     |
| errors   | 0     |
+----------+-------+

```
