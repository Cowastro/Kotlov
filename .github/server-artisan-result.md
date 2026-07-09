# Server Artisan Result

- Time: 2026-07-09 19:42:03 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:sync-akvatermex --apply --available-only --only-linked --prefer-teplodvor-source --brand=Thermex,Edisson,Eurostar --limit=80`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
APPLY: Akvatermex price list will write changes.
Downloading Akvatermex Google Sheet: https://docs.google.com/spreadsheets/d/19G0Mei9zkr8iFzTJeYKFHd4IYeIfJwH_7dDAYhTp5jk/export?format=xlsx
+----------------+-------+
| metric         | count |
+----------------+-------+
| matched        | 63    |
| created        | 0     |
| updated_retail | 0     |
| skipped        | 17    |
| errors         | 0     |
+----------------+-------+

```
