# Server Artisan Result

- Time: 2026-07-12 12:25:52 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --product=20484 --http-timeout=5 --limit=1`
- Log file: `storage/logs/varmega-official-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   08f34f4..5c010ed  main       -> origin/main
Updating 08f34f4..5c010ed
Fast-forward
 .github/server-artisan-result.md                   | 19 ++++++++--------
 .github/server-artisan-task.json                   |  4 ++--
 .../Commands/RepairVarmegaSourceUrlsCommand.php    | 25 ++++++++++++++++++++++
 3 files changed, 36 insertions(+), 12 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 1.
Progress: checked=1 matched=0 missing=0 current=VM704003515
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                       | official_url                                                           |
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
| 20484   | VM704003515 | Пресс-фитинги | Varmega VM704003515 35ax15 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 1     |
| matched          | 1     |
| written          | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
