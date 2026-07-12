# Server Artisan Result

- Time: 2026-07-12 16:08:52 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --supplier=rn-profi --brand=Varmega --article-prefix=VM355 --active-only --not-archived --issues=no_photo,no_content,no_short,low_attrs,no_source --max-attrs=4 --limit=80`
- Log file: `storage/logs/audit-varmega-vm355-cabinets.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   c75481f..ee0d2ac  main       -> origin/main
Updating c75481f..ee0d2ac
Fast-forward
 .github/server-artisan-result.md | 86 ++++++++++++++++++++--------------------
 .github/server-artisan-task.json |  8 ++--
 2 files changed, 47 insertions(+), 47 deletions(-)
Products with content-health issues: 0
Showing rows: 0 (limit 80)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 0        |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 0        |
| no_source  | 0        |
+------------+----------+
By supplier
+------+----------+----------+------------+-----------+
| Name | Products | No photo | No content | Low attrs |
+------+----------+----------+------------+-----------+
By brand
+------+----------+----------+------------+-----------+
| Name | Products | No photo | No content | Low attrs |
+------+----------+----------+------------+-----------+
By category
+------+----------+----------+------------+-----------+
| Name | Products | No photo | No content | Low attrs |
+------+----------+----------+------------+-----------+

+----+-----+-------+----------+-----------+-------+--------+----------------+---------+
| ID | SKU | Brand | Category | Suppliers | Attrs | Issues | Source domains | Product |
+----+-----+-------+----------+-----------+-------+--------+----------------+---------+

```
