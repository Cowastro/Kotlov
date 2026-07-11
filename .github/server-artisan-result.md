# Server Artisan Result

- Time: 2026-07-11 15:56:02 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-100kaminov --apply --brand=Blist --urls=https://100kaminov.by/p132742174-pech-kamin-blist.html,https://100kaminov.by/p132742452-pech-kamin-blist.html,https://100kaminov.by/p132742506-pech-kamin-blist.html,https://100kaminov.by/p132742546-pech-kamin-blist.html,https://100kaminov.by/p142087554-pech-kamin-blist.html --limit=5 --sleep=300`
- Log file: `storage/logs/server-artisan-ligmet-blist-tail-apply.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   b562b0e..dba3452  main       -> origin/main
Updating b562b0e..dba3452
Fast-forward
 .github/server-artisan-result.md | 135 +++++++++++++++++++++++++++------------
 .github/server-artisan-task.json |   8 +--
 2 files changed, 99 insertions(+), 44 deletions(-)
APPLY
Catalog index: 1 brands, 44 products total

Direct product links: 5
  [Blist] Печь-камин Blist Berna Lux красная → model:BERNA LUX КРАСНАЯ BLIST_RED → pid=16992
  [Blist] Печь-камин Blist Modena бежевая → model:MODENA БЕЖЕВАЯ BLIST_BEIGE → pid=16995
  [Blist] Печь-камин Blist Modena серая → model:MODENA СЕРАЯ BLIST_GREY → pid=16997
  [Blist] Печь-камин Blist Roma G красная с духовы → model:ROMA G КРАСНАЯ ДУХОВЫМ ШКАФОМ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Milano E с водяным конт → model:MILANO E КОНТУРОМ И БЕЖЕВАЯ BLIST_BEIGE → NO MATCH

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 0     |
| matched  | 3     |
| enriched | 3     |
| images   | 0     |
| specs    | 0     |
| ai_done  | 0     |
| skipped  | 2     |
| errors   | 0     |
+----------+-------+

```
