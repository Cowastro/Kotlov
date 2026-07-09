# Server Artisan Result

- Time: 2026-07-09 20:09:40 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=akvatermex --domain=teplodvor.by --max-current-attrs=2 --force --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=20`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `1`

```text
DRY RUN: source enrichment preview only.
Products with source URLs: 5 (processing 5, offset 0, --force)
[1/5] #21165 4670007717674 THERMEX N 10 O
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-n-10-o-pro
  found: images=4 specs=26 service=5 docs=1 video=1
[2/5] #21166 4670007719555 THERMEX N 10 U
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-n-10-u-pro
  found: images=4 specs=26 service=5 docs=1 video=1
[3/5] #21167 4670007717681 THERMEX N 15 O
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-n-15-o-pro
  found: images=4 specs=27 service=5 docs=1 video=1
[4/5] #21168 4670007719562 THERMEX N 15 U
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-n-15-u-pro
  found: images=4 specs=20 service=5 docs=1 video=1
[5/5] #21290 361 201 EDISSON H 20 D
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
| processed        | 5     |
| enriched         | 4     |
| images_found     | 16    |
| images_saved     | 0     |
| specs_found      | 99    |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 1     |
+------------------+-------+

```
