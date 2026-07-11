# Server Artisan Result

- Time: 2026-07-11 12:40:22 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=akvatermex --force --skip-ai --limit=10 --sleep=1000`
- Log file: `storage/logs/server-artisan-akvatermex-source-force-preview.log`
- Exit code: `1`

```text
From https://github.com/Cowastro/Kotlov
   3b23ad9..4a9bc61  main       -> origin/main
Updating 3b23ad9..4a9bc61
Fast-forward
 .github/server-artisan-result.md | 101 ++++++++++++++++++++++++++++++---------
 .github/server-artisan-task.json |   6 +--
 2 files changed, 82 insertions(+), 25 deletions(-)
DRY RUN: source enrichment preview only.
Products with source URLs: 249 (processing 10, offset 0, --force)
[1/10] #17564 4670033316681 Панель управления THERMEX в сборе с тэном 6 кВт
  source: https://thermex.by/index.php?route=product/product&path=112&product_id=4147#productParametrsBlock
  ERROR: HTTP request returned status code 403:
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/htm (truncated...)

[2/10] #17604 4670033318777 Электрический котел Thermex Boss 12 Wi-Fi (White)
  source: https://www.teplodvor.by/shop/kotly/elektricheskie/elektricheskiy-kotel-thermex-boss-12-wi-fi-white
  found: images=4 specs=21 service=5 docs=2 video=1
[3/10] #17607 4670033318753 Электрический котел Thermex Boss 12 Wi-Fi (Black)
  source: https://www.teplodvor.by/shop/kotly/elektricheskie/elektricheskiy-kotel-thermex-boss-12-wi-fi-black
  found: images=4 specs=21 service=5 docs=2 video=1
[4/10] #17638 351 108 Газовая колонка Thermex B 20 D
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-thermex-b-20-d
  found: images=4 specs=12 service=5 docs=1 video=1
[5/10] #17639 351 107 Газовая колонка Thermex S 20 MD
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-thermex-s-20-md
  found: images=4 specs=18 service=5 docs=3 video=1
[6/10] #17640 351 111 Газовая колонка Thermex S 20 MD (Art Black)
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-thermex-s-20-md-art-black
  found: images=4 specs=18 service=5 docs=3 video=1
[7/10] #17643 4670033318548 Газовая колонка Thermex T 26 D
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-thermex-t-26-d
  found: images=4 specs=15 service=5 docs=1 video=1
[8/10] #17644 4670033318678 Газовая колонка Thermex T 20 D (Black)
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-thermex-t-20-d-black
  found: images=4 specs=15 service=5 docs=1 video=1
[9/10] #17645 4670033313741 Газовая колонка Thermex E 22 MD
  source: https://www.teplodvor.by/shop/vodonagrevateli/gazovye-kolonki/gazovaya-kolonka-thermex-e-22-md
  found: images=4 specs=15 service=5 docs=1 video=1
[10/10] #17651 6971170591008 Водонагреватель Thermex Ceramik 50 V
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-ceramik-50-v
  found: images=4 specs=23 service=5 docs=2 video=1

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 10    |
| enriched         | 9     |
| images_found     | 36    |
| images_saved     | 0     |
| specs_found      | 158   |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 1     |
+------------------+-------+

```
