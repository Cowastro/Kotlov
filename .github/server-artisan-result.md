# Server Artisan Result

- Time: 2026-07-12 15:21:01 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --article-prefix=VM706 --http-timeout=8 --limit=0 --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents`
- Log file: `storage/logs/varmega-vm706-source-repair-apply-v2.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   c0bff5c..c608f7d  main       -> origin/main
Updating c0bff5c..c608f7d
Fast-forward
 .github/server-artisan-result.md       | 108 +++++++++++++++++++--------------
 .github/server-artisan-task.json       |   8 +--
 app/Services/ProductSourceEnricher.php |  65 +++++++++++++++++++-
 3 files changed, 129 insertions(+), 52 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6810 URLs.
RN-Profi Varmega links to check: 15.
Progress: checked=1 matched=0 missing=0 current=VM706001504
Progress: checked=10 matched=9 missing=0 current=VM706002806
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| product | article     | category      | name                          | official_url                                                           |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
| 20508   | VM706001504 | Пресс-фитинги | Varmega VM706001504 15x1/2"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20509   | VM706001505 | Пресс-фитинги | Varmega VM706001505 15x3/4"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20510   | VM706001804 | Пресс-фитинги | Varmega VM706001804 18x1/2"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20511   | VM706001805 | Пресс-фитинги | Varmega VM706001805 18x3/4"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20512   | VM706002204 | Пресс-фитинги | Varmega VM706002204 22x1/2"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20513   | VM706002205 | Пресс-фитинги | Varmega VM706002205 22x3/4"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20514   | VM706002206 | Пресс-фитинги | Varmega VM706002206 22x1"     | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20515   | VM706002804 | Пресс-фитинги | Varmega VM706002804 28x1/2"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20516   | VM706002805 | Пресс-фитинги | Varmega VM706002805 28x3/4"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20517   | VM706002806 | Пресс-фитинги | Varmega VM706002806 28x1"     | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20518   | VM706003505 | Пресс-фитинги | Varmega VM706003505 35x3/4"   | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20519   | VM706003506 | Пресс-фитинги | Varmega VM706003506 35x1"     | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20520   | VM706003507 | Пресс-фитинги | Varmega VM706003507 35x1 1/4" | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20521   | VM706004208 | Пресс-фитинги | Varmega VM706004208 42x1 1/2" | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
| 20522   | VM706005409 | Пресс-фитинги | Varmega VM706005409 54x2"     | https://rn-profi.by/mufta-rastrubnaya-varmega-inox-press-s-naruzhnoj-r |
+---------+-------------+---------------+-------------------------------+------------------------------------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 15    |
| matched          | 15    |
| written          | 15    |
| enriched         | 15    |
| images_found     | 60    |
| images_saved     | 0     |
| specs_found      | 90    |
| attributes_saved | 90    |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
