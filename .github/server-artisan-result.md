# Server Artisan Result

- Time: 2026-07-09 20:28:40 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=akvatermex --domain=teplodvor.by --max-current-attrs=2 --force --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=30`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `1`

```text
DRY RUN: source enrichment preview only.
Products with source URLs: 6 (processing 6, offset 0, --force)
[1/6] #21205 6971170590315 THERMEX IF 100 (smart)
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-if-100-v-smart
  found: images=4 specs=30 service=5 docs=2 video=1
[2/6] #21244 4607084195453 Edisson ER 80
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-edisson-er-80-v
  found: images=4 specs=20 service=5 docs=1 video=1
[3/6] #21272 4670033310597 THERMEX Frame 1500E
  source: https://www.teplodvor.by/shop/raditory/konvektory/elektrokonvektor-thermex-frame-1500e
  found: images=4 specs=18 service=5 docs=2 video=1
[4/6] #21290 361 201 EDISSON H 20 D
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-edisson-h-20-d
  ERROR: HTTP request returned status code 404:
<!doctype html>
<html lang="ru">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link hre (truncated...)

[5/6] #21301 ЭдЭБ01237 EUROSTAR E 906
  source: https://www.teplodvor.by/shop/kotly/elektricheskie/elektricheskiy-kotel-thermex-eurostar-e-906
  found: images=4 specs=17 service=5 docs=2 video=1
[6/6] #21306 4670033317398 THERMEX Stern 9
  source: https://www.teplodvor.by/shop/kotly/elektricheskie/elektricheskiy-kotel-thermex-stern-4-12-tip-b-9-kv
  found: images=4 specs=18 service=5 docs=1 video=1

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 6     |
| enriched         | 5     |
| images_found     | 20    |
| images_saved     | 0     |
| specs_found      | 103   |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 1     |
+------------------+-------+

```
