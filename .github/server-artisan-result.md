# Server Artisan Result

- Time: 2026-07-11 15:49:47 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-100kaminov --apply --brand=Blist --urls=https://100kaminov.by/p124137348-pech-kamin-blist.html,https://100kaminov.by/p124137364-pech-kamin-blist.html,https://100kaminov.by/p124137382-pech-kamin-blist.html,https://100kaminov.by/p124137478-pech-kamin-blist.html,https://100kaminov.by/p126748755-pech-kamin-blist.html,https://100kaminov.by/p126748774-pech-kamin-blist.html,https://100kaminov.by/p126748832-pech-kamin-blist.html,https://100kaminov.by/p126748852-pech-kamin-blist.html,https://100kaminov.by/p126748894-pech-kamin-blist.html,https://100kaminov.by/p126748918-pech-kamin-blist.html,https://100kaminov.by/p126749660-pech-kamin-blist.html,https://100kaminov.by/p127020214-pech-kamin-blist.html,https://100kaminov.by/p127164480-pech-kamin-blist.html,https://100kaminov.by/p127164619-pech-kamin-blist.html,https://100kaminov.by/p127164648-pech-kamin-blist.html,https://100kaminov.by/p127164662-pech-kamin-blist.html,https://100kaminov.by/p127287685-pech-kamin-blist.html,https://100kaminov.by/p127289906-pech-kamin-blist.html,https://100kaminov.by/p127290325-pech-kamin-blist.html,https://100kaminov.by/p127292448-pech-kamin-blist.html,https://100kaminov.by/p127293349-pech-kamin-blist.html,https://100kaminov.by/p127293382-pech-kamin-blist.html,https://100kaminov.by/p127293417-pech-kamin-blist.html,https://100kaminov.by/p127293792-pech-kamin-blist.html,https://100kaminov.by/p127293846-pech-kamin-blist.html --limit=25 --sleep=300`
- Log file: `storage/logs/server-artisan-ligmet-blist-direct-apply.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   331124c..a3989d8  main       -> origin/main
Updating 331124c..a3989d8
Fast-forward
 .github/server-artisan-result.md | 23 +++++++++--------------
 .github/server-artisan-task.json |  8 ++++----
 2 files changed, 13 insertions(+), 18 deletions(-)
APPLY
Catalog index: 1 brands, 44 products total

Direct product links: 25
  [Blist] Печь-камин Blist Ekonomik Lux бежевая → model:EKONOMIK LUX БЕЖЕВАЯ BLIST_BEIGE → NO MATCH
  [Blist] Печь-камин Blist Atene G красная → model:ATENE G КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Modena красная → model:MODENA КРАСНАЯ BLIST_RED → pid=16996
  [Blist] Печь-камин Blist Atene G ceramic красная → model:ATENE G КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Berna Lux S → model:BERNA LUX S → pid=16990
  [Blist] Печь-камин Blist Atene S серая → model:ATENE S СЕРАЯ BLIST_GREY → NO MATCH
  [Blist] Печь-камин Blist Atene S ceramic бежевый → model:ATENE S БЕЖЕВЫЙ BLIST_BEIGE → NO MATCH
  [Blist] Печь-камин Blist Ambasador LM N красный → model:AMBASADOR LM N КРАСНЫЙ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Vienna красная → model:VIENNA КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist B Max 1 → model:B MAX 1 → NO MATCH
  [Blist] Печь-камин Blist B Max 2 → model:B MAX 2 → NO MATCH
  [Blist] Печь-камин Blist Berna Lux серая → model:BERNA LUX СЕРАЯ BLIST_GREY → pid=16993
  [Blist] Печь-камин Blist B1 → model:B1 → pid=16994
  [Blist] Печь-камин Blist Basel → model:BASEL → NO MATCH
  [Blist] Печь-камин Blist B10 → model:B10 → NO MATCH
  [Blist] Печь-камин Blist Padova → model:PADOVA → NO MATCH
  [Blist] Печь-камин Blist Roma S красная (с духов → model:ROMA S КРАСНАЯ BLIST_RED → NO MATCH
  [Blist] Печь-камин Blist Ambasador R бежевая с д → model:AMBASADOR R БЕЖЕВАЯ BLIST_BEIGE → NO MATCH
  [Blist] Печь-камин Blist Napoli с духовым шкафом → model:NAPOLI ДУХОВЫМ ШКАФОМ → pid=16998
  [Blist] Печь-камин Blist BRM серая (с духовкой) → model:BRM СЕРАЯ BLIST_GREY → NO MATCH
  [Blist] Печь-камин Blist B2 E с водяным контуром → model:B2 E КОНТУРОМ → NO MATCH
  [Blist] Печь-камин Blist Padova E с водяным конт → model:PADOVA E КОНТУРОМ → pid=16999
  [Blist] Печь-камин Blist Milano E с теплообменни → model:MILANO E ТЕПЛООБМЕННИКОМ И → NO MATCH
  [Blist] Печь-камин Blist Roma E бежевая (с водян → model:ROMA E БЕЖЕВАЯ КОНТУРОМ BLIST_BEIGE → pid=17000
  [Blist] Печь-камин Blist B MAX E с водяным конту → model:B MAX E КОНТУРОМ → NO MATCH

+----------+-------+
| metric   | count |
+----------+-------+
| crawled  | 0     |
| matched  | 7     |
| enriched | 7     |
| images   | 13    |
| specs    | 31    |
| ai_done  | 0     |
| skipped  | 18    |
| errors   | 0     |
+----------+-------+

```
