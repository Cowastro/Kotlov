# Server Artisan Result

- Time: 2026-07-12 16:15:19 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --refresh-index --article-prefix=VM722 --enrich --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm722-plugs.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   ee0d2ac..4fd4c67  main       -> origin/main
Updating ee0d2ac..4fd4c67
Fast-forward
 .github/server-artisan-result.md                   | 93 +++++++++-------------
 .github/server-artisan-task.json                   |  8 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  8 ++
 3 files changed, 50 insertions(+), 59 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 7.
Progress: checked=1 matched=0 missing=0 current=VM722000015
+---------+-------------+---------------+------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                   | official_url                                                           |
+---------+-------------+---------------+------------------------+------------------------------------------------------------------------+
| 20660   | VM722000015 | Пресс-фитинги | Varmega VM722000015 15 | https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varme |
| 20661   | VM722000018 | Пресс-фитинги | Varmega VM722000018 18 | https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varme |
| 20662   | VM722000022 | Пресс-фитинги | Varmega VM722000022 22 | https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varme |
| 20663   | VM722000028 | Пресс-фитинги | Varmega VM722000028 28 | https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varme |
| 20664   | VM722000035 | Пресс-фитинги | Varmega VM722000035 35 | https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varme |
| 20665   | VM722000042 | Пресс-фитинги | Varmega VM722000042 42 | https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varme |
| 20666   | VM722000054 | Пресс-фитинги | Varmega VM722000054 54 | https://varmega.ru/product/truby-i-fitingi/zaglushka-rastrubnaya-varme |
+---------+-------------+---------------+------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 7     |
| matched          | 7     |
| written          | 7     |
| enriched         | 7     |
| images_found     | 28    |
| images_saved     | 28    |
| specs_found      | 70    |
| attributes_saved | 70    |
| category_changed | 0     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
