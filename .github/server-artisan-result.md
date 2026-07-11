# Server Artisan Result

- Time: 2026-07-11 19:21:03 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-source-products --supplier=tsk_nasosy --domain=aqualider.by --force --replace-specs --overwrite-images --limit=10`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   2b750f7..e52348c  main       -> origin/main
Updating 2b750f7..e52348c
Fast-forward
 .github/server-artisan-result.md | 156 ++++-----------------------------------
 .github/server-artisan-task.json |   4 +-
 2 files changed, 16 insertions(+), 144 deletions(-)
DRY RUN: source enrichment preview only.
Products with source URLs: 192 (processing 10, offset 0, --force)
[1/10] #8303 skipped generic source URL: https://aqualider.by/
[2/10] #8304 skipped generic source URL: https://aqualider.by/
[3/10] #8305 skipped generic source URL: https://aqualider.by/
[4/10] #8306 skipped generic source URL: https://aqualider.by/
[5/10] #8468 skipped generic source URL: https://aqualider.by/
[6/10] #8472 skipped generic source URL: https://aqualider.by/
[7/10] #16194 61656 UNIPUMP Насос ЭЦВ 4-2-45
  source: https://aqualider.by/catalog/promyshlennye_nasosy/tsentrobezhnye_skvazhinnye_nasosy_3_380/75133/
  found: images=4 specs=6 service=1 docs=3 video=1
[8/10] #16195 70291 UNIPUMP Насос ЭЦВ 4-2-60
  source: https://aqualider.by/catalog/promyshlennye_nasosy/tsentrobezhnye_skvazhinnye_nasosy_3_380/75132/
  found: images=4 specs=6 service=1 docs=3 video=1
[9/10] #16196 37297 UNIPUMP Насос ЭЦВ 4-2-70
  source: https://aqualider.by/catalog/promyshlennye_nasosy/tsentrobezhnye_skvazhinnye_nasosy_3_380/75134/
  found: images=4 specs=6 service=1 docs=3 video=1
[10/10] #16197 48074 UNIPUMP Насос ЭЦВ 4-3-60
  source: https://aqualider.by/catalog/promyshlennye_nasosy/tsentrobezhnye_skvazhinnye_nasosy_3_380/66387/
  found: images=4 specs=6 service=1 docs=5 video=1

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 10    |
| enriched         | 4     |
| images_found     | 16    |
| images_saved     | 0     |
| specs_found      | 24    |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 6     |
| errors           | 0     |
+------------------+-------+

```
