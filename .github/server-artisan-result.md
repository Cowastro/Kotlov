# Server Artisan Result

- Time: 2026-07-12 12:59:01 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --article-prefix=VM701 --rn-profi-section-index --rn-profi-section-pages=80 --http-timeout=5 --limit=0`
- Log file: `storage/logs/varmega-vm701-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   e882b80..287793e  main       -> origin/main
Updating e882b80..287793e
Fast-forward
 .github/server-artisan-result.md | 135 +++++++++++++++++++++++++--------------
 .github/server-artisan-task.json |   6 +-
 2 files changed, 90 insertions(+), 51 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi section index progress: fetched=20 indexed=85.
RN-Profi section index progress: fetched=40 indexed=135.
RN-Profi section index fetched=51 pages.
RN-Profi section article index: 157 URLs.
RN-Profi Varmega links to check: 7.
Progress: checked=1 matched=0 missing=0 current=VM701001515
+---------+-------------+---------------+---------------------------+-----------------------------------------------------------------------+
| product | article     | category      | name                      | official_url                                                          |
+---------+-------------+---------------+---------------------------+-----------------------------------------------------------------------+
| 20456   | VM701001515 | Пресс-фитинги | Varmega VM701001515 15x15 | https://rn-profi.by/truba-iz-nerzhaveyuschej-stali-varmega-inox-press |
| 20457   | VM701001818 | Пресс-фитинги | Varmega VM701001818 18x18 | -                                                                     |
| 20458   | VM701002222 | Пресс-фитинги | Varmega VM701002222 22x22 | -                                                                     |
| 20459   | VM701002828 | Пресс-фитинги | Varmega VM701002828 28x28 | -                                                                     |
| 20460   | VM701003535 | Пресс-фитинги | Varmega VM701003535 35x35 | -                                                                     |
| 20461   | VM701004242 | Пресс-фитинги | Varmega VM701004242 42x42 | -                                                                     |
| 20462   | VM701005454 | Пресс-фитинги | Varmega VM701005454 54x54 | -                                                                     |
+---------+-------------+---------------+---------------------------+-----------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 7     |
| matched          | 1     |
| written          | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| missing          | 6     |
| errors           | 0     |
+------------------+-------+

```
