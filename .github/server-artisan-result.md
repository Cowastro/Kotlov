# Server Artisan Result

- Time: 2026-07-12 12:49:24 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --article-prefix=VM7040 --http-timeout=5 --limit=0`
- Log file: `storage/logs/varmega-vm7040-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   887a992..a661b45  main       -> origin/main
Updating 887a992..a661b45
Fast-forward
 .github/server-artisan-result.md                   | 51 ++++++++--------------
 .github/server-artisan-task.json                   |  6 +--
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  5 +++
 3 files changed, 26 insertions(+), 36 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 16.
Progress: checked=1 matched=0 missing=0 current=VM704001815
Progress: checked=10 matched=9 missing=0 current=VM704003528
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                       | official_url                                                           |
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
| 20478   | VM704001815 | Пресс-фитинги | Varmega VM704001815 18ax15 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20479   | VM704002215 | Пресс-фитинги | Varmega VM704002215 22ax15 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20480   | VM704002218 | Пресс-фитинги | Varmega VM704002218 22ax18 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20481   | VM704002815 | Пресс-фитинги | Varmega VM704002815 28ax15 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20482   | VM704002818 | Пресс-фитинги | Varmega VM704002818 28ax18 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20483   | VM704002822 | Пресс-фитинги | Varmega VM704002822 28ax22 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20484   | VM704003515 | Пресс-фитинги | Varmega VM704003515 35ax15 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20485   | VM704003518 | Пресс-фитинги | Varmega VM704003518 35ax18 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20486   | VM704003522 | Пресс-фитинги | Varmega VM704003522 35ax22 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20487   | VM704003528 | Пресс-фитинги | Varmega VM704003528 35ax28 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20488   | VM704004222 | Пресс-фитинги | Varmega VM704004222 42ax22 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20489   | VM704004228 | Пресс-фитинги | Varmega VM704004228 42ax28 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20490   | VM704004235 | Пресс-фитинги | Varmega VM704004235 42ax35 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20491   | VM704005428 | Пресс-фитинги | Varmega VM704005428 54ax28 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20492   | VM704005435 | Пресс-фитинги | Varmega VM704005435 54ax35 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
| 20493   | VM704005442 | Пресс-фитинги | Varmega VM704005442 54ax42 | https://rn-profi.by/mufta-vstavka-odnorastrubnaya-varmega-inox-press-p |
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 16    |
| matched          | 16    |
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
