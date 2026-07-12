# Server Artisan Result

- Time: 2026-07-12 16:55:05 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM709 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm709.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   0c36ac1..ca75287  main       -> origin/main
Updating 0c36ac1..ca75287
Fast-forward
 .github/server-artisan-result.md | 51 ++++++++++++++++++++--------------------
 .github/server-artisan-task.json |  6 ++---
 2 files changed, 28 insertions(+), 29 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 13.
Progress: checked=1 matched=0 missing=0 current=VM709001504
Progress: checked=10 matched=9 missing=0 current=VM709003506
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                          | official_url                                                           |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| 20549   | VM709001504 | Пресс-фитинги | Varmega VM709001504 15x1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20550   | VM709001505 | Пресс-фитинги | Varmega VM709001505 15x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20551   | VM709001804 | Пресс-фитинги | Varmega VM709001804 18x1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20552   | VM709001805 | Пресс-фитинги | Varmega VM709001805 18x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20553   | VM709002204 | Пресс-фитинги | Varmega VM709002204 22x1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20554   | VM709002205 | Пресс-фитинги | Varmega VM709002205 22x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20555   | VM709002206 | Пресс-фитинги | Varmega VM709002206 22x1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20556   | VM709002805 | Пресс-фитинги | Varmega VM709002805 28x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20557   | VM709002806 | Пресс-фитинги | Varmega VM709002806 28x1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20558   | VM709003506 | Пресс-фитинги | Varmega VM709003506 35x1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20559   | VM709003507 | Пресс-фитинги | Varmega VM709003507 35x1 1/4" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20560   | VM709004208 | Пресс-фитинги | Varmega VM709004208 42x1 1/2" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20561   | VM709005409 | Пресс-фитинги | Varmega VM709005409 54x2"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 13    |
| matched          | 13    |
| written          | 13    |
| enriched         | 13    |
| images_found     | 26    |
| images_saved     | 26    |
| specs_found      | 143   |
| attributes_saved | 130   |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
