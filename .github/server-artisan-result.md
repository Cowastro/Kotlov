# Server Artisan Result

- Time: 2026-07-12 14:02:05 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM705 --http-timeout=8 --limit=0 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents`
- Log file: `storage/logs/varmega-vm705-official-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   d9391dd..bf12611  main       -> origin/main
Updating d9391dd..bf12611
Fast-forward
 .github/server-artisan-result.md | 70 ++++++++++++++++++++++------------------
 .github/server-artisan-task.json |  6 ++--
 2 files changed, 42 insertions(+), 34 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 14.
Progress: checked=1 matched=0 missing=0 current=VM705001504
Progress: checked=10 matched=9 missing=0 current=VM705003505
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                          | official_url                                                           |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| 20494   | VM705001504 | Пресс-фитинги | Varmega VM705001504 15x1/2"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20495   | VM705001804 | Пресс-фитинги | Varmega VM705001804 18x1/2"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20496   | VM705001805 | Пресс-фитинги | Varmega VM705001805 18x3/4"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20497   | VM705002204 | Пресс-фитинги | Varmega VM705002204 22x1/2"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20498   | VM705002205 | Пресс-фитинги | Varmega VM705002205 22x3/4"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20499   | VM705002206 | Пресс-фитинги | Varmega VM705002206 22x1"     | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20500   | VM705002804 | Пресс-фитинги | Varmega VM705002804 28x1/2"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20501   | VM705002805 | Пресс-фитинги | Varmega VM705002805 28x3/4"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20502   | VM705002806 | Пресс-фитинги | Varmega VM705002806 28x1"     | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20503   | VM705003505 | Пресс-фитинги | Varmega VM705003505 35x3/4"   | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20504   | VM705003506 | Пресс-фитинги | Varmega VM705003506 35x1"     | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20505   | VM705003507 | Пресс-фитинги | Varmega VM705003507 35x1 1/4" | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20506   | VM705004208 | Пресс-фитинги | Varmega VM705004208 42x1 1/2" | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
| 20507   | VM705005409 | Пресс-фитинги | Varmega VM705005409 54x2"     | https://varmega.ru/product/truby-i-fitingi/mufta-rastrubnaya-varmega-i |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 14    |
| matched          | 14    |
| written          | 14    |
| enriched         | 14    |
| images_found     | 56    |
| images_saved     | 56    |
| specs_found      | 165   |
| attributes_saved | 165   |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
