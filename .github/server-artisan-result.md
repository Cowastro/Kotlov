# Server Artisan Result

- Time: 2026-07-12 16:41:47 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM706 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm706.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   b4dcd07..259efdc  main       -> origin/main
Updating b4dcd07..259efdc
Fast-forward
 .github/server-artisan-result.md                   | 217 +++++----------------
 .github/server-artisan-task.json                   |   4 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |   5 +
 3 files changed, 55 insertions(+), 171 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 15.
Progress: checked=1 matched=0 missing=0 current=VM706001504
Progress: checked=10 matched=9 missing=0 current=VM706002806
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                          | official_url                                                           |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| 20508   | VM706001504 | Пресс-фитинги | Varmega VM706001504 15x1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20509   | VM706001505 | Пресс-фитинги | Varmega VM706001505 15x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20510   | VM706001804 | Пресс-фитинги | Varmega VM706001804 18x1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20511   | VM706001805 | Пресс-фитинги | Varmega VM706001805 18x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20512   | VM706002204 | Пресс-фитинги | Varmega VM706002204 22x1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20513   | VM706002205 | Пресс-фитинги | Varmega VM706002205 22x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20514   | VM706002206 | Пресс-фитинги | Varmega VM706002206 22x1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20515   | VM706002804 | Пресс-фитинги | Varmega VM706002804 28x1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20516   | VM706002805 | Пресс-фитинги | Varmega VM706002805 28x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20517   | VM706002806 | Пресс-фитинги | Varmega VM706002806 28x1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20518   | VM706003505 | Пресс-фитинги | Varmega VM706003505 35x3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20519   | VM706003506 | Пресс-фитинги | Varmega VM706003506 35x1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20520   | VM706003507 | Пресс-фитинги | Varmega VM706003507 35x1 1/4" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20521   | VM706004208 | Пресс-фитинги | Varmega VM706004208 42x1 1/2" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20522   | VM706005409 | Пресс-фитинги | Varmega VM706005409 54x2"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 15    |
| matched          | 15    |
| written          | 15    |
| enriched         | 15    |
| images_found     | 30    |
| images_saved     | 30    |
| specs_found      | 165   |
| attributes_saved | 150   |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
