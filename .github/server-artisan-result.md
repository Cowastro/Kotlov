# Server Artisan Result

- Time: 2026-07-11 12:19:08 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-tsk-nasosy --max-current-attrs=2 --skip-ai --limit=20 --sleep=1000`
- Log file: `storage/logs/server-artisan-tsk-nasosy-low-attrs-preview.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   6a2f11c..cc22b8d  main       -> origin/main
Updating 6a2f11c..cc22b8d
Fast-forward
 .github/server-artisan-result.md                | 268 ++----------------------
 .github/server-artisan-task.json                |   6 +-
 app/Console/Commands/EnrichTskNasosyCommand.php |   9 +-
 3 files changed, 27 insertions(+), 256 deletions(-)
DRY RUN
aqualider map: 5836 article → card links
Linked products: 143  |  to process: 143
+-----------+------------+----------+---------+----------+--------------------------------------------------------------+
| article   | product_id | in_stock | has_img | has_desc | card_url                                                     |
+-----------+------------+----------+---------+----------+--------------------------------------------------------------+
| 100345493 | 16339      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/motopompy/ |
| 100345494 | 16340      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/motopompy/ |
| 100345497 | 16345      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/motopompy/ |
| 100582450 | 16341      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/motopompy/ |
| 100582451 | 16342      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/motopompy/ |
| 100582453 | 16344      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/motopompy/ |
| 100582454 | 16343      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/motopompy/ |
| 11013     | 16294      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/odnostupen |
| 11019986  | 16280      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019987  | 16265      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019990  | 16275      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019991  | 16260      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019993  | 16259      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019996  | 16274      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019997  | 16258      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019998  | 16273      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11019999  | 16257      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11931     | 16281      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 11988     | 16245      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupe |
| 12019966  | 16298      | да       | да      | да       | https://aqualider.by/catalog/promyshlennye_nasosy/odnostupen |
+-----------+------------+----------+---------+----------+--------------------------------------------------------------+

Run with --apply to write photos/descriptions/specs.

```
