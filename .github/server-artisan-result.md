# Server Artisan Result

- Time: 2026-07-09 19:19:34 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=akvatermex --domain=thermex.by --force --replace-specs --overwrite-images --skip-documents --limit=5`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `1`

```text
DRY RUN: source enrichment preview only.
Products with source URLs: 51 (processing 5, offset 0, --force)
[1/5] #17564 4670033316681 Панель управления THERMEX в сборе с тэном 6 кВт
  source: https://thermex.by/index.php?route=product/product&path=112&product_id=4147#productParametrsBlock
  ERROR: HTTP request returned status code 403:
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/htm (truncated...)

[2/5] #17639 351 107 Газовая колонка Thermex S 20 MD
  source: https://thermex.by/gazovye-kolonki/seriya-sensor/thermex-s-20-md
  ERROR: HTTP request returned status code 403:
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/htm (truncated...)

[3/5] #17869 4670007714086 Водонагреватель Thermex Thermo 80V
  source: http://thermex.by/kruglye-nakopitelnye-vodonagrevateli/seriya-thermo/thermex-thermo-80-v
  ERROR: HTTP request returned status code 403:
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/htm (truncated...)

[4/5] #17890 4670007714666 Водонагреватель Thermex Praktik 150 V
  source: http://thermex.by/kruglye-nakopitelnye-vodonagrevateli/seriya-praktik/thermex-praktik-150-v
  ERROR: HTTP request returned status code 403:
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/htm (truncated...)

[5/5] #21165 4670007717674 THERMEX N 10 O
  source: https://thermex.by/nakopitelnye-vodonagrevateli-malogo-obema/seriya-nobel/thermex-n-10-o
  ERROR: HTTP request returned status code 403:
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/htm (truncated...)


+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 5     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 5     |
+------------------+-------+

```
