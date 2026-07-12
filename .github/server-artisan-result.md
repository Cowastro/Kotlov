# Server Artisan Result

- Time: 2026-07-12 16:51:32 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM708 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm708.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   f943b0b..0c36ac1  main       -> origin/main
Updating f943b0b..0c36ac1
Fast-forward
 .github/server-artisan-result.md | 76 +++++++++++++++++++---------------------
 .github/server-artisan-task.json |  6 ++--
 2 files changed, 40 insertions(+), 42 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 13.
Progress: checked=1 matched=0 missing=0 current=VM708001504
Progress: checked=10 matched=9 missing=0 current=VM708002806
+---------+-------------+---------------+--------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                           | official_url                                                           |
+---------+-------------+---------------+--------------------------------+------------------------------------------------------------------------+
| 20536   | VM708001504 | Пресс-фитинги | Varmega VM708001504 15ax1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20537   | VM708001505 | Пресс-фитинги | Varmega VM708001505 15ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20538   | VM708001804 | Пресс-фитинги | Varmega VM708001804 18ax1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20539   | VM708001805 | Пресс-фитинги | Varmega VM708001805 18ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20540   | VM708001806 | Пресс-фитинги | Varmega VM708001806 18ax1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20541   | VM708002204 | Пресс-фитинги | Varmega VM708002204 22ax1/2"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20542   | VM708002205 | Пресс-фитинги | Varmega VM708002205 22ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20543   | VM708002206 | Пресс-фитинги | Varmega VM708002206 22ax1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20544   | VM708002805 | Пресс-фитинги | Varmega VM708002805 28ax3/4"   | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20545   | VM708002806 | Пресс-фитинги | Varmega VM708002806 28ax1"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20546   | VM708003507 | Пресс-фитинги | Varmega VM708003507 35ax1 1/4" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20547   | VM708004208 | Пресс-фитинги | Varmega VM708004208 42ax1 1/2" | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
| 20548   | VM708005409 | Пресс-фитинги | Varmega VM708005409 54ax2"     | https://termogorod.ru/truby-i-fitingi/truby-i-fitingi-iz-nerzhavejki/t |
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
| specs_found      | 143   |
| attributes_saved | 130   |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
