# Server Artisan Result

- Time: 2026-07-12 16:47:52 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM707 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm707.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   259efdc..f943b0b  main       -> origin/main
Updating 259efdc..f943b0b
Fast-forward
 .github/server-artisan-result.md                   | 71 +++++++++++-----------
 .github/server-artisan-task.json                   |  6 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  5 ++
 3 files changed, 44 insertions(+), 38 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 13.
Progress: checked=1 matched=0 missing=0 current=VM707001504
Progress: checked=10 matched=9 missing=0 current=VM707002806
+---------+-------------+---------------+--------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                           | official_url                                                           |
+---------+-------------+---------------+--------------------------------+------------------------------------------------------------------------+
| 20523   | VM707001504 | Пресс-фитинги | Varmega VM707001504 15ax1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20524   | VM707001505 | Пресс-фитинги | Varmega VM707001505 15ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20525   | VM707001804 | Пресс-фитинги | Varmega VM707001804 18ax1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20526   | VM707001805 | Пресс-фитинги | Varmega VM707001805 18ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20527   | VM707001806 | Пресс-фитинги | Varmega VM707001806 18ax1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20528   | VM707002204 | Пресс-фитинги | Varmega VM707002204 22ax1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20529   | VM707002205 | Пресс-фитинги | Varmega VM707002205 22ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20530   | VM707002206 | Пресс-фитинги | Varmega VM707002206 22ax1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20531   | VM707002805 | Пресс-фитинги | Varmega VM707002805 28ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20532   | VM707002806 | Пресс-фитинги | Varmega VM707002806 28ax1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20533   | VM707003507 | Пресс-фитинги | Varmega VM707003507 35ax1 1/4" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20534   | VM707004208 | Пресс-фитинги | Varmega VM707004208 42ax1 1/2" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20535   | VM707005409 | Пресс-фитинги | Varmega VM707005409 54ax2"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
+---------+-------------+---------------+--------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 13    |
| matched          | 13    |
| written          | 13    |
| enriched         | 13    |
| images_found     | 26    |
| images_saved     | 26    |
| specs_found      | 156   |
| attributes_saved | 143   |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
