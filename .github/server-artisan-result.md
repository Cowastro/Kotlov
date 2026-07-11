# Server Artisan Result

- Time: 2026-07-11 08:27:33 UTC
- Task: `artisan-dry-run`
- Artisan args: `catalog:audit-media --type=categories --only-with-products --missing-only --limit=120`
- Log file: `storage/logs/server-artisan-category-media.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   126af4c..a994647  main       -> origin/main
Updating 126af4c..a994647
Fast-forward
 .github/server-artisan-result.md | 53 ++++++++++++++++++++++++----------------
 .github/server-artisan-task.json |  2 +-
 app/Models/Category.php          |  9 +++++++
 3 files changed, 42 insertions(+), 22 deletions(-)
Categories
+----------+-------+
| metric   | count |
+----------+-------+
| checked  | 108   |
| missing  | 0     |
| broken   | 0     |
| fallback | 108   |
| ok       | 0     |
+----------+-------+

```
