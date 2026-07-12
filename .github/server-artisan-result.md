# Server Artisan Result

- Time: 2026-07-12 13:30:13 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --article-prefix=VM705 --rn-profi-section-index --rn-profi-section-pages=80 --http-timeout=5 --limit=0`
- Log file: `storage/logs/varmega-vm705-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   c8ced5c..6387472  main       -> origin/main
Updating c8ced5c..6387472
Fast-forward
 .github/server-artisan-result.md | 153 +++++++++++++++++++++++++++++----------
 .github/server-artisan-task.json |   8 +-
 2 files changed, 120 insertions(+), 41 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi section index progress: fetched=20 indexed=85.
RN-Profi section index progress: fetched=40 indexed=135.
RN-Profi section index fetched=51 pages.
RN-Profi section article index: 157 URLs.
RN-Profi Varmega links to check: 14.
Progress: checked=1 matched=0 missing=0 current=VM705001504
Progress: checked=10 matched=1 missing=8 current=VM705003505
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                          | official_url                                                           |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| 20494   | VM705001504 | Пресс-фитинги | Varmega VM705001504 15x1/2"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-vnutrennej- |
| 20495   | VM705001804 | Пресс-фитинги | Varmega VM705001804 18x1/2"   | -                                                                      |
| 20496   | VM705001805 | Пресс-фитинги | Varmega VM705001805 18x3/4"   | -                                                                      |
| 20497   | VM705002204 | Пресс-фитинги | Varmega VM705002204 22x1/2"   | -                                                                      |
| 20498   | VM705002205 | Пресс-фитинги | Varmega VM705002205 22x3/4"   | -                                                                      |
| 20499   | VM705002206 | Пресс-фитинги | Varmega VM705002206 22x1"     | -                                                                      |
| 20500   | VM705002804 | Пресс-фитинги | Varmega VM705002804 28x1/2"   | -                                                                      |
| 20501   | VM705002805 | Пресс-фитинги | Varmega VM705002805 28x3/4"   | -                                                                      |
| 20502   | VM705002806 | Пресс-фитинги | Varmega VM705002806 28x1"     | -                                                                      |
| 20503   | VM705003505 | Пресс-фитинги | Varmega VM705003505 35x3/4"   | -                                                                      |
| 20504   | VM705003506 | Пресс-фитинги | Varmega VM705003506 35x1"     | -                                                                      |
| 20505   | VM705003507 | Пресс-фитинги | Varmega VM705003507 35x1 1/4" | -                                                                      |
| 20506   | VM705004208 | Пресс-фитинги | Varmega VM705004208 42x1 1/2" | -                                                                      |
| 20507   | VM705005409 | Пресс-фитинги | Varmega VM705005409 54x2"     | -                                                                      |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 14    |
| matched          | 1     |
| written          | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| missing          | 13    |
| errors           | 0     |
+------------------+-------+

```
