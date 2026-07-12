# Server Artisan Result

- Time: 2026-07-12 12:35:59 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --rn-profi-section-index --rn-profi-section-pages=80 --http-timeout=5 --limit=50`
- Log file: `storage/logs/varmega-official-source-repair-batch.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   c3fdc18..eb5b096  main       -> origin/main
Updating c3fdc18..eb5b096
Fast-forward
 .github/server-artisan-result.md | 51 +++++++++++++++-------------------------
 .github/server-artisan-task.json |  6 ++---
 2 files changed, 22 insertions(+), 35 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi section index progress: fetched=20 indexed=85.
RN-Profi section index progress: fetched=40 indexed=135.
RN-Profi section index fetched=51 pages.
RN-Profi section article index: 157 URLs.
RN-Profi Varmega links to check: 50.
Progress: checked=1 matched=0 missing=0 current=VM19501
Progress: checked=10 matched=0 missing=9 current=VM35511ШРН1
Progress: checked=20 matched=0 missing=19 current=VM35525ШРНГ5
Progress: checked=30 matched=1 missing=28 current=VM701002828
Progress: checked=40 matched=2 missing=37 current=VM702004235
Progress: checked=50 matched=4 missing=45 current=VM704002215
+---------+--------------+-----------------+------------------------------+--------------+
| product | article      | category        | name                         | official_url |
+---------+--------------+-----------------+------------------------------+--------------+
| 20357   | VM19501      | Котлы отопления | Varmega VM19501 3 м          | -            |
| 20365   | VM35500ШРВ0  | Котлы отопления | Varmega VM35500 ШРВ-0 1-3    | -            |
| 20366   | VM35501ШРВ1  | Котлы отопления | Varmega VM35501 ШРВ-1 4-5    | -            |
| 20367   | VM35502ШРВ2  | Котлы отопления | Varmega VM35502 ШРВ-2 6-7    | -            |
| 20368   | VM35503ШРВ3  | Котлы отопления | Varmega VM35503 ШРВ-3 8-10   | -            |
| 20369   | VM35504ШРВ4  | Котлы отопления | Varmega VM35504 ШРВ-4 11-12  | -            |
| 20370   | VM35505ШРВ5  | Котлы отопления | Varmega VM35505 ШРВ-5 13-16  | -            |
| 20371   | VM35506ШРВ6  | Котлы отопления | Varmega VM35506 ШРВ-6 17-18  | -            |
| 20372   | VM35510ШРН0  | Котлы отопления | Varmega VM35510 ШРН-0 1-3    | -            |
| 20373   | VM35511ШРН1  | Котлы отопления | Varmega VM35511 ШРН-1 4-5    | -            |
| 20374   | VM35512ШРН2  | Котлы отопления | Varmega VM35512 ШРН-2 6-7    | -            |
| 20375   | VM35513ШРН3  | Котлы отопления | Varmega VM35513 ШРН-3 8-10   | -            |
| 20376   | VM35514ШРН4  | Котлы отопления | Varmega VM35514 ШРН-4 11-12  | -            |
| 20377   | VM35515ШРН5  | Котлы отопления | Varmega VM35515 ШРН-5 13-16  | -            |
| 20378   | VM35516ШРН6  | Котлы отопления | Varmega VM35516 ШРН-6 17-18  | -            |
| 20379   | VM35521ШРНГ1 | Котлы отопления | Varmega VM35521 ШРНГ-1 4-5   | -            |
| 20380   | VM35522ШРНГ2 | Котлы отопления | Varmega VM35522 ШРНГ-2 6-7   | -            |
| 20381   | VM35523ШРНГ3 | Котлы отопления | Varmega VM35523 ШРНГ-3 8-10  | -            |
| 20382   | VM35524ШРНГ4 | Котлы отопления | Varmega VM35524 ШРНГ-4 11-12 | -            |
| 20383   | VM35525ШРНГ5 | Котлы отопления | Varmega VM35525 ШРНГ-5 13-16 | -            |
+---------+--------------+-----------------+------------------------------+--------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 50    |
| matched          | 5     |
| written          | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| missing          | 45    |
| errors           | 0     |
+------------------+-------+

```
