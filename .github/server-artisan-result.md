# Server Artisan Result

- Time: 2026-07-12 11:58:26 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:repair-varmega-source-urls --category=Пресс-фитинги --rn-profi-fallback --rn-profi-search-limit=10 --rn-profi-candidate-limit=3 --http-timeout=5 --offset=55 --limit=10`
- Log file: `storage/logs/varmega-official-source-repair.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   31664f2..9c49494  main       -> origin/main
Updating 31664f2..9c49494
Fast-forward
 .github/server-artisan-result.md | 56 ++++++++++++++--------------------------
 .github/server-artisan-task.json |  4 +--
 2 files changed, 22 insertions(+), 38 deletions(-)
DRY RUN: Varmega official source URLs will be previewed.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 10.
Progress: checked=1 matched=0 missing=0 current=VM705004208
Progress: checked=10 matched=0 missing=9 current=VM706002804
+---------+-------------+---------------+-------------------------------+--------------+
| product | article     | category      | name                          | official_url |
+---------+-------------+---------------+-------------------------------+--------------+
| 20506   | VM705004208 | Пресс-фитинги | Varmega VM705004208 42x1 1/2" | -            |
| 20507   | VM705005409 | Пресс-фитинги | Varmega VM705005409 54x2"     | -            |
| 20508   | VM706001504 | Пресс-фитинги | Varmega VM706001504 15x1/2"   | -            |
| 20509   | VM706001505 | Пресс-фитинги | Varmega VM706001505 15x3/4"   | -            |
| 20510   | VM706001804 | Пресс-фитинги | Varmega VM706001804 18x1/2"   | -            |
| 20511   | VM706001805 | Пресс-фитинги | Varmega VM706001805 18x3/4"   | -            |
| 20512   | VM706002204 | Пресс-фитинги | Varmega VM706002204 22x1/2"   | -            |
| 20513   | VM706002205 | Пресс-фитинги | Varmega VM706002205 22x3/4"   | -            |
| 20514   | VM706002206 | Пресс-фитинги | Varmega VM706002206 22x1"     | -            |
| 20515   | VM706002804 | Пресс-фитинги | Varmega VM706002804 28x1/2"   | -            |
+---------+-------------+---------------+-------------------------------+--------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 10    |
| matched          | 0     |
| written          | 0     |
| enriched         | 0     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| missing          | 10    |
| errors           | 0     |
+------------------+-------+

```
