# Server Artisan Result

- Time: 2026-07-12 15:04:18 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --supplier=rn-profi --brand=Varmega --category=Пресс-фитинги --active-only --not-archived --issues=no_photo,low_attrs,no_source --max-attrs=2 --article-prefix=VM706 --limit=80`
- Log file: `storage/logs/varmega-vm706-health-audit.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   93dff01..c0bff5c  main       -> origin/main
Updating 93dff01..c0bff5c
Fast-forward
 .github/server-artisan-result.md                   | 29 +++++++++++-----------
 .github/server-artisan-task.json                   |  8 +++---
 .../Commands/AuditProductContentHealthCommand.php  |  6 +++++
 3 files changed, 24 insertions(+), 19 deletions(-)
Products with content-health issues: 15
Showing rows: 15 (limit 80)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 15       |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 15       |
| no_source  | 0        |
+------------+----------+
By supplier
+----------+----------+----------+------------+-----------+
| Name     | Products | No photo | No content | Low attrs |
+----------+----------+----------+------------+-----------+
| rn-profi | 15       | 15       | 0          | 15        |
+----------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Varmega | 15       | 15       | 0          | 15        |
+---------+----------+----------+------------+-----------+
By category
+---------------+----------+----------+------------+-----------+
| Name          | Products | No photo | No content | Low attrs |
+---------------+----------+----------+------------+-----------+
| Пресс-фитинги | 15       | 15       | 0          | 15        |
+---------------+----------+----------+------------+-----------+

+-------+---------------+---------+---------------+-----------+-------+--------------------+----------------+-------------------------------+
| ID    | SKU           | Brand   | Category      | Suppliers | Attrs | Issues             | Source domains | Product                       |
+-------+---------------+---------+---------------+-----------+-------+--------------------+----------------+-------------------------------+
| 20508 | KOTLOV-005674 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001504 15x1/2"   |
| 20509 | KOTLOV-005675 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001505 15x3/4"   |
| 20510 | KOTLOV-005676 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001804 18x1/2"   |
| 20511 | KOTLOV-005677 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001805 18x3/4"   |
| 20512 | KOTLOV-005678 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002204 22x1/2"   |
| 20513 | KOTLOV-005679 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002205 22x3/4"   |
| 20514 | KOTLOV-005680 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002206 22x1"     |
| 20515 | KOTLOV-005681 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002804 28x1/2"   |
| 20516 | KOTLOV-005682 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002805 28x3/4"   |
| 20517 | KOTLOV-005683 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002806 28x1"     |
| 20518 | KOTLOV-005684 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003505 35x3/4"   |
| 20519 | KOTLOV-005685 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003506 35x1"     |
| 20520 | KOTLOV-005686 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003507 35x1 1/4" |
| 20521 | KOTLOV-005687 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706004208 42x1 1/2" |
| 20522 | KOTLOV-005688 | Varmega | Пресс-фитинги | rn-profi  | 1     | no_photo,low_attrs | rn-profi.by    | Varmega VM706005409 54x2"     |
+-------+---------------+---------+---------------+-----------+-------+--------------------+----------------+-------------------------------+

```
