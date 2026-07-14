# Server Artisan Result

- Time: 2026-07-14 12:09:09 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:inspect-price --brand=Plamen --limit=200`
- Log file: `storage/logs/inspect-plamen-prices.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7f55c6d..a5b07ad  main       -> origin/main
Updating 7f55c6d..a5b07ad
Fast-forward
 .github/server-artisan-result.md                   |  28 +++---
 .github/server-artisan-task.json                   |   8 +-
 ...4_140000_update_prometall_and_plamen_prices.php | 110 +++++++++++++++++++++
 3 files changed, 128 insertions(+), 18 deletions(-)
 create mode 100644 database/migrations/2026_07_14_140000_update_prometall_and_plamen_prices.php
+-------+------------+--------+------------------------------+---------------+----------+---------+----------------+-----------+-----+-----------------------------------------------+--------+------------------------------------------+
| id    | sku        | brand  | category                     | product_price | supplier | article | supplier_price | price_byn | qty | stock                                         | source | name                                     |
+-------+------------+--------+------------------------------+---------------+----------+---------+----------------+-----------+-----+-----------------------------------------------+--------+------------------------------------------+
| 7448  | PS-007.448 | Plamen | Печи дровяные (отопительные) | 3040.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Топка Plamen Barun 1                     |
| 7449  | PS-007.449 | Plamen | Каминные топки               | 4800.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Каминная топка Plamen Barun Insert Termo |
| 7451  | PS-007.451 | Plamen | Печи-камины                  | 1760.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Barun                  |
| 7452  | PS-007.452 | Plamen | Печи-камины                  | 2495.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Barun Termo            |
| 7453  | PS-007.453 | Plamen | Печи-камины                  | 3580.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Glas Franklin          |
| 7454  | PS-007.454 | Plamen | Каминные топки               | 3280.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Каминная топка Plamen Vesta Insert       |
| 7455  | PS-007.455 | Plamen | Печи-камины                  | 5800.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Tena Termo             |
| 7456  | PS-007.456 | Plamen | Печи-камины                  | 3400.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Authentic 50           |
| 7457  | PS-007.457 | Plamen | Печи-камины                  | 2675.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Eco Minimal 50         |
| 7458  | PS-007.458 | Plamen | Печи-камины                  | 5000.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Nera N                 |
| 7459  | PS-007.459 | Plamen | Печи-камины                  | 1485.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Julia                  |
| 7460  | PS-007.460 | Plamen | Печи-камины                  | 1420.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Aurora                 |
| 7461  | PS-007.461 | Plamen | Печи-камины                  | 1645.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Vesta                  |
| 7462  | PS-007.462 | Plamen | Печи-камины                  | 3500.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Laguna                 |
| 7463  | PS-007.463 | Plamen | Печи-камины                  | 1780.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Maestral N             |
| 7464  | PS-007.464 | Plamen | Печи-камины                  | 1210.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Dora 8 N               |
| 7465  | PS-007.465 | Plamen | Печи-камины                  | 0.00          | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Dora 10 N              |
| 7466  | PS-007.466 | Plamen | Печи-камины                  | 2890.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Marina                 |
| 7467  | PS-007.467 | Plamen | Печи-камины                  | 1625.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Hana                   |
| 7468  | PS-007.468 | Plamen | Печи-камины                  | 3400.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Authentic 35           |
| 7469  | PS-007.469 | Plamen | Печи-камины                  | 2595.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Eco Minimal 35         |
| 7568  | PS-007.568 | Plamen | Печи-камины                  | 3950.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Tena N                 |
| 7569  | PS-007.569 | Plamen | Печи-камины                  | 3250.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Amity 3 N              |
| 7570  | PS-007.570 | Plamen | Печи-камины                  | 1500.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Trenk                  |
| 7571  | PS-007.571 | Plamen | Печи-камины                  | 5290.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Termo Glas правая      |
| 7572  | PS-007.572 | Plamen | Печи-камины                  | 4440.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь для кухни на дровах Plamen 850 Glas |
| 7573  | PS-007.573 | Plamen | Печи-камины                  | 4500.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Slavonak               |
| 7574  | PS-007.574 | Plamen | Печи-камины                  | 0.00          | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Calorex 60             |
| 8736  | PS-008.736 | Plamen | Каминные топки               | 3400.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Каминная топка Plamen Barun 1            |
| 10066 | PS-010.066 | Plamen | Печи-камины                  | 3750.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Aria                   |
| 10067 | PS-010.067 | Plamen | Печи-камины                  | 3600.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Gala                   |
| 10547 | PS-010.547 | Plamen | Печи-камины                  | 1999.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь-камин Plamen Tara                   |
| 11774 | PS-011.774 | Plamen | Печи-камины                  | 4050.00       | -        | -       | -              | -         | -   | product=no supplier=- active=yes archived=yes | -      | Печь камин Plamen Alberto                |
+-------+------------+--------+------------------------------+---------------+----------+---------+----------------+-----------+-----+-----------------------------------------------+--------+------------------------------------------+

```
