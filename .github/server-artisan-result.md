# Server Artisan Result

- Time: 2026-07-12 12:28:12 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --product=20484 --http-timeout=5 --limit=1 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents`
- Log file: `storage/logs/varmega-official-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   5c010ed..716f5e7  main       -> origin/main
Updating 5c010ed..716f5e7
Fast-forward
 .github/server-artisan-result.md | 33 +++++++++++++++------------------
 .github/server-artisan-task.json |  6 +++---
 2 files changed, 18 insertions(+), 21 deletions(-)
APPLY: Varmega official source URLs will be written.
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
| written          | 1     |
| enriched         | 1     |
| images_found     | 4     |
| images_saved     | 1     |
| specs_found      | 16    |
| attributes_saved | 16    |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
