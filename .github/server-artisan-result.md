# Server Artisan Result

- Time: 2026-07-11 19:55:13 UTC
- Task: `artisan-apply`
- Artisan args: `brands:discover-teplodvor-logos --apply --limit=0`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   465be6f..8d4bceb  main       -> origin/main
Updating 465be6f..8d4bceb
Fast-forward
 .github/server-artisan-result.md | 23 +++++++++++++++--------
 .github/server-artisan-task.json |  6 +++---
 2 files changed, 18 insertions(+), 11 deletions(-)
APPLY: missing/broken brand logos will be downloaded.
Source: https://www.teplodvor.by/brands/
+------------------------+-------+
| metric                 | count |
+------------------------+-------+
| checked                | 200   |
| matched                | 2     |
| downloaded             | 2     |
| updated                | 2     |
| skipped_existing       | 129   |
| skipped_missing_source | 69    |
| errors                 | 0     |
+------------------------+-------+
+----------+-------------+----------+----------------------------------------------------------------------------+
| brand_id | brand       | old_logo | source                                                                     |
+----------+-------------+----------+----------------------------------------------------------------------------+
| 219      | BRV-MODVLVS | broken   | https://www.teplodvor.by/userfls/shop/small/14/138003_brv.png              |
| 370      | Бренд 370   | broken   | https://www.teplodvor.by/userfls/shop/small/13/124862_federica-bugatti.png |
+----------+-------------+----------+----------------------------------------------------------------------------+

```
