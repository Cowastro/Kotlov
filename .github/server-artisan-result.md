# Server Artisan Result

- Time: 2026-07-12 17:40:31 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=2172010,VM52603,VM52604 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-user-links-1.log`
- Exit code: `1`

```text
From https://github.com/Cowastro/Kotlov
   f180afb..5d3816f  main       -> origin/main
Updating f180afb..5d3816f
Fast-forward
 .github/server-artisan-result.md                   | 202 ++++++++++++++-------
 .github/server-artisan-task.json                   |   8 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |  10 +
 3 files changed, 152 insertions(+), 68 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 3.
Progress: checked=1 matched=0 missing=0 current=2172010
  #20426 2172010 ERROR: HTTP request returned status code 403:


    <!DOCTYPE html><html lang="ru"><head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"><meta name (truncated...)

+---------+---------+---------------+-----------------------------------+------------------------------------------------------------------------+
| product | article | category      | name                              | official_url                                                           |
+---------+---------+---------------+-----------------------------------+------------------------------------------------------------------------+
| 20426   | 2172010 | Пресс-фитинги | Varmega 2172010 3/4"EK*20х2.8     | https://www.ozon.ru/product/soedinenie-rezbozazhimnoe-varmega-20x2-8-x |
| 20428   | VM52603 | Пресс-фитинги | Varmega VM52603 20х2.8-16х2.2/250 | https://belsklad.by/varmega-trojnik-s-trubkoj-slide-fit-20-16-250-dlja |
| 20429   | VM52604 | Пресс-фитинги | Varmega VM52604 16х2.2-20х2.8/250 | https://belsklad.by/varmega-trojnik-s-trubkoj-slide-fit-16-20-250-dlja |
+---------+---------+---------------+-----------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 3     |
| matched          | 3     |
| written          | 3     |
| enriched         | 2     |
| images_found     | 6     |
| images_saved     | 2     |
| specs_found      | 20    |
| attributes_saved | 20    |
| category_changed | 0     |
| missing          | 0     |
| errors           | 1     |
+------------------+-------+

```
