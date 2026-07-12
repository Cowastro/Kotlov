# Server Artisan Result

- Time: 2026-07-12 18:18:33 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:repair-varmega-source-urls --apply --rn-profi-fallback --article-prefix=VM16606,VM16624,VM16627,VM16628,VM16629,VM16644,VM16647,VM16648,VM16649 --fix-category --category-slug=predokhranitelnaya-i-reguliruyushchaya-armatura --enrich --replace-specs --min-specs-to-replace=1 --overwrite-images --skip-documents --limit=0 --sleep=1000`
- Log file: `storage/logs/repair-varmega-vm166-safety-valves.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   ffe90f9..2d01c6f  main       -> origin/main
Updating ffe90f9..2d01c6f
Fast-forward
 .github/server-artisan-result.md                   | 175 ++++++++++++++++-----
 .github/server-artisan-task.json                   |   8 +-
 .../Commands/RepairVarmegaSourceUrlsCommand.php    |   2 +
 3 files changed, 141 insertions(+), 44 deletions(-)
APPLY: Varmega official source URLs will be written.
Official Varmega article index: 6671 URLs.
RN-Profi Varmega links to check: 9.
Progress: checked=1 matched=0 missing=0 current=VM16606
+---------+---------+-----------------+-------------------------------------+-----------------------------------------------+
| product | article | category        | name                                | official_url                                  |
+---------+---------+-----------------+-------------------------------------+-----------------------------------------------+
| 20720   | VM16606 | Котлы отопления | Varmega VM16606 1/2", 4 бар         | https://rn-profi.by/klapan-predokhranitelnyj  |
| 20721   | VM16624 | Котлы отопления | Varmega VM16624 1/2" х 3/4", 3 бар  | https://rn-profi.by/klapan-predokhranitelnyj- |
| 20722   | VM16627 | Котлы отопления | Varmega VM16627 1/2" х 3/4", 6 бар  | https://rn-profi.by/klapan-predokhranitelnyj- |
| 20723   | VM16628 | Котлы отопления | Varmega VM16628 1/2" х 3/4", 8 бар  | https://rn-profi.by/klapan-predokhranitelnyj- |
| 20724   | VM16629 | Котлы отопления | Varmega VM16629 1/2" х 3/4", 10 бар | https://rn-profi.by/klapan-predokhranitelnyj- |
| 20725   | VM16644 | Котлы отопления | Varmega VM16644 3/4" х 1", 3 бар    | https://rn-profi.by/klapan-predokhranitelnyj- |
| 20726   | VM16647 | Котлы отопления | Varmega VM16647 3/4" х 1", 6 бар    | https://rn-profi.by/klapan-predokhranitelnyj- |
| 20727   | VM16648 | Котлы отопления | Varmega VM16648 3/4" х 1", 8 бар    | https://rn-profi.by/klapan-predokhranitelnyj- |
| 20728   | VM16649 | Котлы отопления | Varmega VM16649 3/4" х 1", 10 бар   | https://rn-profi.by/klapan-predokhranitelnyj- |
+---------+---------+-----------------+-------------------------------------+-----------------------------------------------+
+------------------+-------+
| metric           | count |
+------------------+-------+
| checked          | 9     |
| matched          | 9     |
| written          | 9     |
| enriched         | 9     |
| images_found     | 36    |
| images_saved     | 9     |
| specs_found      | 9     |
| attributes_saved | 9     |
| category_changed | 9     |
| missing          | 0     |
| errors           | 0     |
+------------------+-------+

```
