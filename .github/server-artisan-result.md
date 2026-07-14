# Server Artisan Result

- Time: 2026-07-14 12:10:36 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:inspect-price --brand=Prometall --limit=200`
- Log file: `storage/logs/inspect-prometall-prices.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   a5b07ad..310ea32  main       -> origin/main
Updating a5b07ad..310ea32
Fast-forward
 .github/server-artisan-result.md | 82 +++++++++++++++++++++++-----------------
 .github/server-artisan-task.json |  6 +--
 2 files changed, 51 insertions(+), 37 deletions(-)
+-------+---------------+-----------+------------------------+---------------+----------+---------+----------------+-----------+-----+-------------------------------------------------+--------+------------------------------------------------------------------------+
| id    | sku           | brand     | category               | product_price | supplier | article | supplier_price | price_byn | qty | stock                                           | source | name                                                                   |
+-------+---------------+-----------+------------------------+---------------+----------+---------+----------------+-----------+-----+-------------------------------------------------+--------+------------------------------------------------------------------------+
| 11203 | PS-011.203    | Prometall | Дровяные печи (банные) | 3520.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь банная ProMetall Атмосфера М сетка                                |
| 11204 | PS-011.204    | Prometall | Дровяные печи (банные) | 3310.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь банная ProMetall Атмосфера М сетка нержавеющая                    |
| 11206 | PS-011.206    | Prometall | Дровяные печи (банные) | 4060.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь банная ProMetall Атмосфера L сетка                                |
| 11207 | PS-011.207    | Prometall | Дровяные печи (банные) | 4130.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Банная печь ProMetall Атмосфера L сетка из нержавеющей стали           |
| 11208 | PS-011.208    | Prometall | Дровяные печи (банные) | 4540.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь банная Атмосфера XL сетка нержавейка                              |
| 11209 | PS-011.209    | Prometall | Дровяные печи (банные) | 7320.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь банная ProMetall Атмосфера L ламели Окаменевшее дерево перенесенн |
| 11210 | PS-011.210    | Prometall | Дровяные печи (банные) | 6290.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Банная печь ProMetall Атмосфера L Змеевик наборный                     |
| 11211 | PS-011.211    | Prometall | Дровяные печи (банные) | 6900.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Банная печь ProMetall Атмосфера 2XL c сеткой для камней из нержавеющей |
| 11221 | PS-011.221    | Prometall | Печи-камины            | 1690.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь-камин отопительно-варочная ProMetall Бахта черная                 |
| 11246 | PS-011.246    | Prometall | Печи-камины            | 1300.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь-камин Бахтинка                                                    |
| 11247 | PS-011.247    | Prometall | Печи-камины            | 0.00          | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Печь-камин Маэстро                                                     |
| 11494 | PS-011.494    | Prometall | Печи-камины            | 1000.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Дымоход Конвектор Лира D115/130 черный для печи Бахта                  |
| 11692 | PS-011.692    | Prometall | Печи-камины            | 975.00        | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Отопительно-варочная печь Тайга PRO                                    |
| 11693 | PS-011.693    | Prometall | Печи-камины            | 130.00        | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Полки для подогрева к печи Тайга Pro                                   |
| 12277 | PS-012.277    | Prometall | Печи-камины            | 2887.00       | -        | -       | -              | -         | -   | product=no supplier=- active=no archived=yes    | -      | Отопительная печь-камин Маэстро II                                     |
| 16927 | KOTLOV-004649 | Prometall | Дровяные печи (банные) | 3565.00       | bania    | p0503   | 2892.36        | 2892.36   | -   | product=yes supplier=yes active=yes archived=no | -      | Печь банная "Атмосфера M" сетка из прутка                              |
+-------+---------------+-----------+------------------------+---------------+----------+---------+----------------+-----------+-----+-------------------------------------------------+--------+------------------------------------------------------------------------+

```
