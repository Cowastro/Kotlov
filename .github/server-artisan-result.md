# Server Artisan Result

- Time: 2026-07-12 07:52:38 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:sync-ligmet --apply --brand=Ferrum --all-categories --link-existing-suggestions --min-suggestion-score=99.9`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7132445..ebc6236  main       -> origin/main
Updating 7132445..ebc6236
Fast-forward
 .github/server-artisan-result.md | 41 ++++++++++++++++++++--------------------
 .github/server-artisan-task.json |  6 +++---
 2 files changed, 24 insertions(+), 23 deletions(-)
APPLY: database will be updated.
Using latest Ligmet workbook from Drive folder: https://docs.google.com/spreadsheets/d/1KIhK4gt-FoD4HZMDYhDgLLsRnHIhg7kM/edit?rtpof=true&sd=true
Parsed 511 product rows for requested brands

+------------+--------+
| метрика    | кол-во |
+------------+--------+
| matched    | 8      |
| created    | 0      |
| retail_set | 8      |
| skipped    | 21     |
| errors     | 0      |
+------------+--------+

```
