# Server Artisan Result

- Time: 2026-07-11 12:50:25 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-ligmet-extra --base-url=https://kaminbel.by --source-url=/product/pechi-kaminy/fireway/ --brand=FireWay --skip-ai --limit=20 --dry-run`
- Log file: `storage/logs/server-artisan-ligmet-fireway-extra-preview.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   618258d..11da5d6  main       -> origin/main
Updating 618258d..11da5d6
Fast-forward
 .github/server-artisan-result.md                  | 148 +++++++++++++++++-----
 .github/server-artisan-task.json                  |   6 +-
 app/Console/Commands/EnrichLigmetExtraCommand.php |   3 +-
 3 files changed, 119 insertions(+), 38 deletions(-)
DRY RUN
Source: https://kaminbel.by
Catalog: 1 brands, 4 products
  [fireway] keys: DACHA II, TANGO, DAGMAR, ПАРОВАР КОВКА К505
Sitemaps found: 1

Collecting: /product/pechi-kaminy/fireway/
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
