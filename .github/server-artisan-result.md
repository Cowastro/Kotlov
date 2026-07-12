# Server Artisan Result

- Time: 2026-07-12 17:15:51 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM711,VM712,VM713,VM714,VM715,VM716,VM717,VM718,VM719,VM720,VM721,VM722,VM723,VM724,VM725,VM730,VM731,VM732,VM733 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-inox-tail.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   97a8476..1b72b84  main       -> origin/main
Updating 97a8476..1b72b84
Fast-forward
 .github/server-artisan-result.md                   | 269 +++------------------
 .github/server-artisan-task.json                   |   6 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  14 +-
 3 files changed, 54 insertions(+), 235 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 113.
Progress: checked=1 matched=0 missing=0 current=VM711001515
Progress: checked=10 matched=9 missing=0 current=VM712002222
Progress: checked=20 matched=19 missing=0 current=VM713004242
Progress: checked=30 matched=29 missing=0 current=VM715001804
Progress: checked=40 matched=39 missing=0 current=VM717001504
Progress: checked=50 matched=49 missing=0 current=VM719221822
Progress: checked=60 matched=59 missing=0 current=VM719544254
Progress: checked=70 matched=69 missing=0 current=VM720350635
Progress: checked=80 matched=79 missing=0 current=VM721180518
Progress: checked=90 matched=89 missing=0 current=VM721540654
Progress: checked=100 matched=99 missing=0 current=VM724002828
Progress: checked=110 matched=109 missing=0 current=VM731001818
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                       | official_url                                                           |
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
| 20569   | VM711001515 | Пресс-фитинги | Varmega VM711001515 15x15a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20570   | VM711001818 | Пресс-фитинги | Varmega VM711001818 18x18a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20571   | VM711002222 | Пресс-фитинги | Varmega VM711002222 22x22a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20572   | VM711002828 | Пресс-фитинги | Varmega VM711002828 28x28a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20573   | VM711003535 | Пресс-фитинги | Varmega VM711003535 35x35a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20574   | VM711004242 | Пресс-фитинги | Varmega VM711004242 42x42a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20575   | VM711005454 | Пресс-фитинги | Varmega VM711005454 54x54a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20576   | VM712001515 | Пресс-фитинги | Varmega VM712001515 15x15  | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20577   | VM712001818 | Пресс-фитинги | Varmega VM712001818 18x18  | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20578   | VM712002222 | Пресс-фитинги | Varmega VM712002222 22x22  | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20579   | VM712002828 | Пресс-фитинги | Varmega VM712002828 28x28  | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20580   | VM712003535 | Пресс-фитинги | Varmega VM712003535 35x35  | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20581   | VM712004242 | Пресс-фитинги | Varmega VM712004242 42x42  | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20582   | VM712005454 | Пресс-фитинги | Varmega VM712005454 54x54  | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20583   | VM713001515 | Пресс-фитинги | Varmega VM713001515 15x15a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20584   | VM713001818 | Пресс-фитинги | Varmega VM713001818 18x18a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20585   | VM713002222 | Пресс-фитинги | Varmega VM713002222 22x22a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20586   | VM713002828 | Пресс-фитинги | Varmega VM713002828 28x28a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20587   | VM713003535 | Пресс-фитинги | Varmega VM713003535 35x35a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20588   | VM713004242 | Пресс-фитинги | Varmega VM713004242 42x42a | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
+---------+-------------+---------------+----------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 113   |
| matched          | 113   |
| written          | 113   |
| enriched         | 113   |
| images_found     | 213   |
| images_saved     | 209   |
| specs_found      | 1457  |
| attributes_saved | 1344  |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
