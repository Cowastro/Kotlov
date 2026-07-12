# Server Artisan Result

- Time: 2026-07-12 18:37:06 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --supplier=rn-profi --brand=Varmega --active-only --not-archived --issues=no_photo,low_attrs --max-attrs=4 --limit=220`
- Log file: `storage/logs/audit-varmega-after-vm04302-filter.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   1daa53e..d406635  main       -> origin/main
Updating 1daa53e..d406635
Fast-forward
 .github/server-artisan-result.md | 171 ++++++++-------------------------------
 .github/server-artisan-task.json |   8 +-
 2 files changed, 39 insertions(+), 140 deletions(-)
Products with content-health issues: 87
Showing rows: 87 (limit 220)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 6        |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 87       |
| no_source  | 0        |
+------------+----------+
By supplier
+----------+----------+----------+------------+-----------+
| Name     | Products | No photo | No content | Low attrs |
+----------+----------+----------+------------+-----------+
| rn-profi | 87       | 6        | 0          | 87        |
+----------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Varmega | 87       | 6        | 0          | 87        |
+---------+----------+----------+------------+-----------+
By category
+-------------------------------------------+----------+----------+------------+-----------+
| Name                                      | Products | No photo | No content | Low attrs |
+-------------------------------------------+----------+----------+------------+-----------+
| Пресс-фитинги                             | 34       | 3        | 0          | 34        |
| Котлы отопления                           | 28       | 3        | 0          | 28        |
| Резьбовые фитинги                         | 13       | 0        | 0          | 13        |
| Предохранительная и регулирующая арматура | 10       | 0        | 0          | 10        |
| Радиаторная арматура                      | 1        | 0        | 0          | 1         |
| Фильтры                                   | 1        | 0        | 0          | 1         |
+-------------------------------------------+----------+----------+------------+-----------+

+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+-------------------+------------------------------------------------------------+
| ID    | SKU           | Brand   | Category                                  | Suppliers | Attrs | Issues             | Source domains    | Product                                                    |
+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+-------------------+------------------------------------------------------------+
| 19812 | KOTLOV-004978 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Угол переходной Varmega VM09821, ВР-ВР, 3/4" х 1/2", хр... |
| 19813 | KOTLOV-004979 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Угол переходной Varmega VM09822, ВР-ВР, 1" х 1/2", хром... |
| 19814 | KOTLOV-004980 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с внутренней резьбой Varmega VM09831, ВР, 3/4"... |
| 19815 | KOTLOV-004981 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с внутренней резьбой Varmega VM09832, ВР, 1", ... |
| 19816 | KOTLOV-004982 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с наружной резьбой Varmega VM09841, НР, 3/4", ... |
| 19817 | KOTLOV-004983 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с наружной резьбой Varmega VM09842, НР, 1", хр... |
| 19818 | KOTLOV-004984 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Угол переходной Varmega VM09921, ВР-ВР, 3/4" х 1/2"        |
| 19819 | KOTLOV-004985 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Угол переходной Varmega VM09922, ВР-ВР, 1" х 1/2"          |
| 19820 | KOTLOV-004986 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с внутренней резьбой Varmega VM09931, ВР, 3/4"    |
| 19821 | KOTLOV-004987 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с внутренней резьбой Varmega VM09932, ВР, 1"      |
| 19822 | KOTLOV-004988 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с наружной резьбой Varmega VM09941, НР, 3/4"      |
| 19823 | KOTLOV-004989 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Заглушка с наружной резьбой Varmega VM09942, НР, 1"        |
| 20067 | KOTLOV-005233 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru        | Латунный запирающий колпачок Varmega VM15978, для термо... |
| 20229 | KOTLOV-005395 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 2     | low_attrs          | varmega.ru        | Соединение быстросъемное Varmega VM09601, 3/4", для рас... |
| 20287 | KOTLOV-005453 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM30701 16x2.0                                     |
| 20288 | KOTLOV-005454 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM30751 16x2.0                                     |
| 20337 | KOTLOV-005503 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM15975 24х30                                      |
| 20341 | KOTLOV-005507 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru        | Varmega VM15906 1"                                         |
| 20351 | KOTLOV-005517 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM15981 M18x1.75                                   |
| 20352 | KOTLOV-005518 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru        | Varmega VM15985 1/2"                                       |
| 20353 | KOTLOV-005519 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM19001 НО, M30х1.5                                |
| 20354 | KOTLOV-005520 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM19002 НЗ, M30х1.5                                |
| 20355 | KOTLOV-005521 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru        | Varmega VM19221 230 В                                      |
| 20356 | KOTLOV-005522 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru        | Varmega VM19222 230 В                                      |
| 20357 | KOTLOV-005523 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM19501 3 м                                        |
| 20397 | KOTLOV-005563 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM09801 3/4" х 1/2"                                |
| 20398 | KOTLOV-005564 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM09802 1" х 1/2"                                  |
| 20399 | KOTLOV-005565 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM09811 3/4" х 1/2"                                |
| 20400 | KOTLOV-005566 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru        | Varmega VM09812 1" х 1/2"                                  |
| 20401 | KOTLOV-005567 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09851 3/4"                                       |
| 20402 | KOTLOV-005568 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09852 1"                                         |
| 20415 | KOTLOV-005581 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09901 3/4" х 1/2"                                |
| 20416 | KOTLOV-005582 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09902 1" х 1/2"                                  |
| 20417 | KOTLOV-005583 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09911 3/4" х 1/2"                                |
| 20418 | KOTLOV-005584 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09912 1" х 1/2"                                  |
| 20419 | KOTLOV-005585 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09951 3/4"                                       |
| 20420 | KOTLOV-005586 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru        | Varmega VM09952 1"                                         |
| 20425 | KOTLOV-005591 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by       | Varmega 2171610 3/4"EK*16х2.2                              |
| 20426 | KOTLOV-005592 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | www.ozon.ru       | Varmega 2172010 3/4"EK*20х2.8                              |
| 20463 | KOTLOV-005629 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702001815 18x15                                  |
| 20464 | KOTLOV-005630 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702002215 22x15                                  |
| 20465 | KOTLOV-005631 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702002218 22x18                                  |
| 20466 | KOTLOV-005632 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702002815 28x15                                  |
| 20467 | KOTLOV-005633 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702002822 28x22                                  |
| 20468 | KOTLOV-005634 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702003528 35x28                                  |
| 20469 | KOTLOV-005635 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702004235 42x35                                  |
| 20470 | KOTLOV-005636 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM702005442 54x42                                  |
| 20471 | KOTLOV-005637 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM703001515 15x15                                  |
| 20472 | KOTLOV-005638 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM703001818 18x18                                  |
| 20473 | KOTLOV-005639 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM703002222 22x22                                  |
| 20474 | KOTLOV-005640 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM703002828 28x28                                  |
| 20475 | KOTLOV-005641 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM703003535 35x35                                  |
| 20476 | KOTLOV-005642 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM703004242 42x42                                  |
| 20477 | KOTLOV-005643 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM703005454 54x54                                  |
| 20478 | KOTLOV-005644 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704001815 18ax15                                 |
| 20479 | KOTLOV-005645 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704002215 22ax15                                 |
| 20480 | KOTLOV-005646 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704002218 22ax18                                 |
| 20481 | KOTLOV-005647 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704002815 28ax15                                 |
| 20482 | KOTLOV-005648 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704002818 28ax18                                 |
| 20483 | KOTLOV-005649 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704002822 28ax22                                 |
| 20484 | KOTLOV-005650 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704003515 35ax15                                 |
| 20485 | KOTLOV-005651 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704003518 35ax18                                 |
| 20486 | KOTLOV-005652 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704003522 35ax22                                 |
| 20487 | KOTLOV-005653 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704003528 35ax28                                 |
| 20488 | KOTLOV-005654 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704004222 42ax22                                 |
| 20489 | KOTLOV-005655 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704004228 42ax28                                 |
| 20490 | KOTLOV-005656 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704004235 42ax35                                 |
| 20491 | KOTLOV-005657 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704005428 54ax28                                 |
| 20492 | KOTLOV-005658 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704005435 54ax35                                 |
| 20493 | KOTLOV-005659 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM704005442 54ax42                                 |
| 20694 | KOTLOV-005860 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by       | Varmega VM796000V42 42                                     |
| 20696 | KOTLOV-005862 | Varmega | Радиаторная арматура                      | rn-profi  | 2     | low_attrs          | rn-profi.by       | Varmega VM11501 под клик DA                                |
| 20717 | KOTLOV-005883 | Varmega | Фильтры                                   | rn-profi  | 4     | low_attrs          | b2b.rusklimat.com | Varmega VM04302 3/4"                                       |
| 20720 | KOTLOV-005886 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16606 1/2", 4 бар                                |
| 20721 | KOTLOV-005887 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16624 1/2" х 3/4", 3 бар                         |
| 20722 | KOTLOV-005888 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16627 1/2" х 3/4", 6 бар                         |
| 20723 | KOTLOV-005889 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16628 1/2" х 3/4", 8 бар                         |
| 20724 | KOTLOV-005890 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16629 1/2" х 3/4", 10 бар                        |
| 20725 | KOTLOV-005891 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16644 3/4" х 1", 3 бар                           |
| 20726 | KOTLOV-005892 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16647 3/4" х 1", 6 бар                           |
| 20727 | KOTLOV-005893 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16648 3/4" х 1", 8 бар                           |
| 20728 | KOTLOV-005894 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 1     | low_attrs          | rn-profi.by       | Varmega VM16649 3/4" х 1", 10 бар                          |
| 20729 | KOTLOV-005895 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by       | Varmega VM16664 1 1/4" х 1 1/2", 3 бар                     |
| 20730 | KOTLOV-005896 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by       | Varmega VM16701 1/2", 1.5 бар                              |
| 20731 | KOTLOV-005897 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by       | Varmega VM16704 1/2", 3 бар                                |
| 20733 | KOTLOV-005899 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | rn-profi.by       | Varmega VM17302 Kvs 1.6 3/4"                               |
| 20734 | KOTLOV-005900 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | rn-profi.by       | Varmega VM18103 Kvs 2.5 1"                                 |
+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+-------------------+------------------------------------------------------------+

```
