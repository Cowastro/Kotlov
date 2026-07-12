# Server Artisan Result

- Time: 2026-07-12 08:41:56 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --active-only --not-archived --brand=Varmega --max-attrs=5 --limit=120 --csv=storage/app/reports/content-health/content-health-varmega.csv`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7dee1bc..8d9fb6e  main       -> origin/main
Updating 7dee1bc..8d9fb6e
Fast-forward
 .github/server-artisan-result.md | 430 ++++++++++++++-------------------------
 .github/server-artisan-task.json |   4 +-
 2 files changed, 155 insertions(+), 279 deletions(-)
Products with content-health issues: 314
Showing rows: 120 (limit 120)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 245      |
| no_content | 1        |
| no_short   | 1        |
| low_attrs  | 312      |
| no_source  | 0        |
+------------+----------+
By supplier
+-----------+----------+----------+------------+-----------+
| Name      | Products | No photo | No content | Low attrs |
+-----------+----------+----------+------------+-----------+
| rn-profi  | 301      | 245      | 0          | 299       |
| rusklimat | 13       | 0        | 1          | 13        |
+-----------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Varmega | 314      | 245      | 1          | 312       |
+---------+----------+----------+------------+-----------+
By category
+-------------------------------------------+----------+----------+------------+-----------+
| Name                                      | Products | No photo | No content | Low attrs |
+-------------------------------------------+----------+----------+------------+-----------+
| Пресс-фитинги                             | 225      | 213      | 0          | 223       |
| Котлы отопления                           | 61       | 32       | 0          | 61        |
| Резьбовые фитинги                         | 13       | 0        | 0          | 13        |
| Автоматика и терморегуляторы              | 5        | 0        | 0          | 5         |
| Краны и запорная арматура                 | 4        | 0        | 1          | 4         |
| Комплекты подключения                     | 3        | 0        | 0          | 3         |
| Трубы и фитинги                           | 1        | 0        | 0          | 1         |
| Предохранительная и регулирующая арматура | 1        | 0        | 0          | 1         |
| Радиаторная арматура                      | 1        | 0        | 0          | 1         |
+-------------------------------------------+----------+----------+------------+-----------+

+-------+---------------+---------+-------------------------------------------+-----------+-------+-------------------------------+----------------+------------------------------------------------------------+
| ID    | SKU           | Brand   | Category                                  | Suppliers | Attrs | Issues                        | Source domains | Product                                                    |
+-------+---------------+---------+-------------------------------------------+-----------+-------+-------------------------------+----------------+------------------------------------------------------------+
| 15409 | KOTLOV-003131 | Varmega | Краны и запорная арматура                 | rusklimat | 0     | low_attrs                     | rusklimat.by   | Клапан радиаторный VARMEGA запорный, прямой 1/2", VM10301  |
| 15418 | KOTLOV-003140 | Varmega | Краны и запорная арматура                 | rusklimat | 0     | low_attrs                     | rusklimat.by   | Клапан радиаторный VARMEGA 1/2" термостатический, углов... |
| 15427 | KOTLOV-003149 | Varmega | Краны и запорная арматура                 | rusklimat | 0     | low_attrs                     | rusklimat.by   | Клапан радиаторный VARMEGA 1/2" термостатический осевой... |
| 15428 | KOTLOV-003150 | Varmega | Краны и запорная арматура                 | rusklimat | 0     | no_content,no_short,low_attrs | rusklimat.by   | Клапан радиаторный VARMEGA 1/2" x 3/4"EK термостатическ... |
| 15430 | KOTLOV-003152 | Varmega | Автоматика и терморегуляторы              | rusklimat | 0     | low_attrs                     | rusklimat.by   | Головка термостатическая VARMEGA, VM110, M30х1.5, белая... |
| 15431 | KOTLOV-003153 | Varmega | Автоматика и терморегуляторы              | rusklimat | 0     | low_attrs                     | rusklimat.by   | Головка термостатическая VARMEGA M30х1.5 без датчика че... |
| 15436 | KOTLOV-003158 | Varmega | Автоматика и терморегуляторы              | rusklimat | 0     | low_attrs                     | rusklimat.by   | Головка термостатическая VARMEGA, серия VM112, M30х1.5,... |
| 15437 | KOTLOV-003159 | Varmega | Автоматика и терморегуляторы              | rusklimat | 0     | low_attrs                     | rusklimat.by   | Головка термостатическая VARMEGA M30х1.5 без датчика бе... |
| 15438 | KOTLOV-003160 | Varmega | Автоматика и терморегуляторы              | rusklimat | 0     | low_attrs                     | rusklimat.by   | Головка термостатическая VARMEGA M30х1.5 выносной датчи... |
| 15440 | KOTLOV-003162 | Varmega | Комплекты подключения                     | rusklimat | 0     | low_attrs                     | rusklimat.by   | Узел нижнего подключения VARMEGA 3/4"EKх3/4"EK НР-ВР уг... |
| 15441 | KOTLOV-003163 | Varmega | Комплекты подключения                     | rusklimat | 0     | low_attrs                     | rusklimat.by   | Узел нижнего подключения VARMEGA, НР-ВР 3/4"EKх3/4"EK, ... |
| 15442 | KOTLOV-003164 | Varmega | Комплекты подключения                     | rusklimat | 0     | low_attrs                     | rusklimat.by   | Узел нижнего подключения VARMEGA 3/4"EKх3/4"EK НР-ВР пр... |
| 15443 | KOTLOV-003165 | Varmega | Трубы и фитинги                           | rusklimat | 0     | low_attrs                     | rusklimat.by   | Ниппель переходной VARMEGA VM14401, 1/2"х3/4"EK, с прок... |
| 19812 | KOTLOV-004978 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Угол переходной Varmega VM09821, ВР-ВР, 3/4" х 1/2", хр... |
| 19813 | KOTLOV-004979 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Угол переходной Varmega VM09822, ВР-ВР, 1" х 1/2", хром... |
| 19814 | KOTLOV-004980 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с внутренней резьбой Varmega VM09831, ВР, 3/4"... |
| 19815 | KOTLOV-004981 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с внутренней резьбой Varmega VM09832, ВР, 1", ... |
| 19816 | KOTLOV-004982 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с наружной резьбой Varmega VM09841, НР, 3/4", ... |
| 19817 | KOTLOV-004983 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с наружной резьбой Varmega VM09842, НР, 1", хр... |
| 19818 | KOTLOV-004984 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Угол переходной Varmega VM09921, ВР-ВР, 3/4" х 1/2"        |
| 19819 | KOTLOV-004985 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Угол переходной Varmega VM09922, ВР-ВР, 1" х 1/2"          |
| 19820 | KOTLOV-004986 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с внутренней резьбой Varmega VM09931, ВР, 3/4"    |
| 19821 | KOTLOV-004987 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с внутренней резьбой Varmega VM09932, ВР, 1"      |
| 19822 | KOTLOV-004988 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с наружной резьбой Varmega VM09941, НР, 3/4"      |
| 19823 | KOTLOV-004989 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Заглушка с наружной резьбой Varmega VM09942, НР, 1"        |
| 20067 | KOTLOV-005233 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs                     | varmega.ru     | Латунный запирающий колпачок Varmega VM15978, для термо... |
| 20229 | KOTLOV-005395 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Соединение быстросъемное Varmega VM09601, 3/4", для рас... |
| 20287 | KOTLOV-005453 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM30701 16x2.0                                     |
| 20288 | KOTLOV-005454 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM30751 16x2.0                                     |
| 20337 | KOTLOV-005503 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM15975 24х30                                      |
| 20341 | KOTLOV-005507 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Varmega VM15906 1"                                         |
| 20350 | KOTLOV-005516 | Varmega | Котлы отопления                           | rn-profi  | 5     | low_attrs                     | rn-profi.by    | Varmega VM15980 1/2"                                       |
| 20351 | KOTLOV-005517 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM15981 M18x1.75                                   |
| 20352 | KOTLOV-005518 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Varmega VM15985 1/2"                                       |
| 20353 | KOTLOV-005519 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM19001 НО, M30х1.5                                |
| 20354 | KOTLOV-005520 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM19002 НЗ, M30х1.5                                |
| 20355 | KOTLOV-005521 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Varmega VM19221 230 В                                      |
| 20356 | KOTLOV-005522 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs                     | rn-profi.by    | Varmega VM19222 230 В                                      |
| 20357 | KOTLOV-005523 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM19501 3 м                                        |
| 20358 | KOTLOV-005524 | Varmega | Котлы отопления                           | rn-profi  | 5     | low_attrs                     | rn-profi.by    | Varmega VM19101 8 зон                                      |
| 20359 | KOTLOV-005525 | Varmega | Котлы отопления                           | rn-profi  | 5     | low_attrs                     | rn-profi.by    | Varmega VM19102 8 зон                                      |
| 20365 | KOTLOV-005531 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35500 ШРВ-0 1-3                                  |
| 20366 | KOTLOV-005532 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35501 ШРВ-1 4-5                                  |
| 20367 | KOTLOV-005533 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35502 ШРВ-2 6-7                                  |
| 20368 | KOTLOV-005534 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35503 ШРВ-3 8-10                                 |
| 20369 | KOTLOV-005535 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35504 ШРВ-4 11-12                                |
| 20370 | KOTLOV-005536 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35505 ШРВ-5 13-16                                |
| 20371 | KOTLOV-005537 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35506 ШРВ-6 17-18                                |
| 20372 | KOTLOV-005538 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35510 ШРН-0 1-3                                  |
| 20373 | KOTLOV-005539 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35511 ШРН-1 4-5                                  |
| 20374 | KOTLOV-005540 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35512 ШРН-2 6-7                                  |
| 20375 | KOTLOV-005541 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35513 ШРН-3 8-10                                 |
| 20376 | KOTLOV-005542 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35514 ШРН-4 11-12                                |
| 20377 | KOTLOV-005543 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35515 ШРН-5 13-16                                |
| 20378 | KOTLOV-005544 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35516 ШРН-6 17-18                                |
| 20379 | KOTLOV-005545 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35521 ШРНГ-1 4-5                                 |
| 20380 | KOTLOV-005546 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35522 ШРНГ-2 6-7                                 |
| 20381 | KOTLOV-005547 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35523 ШРНГ-3 8-10                                |
| 20382 | KOTLOV-005548 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35524 ШРНГ-4 11-12                               |
| 20383 | KOTLOV-005549 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35525 ШРНГ-5 13-16                               |
| 20384 | KOTLOV-005550 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM35526 ШРНГ-6 17-18                               |
| 20397 | KOTLOV-005563 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM09801 3/4" х 1/2"                                |
| 20398 | KOTLOV-005564 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM09802 1" х 1/2"                                  |
| 20399 | KOTLOV-005565 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM09811 3/4" х 1/2"                                |
| 20400 | KOTLOV-005566 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs                     | rn-profi.by    | Varmega VM09812 1" х 1/2"                                  |
| 20401 | KOTLOV-005567 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09851 3/4"                                       |
| 20402 | KOTLOV-005568 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09852 1"                                         |
| 20415 | KOTLOV-005581 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09901 3/4" х 1/2"                                |
| 20416 | KOTLOV-005582 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09902 1" х 1/2"                                  |
| 20417 | KOTLOV-005583 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09911 3/4" х 1/2"                                |
| 20418 | KOTLOV-005584 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09912 1" х 1/2"                                  |
| 20419 | KOTLOV-005585 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09951 3/4"                                       |
| 20420 | KOTLOV-005586 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs                     | rn-profi.by    | Varmega VM09952 1"                                         |
| 20425 | KOTLOV-005591 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega 2171610 3/4"EK*16х2.2                              |
| 20426 | KOTLOV-005592 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega 2172010 3/4"EK*20х2.8                              |
| 20427 | KOTLOV-005593 | Varmega | Пресс-фитинги                             | rn-profi  | 5     | low_attrs                     | rn-profi.by    | Varmega VM52501 16х2.2/250                                 |
| 20428 | KOTLOV-005594 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM52603 20х2.8-16х2.2/250                          |
| 20429 | KOTLOV-005595 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM52604 16х2.2-20х2.8/250                          |
| 20457 | KOTLOV-005623 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM701001818 18x18                                  |
| 20458 | KOTLOV-005624 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM701002222 22x22                                  |
| 20459 | KOTLOV-005625 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM701002828 28x28                                  |
| 20460 | KOTLOV-005626 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM701003535 35x35                                  |
| 20461 | KOTLOV-005627 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM701004242 42x42                                  |
| 20462 | KOTLOV-005628 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM701005454 54x54                                  |
| 20464 | KOTLOV-005630 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM702002215 22x15                                  |
| 20465 | KOTLOV-005631 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM702002218 22x18                                  |
| 20466 | KOTLOV-005632 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM702002815 28x15                                  |
| 20467 | KOTLOV-005633 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM702002822 28x22                                  |
| 20468 | KOTLOV-005634 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM702003528 35x28                                  |
| 20469 | KOTLOV-005635 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM702004235 42x35                                  |
| 20470 | KOTLOV-005636 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM702005442 54x42                                  |
| 20472 | KOTLOV-005638 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM703001818 18x18                                  |
| 20473 | KOTLOV-005639 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM703002222 22x22                                  |
| 20474 | KOTLOV-005640 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM703002828 28x28                                  |
| 20475 | KOTLOV-005641 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM703003535 35x35                                  |
| 20476 | KOTLOV-005642 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM703004242 42x42                                  |
| 20477 | KOTLOV-005643 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM703005454 54x54                                  |
| 20478 | KOTLOV-005644 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704001815 18ax15                                 |
| 20479 | KOTLOV-005645 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704002215 22ax15                                 |
| 20481 | KOTLOV-005647 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704002815 28ax15                                 |
| 20482 | KOTLOV-005648 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704002818 28ax18                                 |
| 20483 | KOTLOV-005649 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704002822 28ax22                                 |
| 20484 | KOTLOV-005650 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704003515 35ax15                                 |
| 20485 | KOTLOV-005651 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704003518 35ax18                                 |
| 20486 | KOTLOV-005652 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704003522 35ax22                                 |
| 20487 | KOTLOV-005653 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704003528 35ax28                                 |
| 20488 | KOTLOV-005654 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704004222 42ax22                                 |
| 20489 | KOTLOV-005655 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704004228 42ax28                                 |
| 20490 | KOTLOV-005656 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704004235 42ax35                                 |
| 20491 | KOTLOV-005657 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704005428 54ax28                                 |
| 20492 | KOTLOV-005658 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704005435 54ax35                                 |
| 20493 | KOTLOV-005659 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM704005442 54ax42                                 |
| 20495 | KOTLOV-005661 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705001804 18x1/2"                                |
| 20496 | KOTLOV-005662 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705001805 18x3/4"                                |
| 20497 | KOTLOV-005663 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705002204 22x1/2"                                |
| 20498 | KOTLOV-005664 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705002205 22x3/4"                                |
| 20499 | KOTLOV-005665 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705002206 22x1"                                  |
| 20500 | KOTLOV-005666 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705002804 28x1/2"                                |
| 20501 | KOTLOV-005667 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705002805 28x3/4"                                |
| 20502 | KOTLOV-005668 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs            | rn-profi.by    | Varmega VM705002806 28x1"                                  |
+-------+---------------+---------+-------------------------------------------+-----------+-------+-------------------------------+----------------+------------------------------------------------------------+
CSV written: /var/www/h209767/data/www/new.kotlov.by/storage/app/reports/content-health/content-health-varmega.csv

```
