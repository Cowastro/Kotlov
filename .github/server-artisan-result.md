# Server Artisan Result

- Time: 2026-07-12 13:51:55 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM701 --http-timeout=8 --limit=0 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents`
- Log file: `storage/logs/varmega-vm701-official-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   21273ed..a5306f5  main       -> origin/main
Updating 21273ed..a5306f5
Fast-forward
 .github/server-artisan-result.md | 40 ++++++++++++++++++++--------------------
 .github/server-artisan-task.json |  6 +++---
 2 files changed, 23 insertions(+), 23 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 7.
Progress: checked=1 matched=0 missing=0 current=VM701001515
+---------+-------------+---------------+---------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                      | official_url                                                           |
+---------+-------------+---------------+---------------------------+------------------------------------------------------------------------+
| 20456   | VM701001515 | Пресс-фитинги | Varmega VM701001515 15x15 | https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varm |
| 20457   | VM701001818 | Пресс-фитинги | Varmega VM701001818 18x18 | https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varm |
| 20458   | VM701002222 | Пресс-фитинги | Varmega VM701002222 22x22 | https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varm |
| 20459   | VM701002828 | Пресс-фитинги | Varmega VM701002828 28x28 | https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varm |
| 20460   | VM701003535 | Пресс-фитинги | Varmega VM701003535 35x35 | https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varm |
| 20461   | VM701004242 | Пресс-фитинги | Varmega VM701004242 42x42 | https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varm |
| 20462   | VM701005454 | Пресс-фитинги | Varmega VM701005454 54x54 | https://varmega.ru/product/truby-i-fitingi/mufta-dvukhrastrubnaya-varm |
+---------+-------------+---------------+---------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 7     |
| matched          | 7     |
| written          | 7     |
| enriched         | 7     |
| images_found     | 28    |
| images_saved     | 28    |
| specs_found      | 77    |
| attributes_saved | 77    |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
