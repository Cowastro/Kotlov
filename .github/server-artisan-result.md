# Server Artisan Result

- Time: 2026-07-11 12:42:52 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=akvatermex --domain=teplodvor.by --force --skip-ai --skip-documents --max-current-attrs=2 --limit=20 --sleep=1000`
- Log file: `storage/logs/server-artisan-akvatermex-teplodvor-preview.log`
- Exit code: `1`

```text
From https://github.com/Cowastro/Kotlov
   4a9bc61..9009f7c  main       -> origin/main
Updating 4a9bc61..9009f7c
Fast-forward
 .github/server-artisan-result.md | 135 ++++++++++++++++-----------------------
 .github/server-artisan-task.json |   6 +-
 2 files changed, 58 insertions(+), 83 deletions(-)
DRY RUN: source enrichment preview only.
Products with source URLs: 1 (processing 1, offset 0, --force)
[1/1] #21290 361 201 EDISSON H 20 D
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-edisson-h-20-d
  ERROR: HTTP request returned status code 404:
<!doctype html>
<html lang="ru">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link hre (truncated...)


+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 1     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 1     |
+------------------+-------+

```
