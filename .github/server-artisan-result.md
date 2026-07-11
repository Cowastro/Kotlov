# Server Artisan Result

- Time: 2026-07-11 15:06:37 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-specs-rusklimat --active-only --brand=Royal --limit=12 --sleep=300`
- Log file: `storage/logs/server-artisan-rusklimat-royal-specs-dry.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   0864876..284c929  main       -> origin/main
Updating 0864876..284c929
Fast-forward
 .github/server-artisan-result.md                   | 267 +++++----------------
 .github/server-artisan-task.json                   |   2 +-
 .../Commands/EnrichSpecsRusklimatCommand.php       |  59 ++++-
 3 files changed, 115 insertions(+), 213 deletions(-)
DRY RUN: nothing will be written.

Products without specs: 4 (processing 4, offset 0)

id=14324 Royal Thermo ТЭН ESH 2,0 кВт для бойлера 1 1/2"
  page not found on trusted sources

id=16071 Трап душевой Royal Thermo с сухим затвором Compact Line 
  page not found on trusted sources

id=16072 Трап душевой Royal Thermo с сухим затвором Compact Line 
  page not found on trusted sources

id=16073 Трап душевой Royal Thermo с сухим затвором Compact Line 
  page not found on trusted sources

+-------------+-------+
| metric      | count |
+-------------+-------+
| processed   | 4     |
| page_found  | 0     |
| specs_saved | 0     |
| short_saved | 0     |
| not_found   | 4     |
| errors      | 0     |
+-------------+-------+

```
