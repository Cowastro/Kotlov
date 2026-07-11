# Server Artisan Result

- Time: 2026-07-11 11:57:53 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=ligmet --brand=Blist --skip-ai --limit=10 --sleep=1000`
- Log file: `storage/logs/server-artisan-ligmet-blist-source-preview.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   8423b7e..c83a1c4  main       -> origin/main
Updating 8423b7e..c83a1c4
Fast-forward
 .github/server-artisan-result.md | 391 ++++++++++++++++++++-------------------
 .github/server-artisan-task.json |   6 +-
 2 files changed, 199 insertions(+), 198 deletions(-)
DRY RUN: source enrichment preview only.
Products with source URLs: 31 (processing 10, offset 0)
[1/10] #21361 skipped generic source URL: https://ligmet.by/
[2/10] #21362 skipped generic source URL: https://ligmet.by/
[3/10] #21363 skipped generic source URL: https://ligmet.by/
[4/10] #21364 skipped generic source URL: https://ligmet.by/
[5/10] #21365 skipped generic source URL: https://ligmet.by/
[6/10] #21366 skipped generic source URL: https://ligmet.by/
[7/10] #21367 skipped generic source URL: https://ligmet.by/
[8/10] #21368 skipped generic source URL: https://ligmet.by/
[9/10] #21369 skipped generic source URL: https://ligmet.by/
[10/10] #21370 skipped generic source URL: https://ligmet.by/

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 10    |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 10    |
| errors           | 0     |
+------------------+-------+

```
