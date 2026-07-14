# Server Artisan Result

- Time: 2026-07-14 12:15:48 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:inspect-price --sku=PS-007.453 --limit=10`
- Log file: `storage/logs/verify-plamen-price.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7926292..0e5256b  main       -> origin/main
Updating 7926292..0e5256b
Fast-forward
 .github/server-artisan-result.md | 29 ++++++++++++++---------------
 .github/server-artisan-task.json |  6 +++---
 2 files changed, 17 insertions(+), 18 deletions(-)
+------+------------+--------+-------------+---------------+----------+---------+----------------+-----------+-----+-----------------------------------------------+--------+---------------------------------+
| id   | sku        | brand  | category    | product_price | supplier | article | supplier_price | price_byn | qty | stock                                         | source | name                            |
+------+------------+--------+-------------+---------------+----------+---------+----------------+-----------+-----+-----------------------------------------------+--------+---------------------------------+
| 7453 | PS-007.453 | Plamen | Печи-камины | 3740.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Glas Franklin |
+------+------------+--------+-------------+---------------+----------+---------+----------------+-----------+-----+-----------------------------------------------+--------+---------------------------------+

```
