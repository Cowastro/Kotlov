# Server Artisan Result

- Time: 2026-07-12 17:07:26 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM710 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm710.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   38c993e..97a8476  main       -> origin/main
Updating 38c993e..97a8476
Fast-forward
 .github/server-artisan-result.md                   | 275 +++++++++++++++++----
 .github/server-artisan-task.json                   |   8 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |   5 +
 3 files changed, 238 insertions(+), 50 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 7.
Progress: checked=1 matched=0 missing=0 current=VM710001515
+---------+-------------+---------------+---------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                      | official_url                                                           |
+---------+-------------+---------------+---------------------------+------------------------------------------------------------------------+
| 20562   | VM710001515 | Пресс-фитинги | Varmega VM710001515 15x15 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20563   | VM710001818 | Пресс-фитинги | Varmega VM710001818 18x18 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20564   | VM710002222 | Пресс-фитинги | Varmega VM710002222 22x22 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20565   | VM710002828 | Пресс-фитинги | Varmega VM710002828 28x28 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20566   | VM710003535 | Пресс-фитинги | Varmega VM710003535 35x35 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20567   | VM710004242 | Пресс-фитинги | Varmega VM710004242 42x42 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20568   | VM710005454 | Пресс-фитинги | Varmega VM710005454 54x54 | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
+---------+-------------+---------------+---------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 7     |
| matched          | 7     |
| written          | 7     |
| enriched         | 7     |
| images_found     | 14    |
| images_saved     | 14    |
| specs_found      | 84    |
| attributes_saved | 77    |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
