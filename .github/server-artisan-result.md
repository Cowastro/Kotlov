# Server Artisan Result

- Time: 2026-07-14 12:14:35 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:inspect-price --sku=KOTLOV-004649 --limit=10`
- Log file: `storage/logs/verify-prometall-price.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   5dc362d..7926292  main       -> origin/main
Updating 5dc362d..7926292
Fast-forward
 .github/server-artisan-result.md | 44 ++++++++++++++--------------------------
 .github/server-artisan-task.json |  8 ++++----
 2 files changed, 19 insertions(+), 33 deletions(-)
+-------+---------------+-----------+------------------------+---------------+----------+---------+----------------+-----------+-----+-------------------------------------------------+--------+-------------------------------------------+
| id    | sku           | brand     | category               | product_price | supplier | article | supplier_price | price_byn | qty | stock                                           | source | name                                      |
+-------+---------------+-----------+------------------------+---------------+----------+---------+----------------+-----------+-----+-------------------------------------------------+--------+-------------------------------------------+
| 16927 | KOTLOV-004649 | Prometall | Дровяные печи (банные) | 3770.00       | bania    | p0503   | 2827.50        | 2827.50   | -   | product=yes supplier=yes active=yes archived=no | -      | Печь банная "Атмосфера M" сетка из прутка |
+-------+---------------+-----------+------------------------+---------------+----------+---------+----------------+-----------+-----+-------------------------------------------------+--------+-------------------------------------------+

```
