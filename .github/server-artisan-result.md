# Server Artisan Result

- Time: 2026-07-11 19:52:25 UTC
- Task: `artisan-dry-run`
- Artisan args: `brands:discover-teplodvor-logos --limit=0`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   6490f45..465be6f  main       -> origin/main
Updating 6490f45..465be6f
Fast-forward
 .github/server-artisan-result.md                   | 122 ++++-----------------
 .github/server-artisan-task.json                   |   2 +-
 .../DiscoverTeplodvorBrandLogosCommand.php         |  41 ++++++-
 3 files changed, 60 insertions(+), 105 deletions(-)
DRY RUN: no brand logos will be changed.
Source: https://www.teplodvor.by/brands/
+------------------------+-------+
| metric                 | count |
+------------------------+-------+
| checked                | 200   |
| matched                | 2     |
| downloaded             | 0     |
| updated                | 0     |
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
