# Server Artisan Result

- Time: 2026-07-12 11:55:29 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --category=Пресс-фитинги --rn-profi-fallback --rn-profi-search-limit=5 --rn-profi-candidate-limit=3 --http-timeout=5 --limit=5`
- Log file: `storage/logs/varmega-official-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   4318293..31664f2  main       -> origin/main
Updating 4318293..31664f2
Fast-forward
 .github/server-artisan-task.json | 4 ++--
 1 file changed, 2 insertions(+), 2 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 5.
Progress: checked=1 matched=0 missing=0 current=2171610
+---------+---------+---------------+-----------------------------------+--------------+
| product | article | category      | name                              | official_url |
+---------+---------+---------------+-----------------------------------+--------------+
| 20425   | 2171610 | Пресс-фитинги | Varmega 2171610 3/4"EK*16х2.2     | -            |
| 20426   | 2172010 | Пресс-фитинги | Varmega 2172010 3/4"EK*20х2.8     | -            |
| 20427   | VM52501 | Пресс-фитинги | Varmega VM52501 16х2.2/250        | -            |
| 20428   | VM52603 | Пресс-фитинги | Varmega VM52603 20х2.8-16х2.2/250 | -            |
| 20429   | VM52604 | Пресс-фитинги | Varmega VM52604 16х2.2-20х2.8/250 | -            |
+---------+---------+---------------+-----------------------------------+--------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 5     |
| matched          | 0     |
| written          | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| missing          | 5     |
| errors           | 0     |
+------------------+-------+

```
