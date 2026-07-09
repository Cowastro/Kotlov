# Server Artisan Result

- Time: 2026-07-09 15:18:02 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-ligmet-extra --base-url=https://ochag.by --source-url=/kaminy/pechi-kaminy/kratki/ --brand=Kratki --pages=20 --dry-run`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN
Source: https://ochag.by
Catalog: 1 brands, 111 products
  [kratki] keys: ДВЕРЬ ZUZIA, ДВЕРЬ MAJA, BJORN, RUNA, TOFA, KOZA K12, FLOKI L L, FLOKI L P, FLOKI L PF, FLOKI M P, РЕШЕТКА B 11Х11, РЕШЕТКА B 11Х17, РЕШЕТКА B 17Х17, РЕШЕТКА B 17Х30, РЕШЕТКА B 17Х37, РЕШЕТКА B 17Х49, РЕШЕТКА BX 17Х17 ЖАЛЮЗИ, РЕШЕТКА BX 17Х30 ЖАЛЮЗИ, РЕШЕТКА BX 17Х37 ЖАЛЮЗИ, РЕШЕТКА BX 17Х49 ЖАЛЮЗИ, РЕШЕТКА C 11Х17, РЕШЕТКА C 17Х30, РЕШЕТКА C 17Х37, РЕШЕТКА C 17Х49, РЕШЕТКА CX 17Х17 ЖАЛЮЗИ
Sitemaps found: 1

Collecting: /kaminy/pechi-kaminy/kratki/
  sitemap: no links — falling back to HTML crawl
  HTML page 1: 124 links
  HTML page 2: no new links, stopping.
  [Ferguss] Печи-камины Ferguss → ПЕЧИ КАМИНЫ → NO MATCH
  [FireWay] Печи-камины Fireway → ПЕЧИ КАМИНЫ → NO MATCH
  [Invicta] Печи-камины Invicta → ПЕЧИ КАМИНЫ → NO MATCH
  [Nordflam] Печи-камины Nordflam → ПЕЧИ КАМИНЫ → NO MATCH
  [FireWay] Печь-Камин Fireway Dacha → DACHA → NO MATCH
  [FireWay] Печь-Камин Fireway Solo → SOLO → NO MATCH
  [Invicta] Печь-Камин Invicta Remilly → REMILLY → NO MATCH

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 7     |
| matched  | 0     |
| enriched | 0     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 7     |
| errors   | 0     |
+----------+-------+

```
