# Server Artisan Result

- Time: 2026-07-09 15:08:18 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-ligmet-extra --base-url=https://kaminbel.by --source-url=/product/pechi-kaminy/kratki/,/product/kaminnye-topki/kratki/ --brand=Kratki --pages=20 --dry-run`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN
Source: https://kaminbel.by
Catalog: 1 brands, 111 products
  [kratki] keys: ДВЕРЬ ZUZIA, ДВЕРЬ MAJA, BJORN, RUNA, TOFA, KOZA K12, FLOKI L L, FLOKI L P, FLOKI L PF, FLOKI M P, РЕШЕТКА B 11Х11, РЕШЕТКА B 11Х17, РЕШЕТКА B 17Х17, РЕШЕТКА B 17Х30, РЕШЕТКА B 17Х37, РЕШЕТКА B 17Х49, РЕШЕТКА BX 17Х17 ЖАЛЮЗИ, РЕШЕТКА BX 17Х30 ЖАЛЮЗИ, РЕШЕТКА BX 17Х37 ЖАЛЮЗИ, РЕШЕТКА BX 17Х49 ЖАЛЮЗИ, РЕШЕТКА C 11Х17, РЕШЕТКА C 17Х30, РЕШЕТКА C 17Х37, РЕШЕТКА C 17Х49, РЕШЕТКА CX 17Х17 ЖАЛЮЗИ
Sitemaps found: 1

Collecting: /product/pechi-kaminy/kratki/
  sitemap: no links — falling back to HTML crawl
  HTML page 1: no new links, stopping.

Collecting: /product/kaminnye-topki/kratki/
  sitemap: no links — falling back to HTML crawl
  HTML page 1: no new links, stopping.

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
