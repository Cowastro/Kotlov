# Server Artisan Result

- Time: 2026-07-12 16:31:45 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --supplier=rn-profi --brand=Varmega --active-only --not-archived --issues=no_photo,no_content,no_short,low_attrs,no_source --max-attrs=4 --limit=120`
- Log file: `storage/logs/audit-varmega-empty-cards.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   9cc8fe7..6cac10b  main       -> origin/main
Updating 9cc8fe7..6cac10b
Fast-forward
 .github/server-artisan-result.md | 54 ++++++++++++++++++----------------------
 .github/server-artisan-task.json |  8 +++---
 2 files changed, 28 insertions(+), 34 deletions(-)
Products with content-health issues: 248
Showing rows: 120 (limit 120)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 171      |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 232      |
| no_source  | 0        |
+------------+----------+
By supplier
+----------+----------+----------+------------+-----------+
| Name     | Products | No photo | No content | Low attrs |
+----------+----------+----------+------------+-----------+
| rn-profi | 248      | 171      | 0          | 232       |
+----------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Varmega | 248      | 171      | 0          | 232       |
+---------+----------+----------+------------+-----------+
By category
+-------------------------------------------+----------+----------+------------+-----------+
| Name                                      | Products | No photo | No content | Low attrs |
+-------------------------------------------+----------+----------+------------+-----------+
| Пресс-фитинги                             | 195      | 159      | 0          | 179       |
| Котлы отопления                           | 38       | 12       | 0          | 38        |
| Резьбовые фитинги                         | 13       | 0        | 0          | 13        |
| Предохранительная и регулирующая арматура | 1        | 0        | 0          | 1         |
| Радиаторная арматура                      | 1        | 0        | 0          | 1         |
+-------------------------------------------+----------+----------+------------+-----------+

+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+----------------+------------------------------------------------------------+
| ID    | SKU           | Brand   | Category                                  | Suppliers | Attrs | Issues             | Source domains | Product                                                    |
+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+----------------+------------------------------------------------------------+
| 19812 | KOTLOV-004978 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Угол переходной Varmega VM09821, ВР-ВР, 3/4" х 1/2", хр... |
| 19813 | KOTLOV-004979 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Угол переходной Varmega VM09822, ВР-ВР, 1" х 1/2", хром... |
| 19814 | KOTLOV-004980 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с внутренней резьбой Varmega VM09831, ВР, 3/4"... |
| 19815 | KOTLOV-004981 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с внутренней резьбой Varmega VM09832, ВР, 1", ... |
| 19816 | KOTLOV-004982 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с наружной резьбой Varmega VM09841, НР, 3/4", ... |
| 19817 | KOTLOV-004983 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с наружной резьбой Varmega VM09842, НР, 1", хр... |
| 19818 | KOTLOV-004984 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Угол переходной Varmega VM09921, ВР-ВР, 3/4" х 1/2"        |
| 19819 | KOTLOV-004985 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Угол переходной Varmega VM09922, ВР-ВР, 1" х 1/2"          |
| 19820 | KOTLOV-004986 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с внутренней резьбой Varmega VM09931, ВР, 3/4"    |
| 19821 | KOTLOV-004987 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с внутренней резьбой Varmega VM09932, ВР, 1"      |
| 19822 | KOTLOV-004988 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с наружной резьбой Varmega VM09941, НР, 3/4"      |
| 19823 | KOTLOV-004989 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Заглушка с наружной резьбой Varmega VM09942, НР, 1"        |
| 20067 | KOTLOV-005233 | Varmega | Резьбовые фитинги                         | rn-profi  | 4     | low_attrs          | varmega.ru     | Латунный запирающий колпачок Varmega VM15978, для термо... |
| 20229 | KOTLOV-005395 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 2     | low_attrs          | varmega.ru     | Соединение быстросъемное Varmega VM09601, 3/4", для рас... |
| 20287 | KOTLOV-005453 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM30701 16x2.0                                     |
| 20288 | KOTLOV-005454 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM30751 16x2.0                                     |
| 20337 | KOTLOV-005503 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM15975 24х30                                      |
| 20341 | KOTLOV-005507 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru     | Varmega VM15906 1"                                         |
| 20351 | KOTLOV-005517 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM15981 M18x1.75                                   |
| 20352 | KOTLOV-005518 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru     | Varmega VM15985 1/2"                                       |
| 20353 | KOTLOV-005519 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM19001 НО, M30х1.5                                |
| 20354 | KOTLOV-005520 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM19002 НЗ, M30х1.5                                |
| 20355 | KOTLOV-005521 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru     | Varmega VM19221 230 В                                      |
| 20356 | KOTLOV-005522 | Varmega | Котлы отопления                           | rn-profi  | 4     | low_attrs          | varmega.ru     | Varmega VM19222 230 В                                      |
| 20357 | KOTLOV-005523 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM19501 3 м                                        |
| 20397 | KOTLOV-005563 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM09801 3/4" х 1/2"                                |
| 20398 | KOTLOV-005564 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM09802 1" х 1/2"                                  |
| 20399 | KOTLOV-005565 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM09811 3/4" х 1/2"                                |
| 20400 | KOTLOV-005566 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM09812 1" х 1/2"                                  |
| 20401 | KOTLOV-005567 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09851 3/4"                                       |
| 20402 | KOTLOV-005568 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09852 1"                                         |
| 20415 | KOTLOV-005581 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09901 3/4" х 1/2"                                |
| 20416 | KOTLOV-005582 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09902 1" х 1/2"                                  |
| 20417 | KOTLOV-005583 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09911 3/4" х 1/2"                                |
| 20418 | KOTLOV-005584 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09912 1" х 1/2"                                  |
| 20419 | KOTLOV-005585 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09951 3/4"                                       |
| 20420 | KOTLOV-005586 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM09952 1"                                         |
| 20425 | KOTLOV-005591 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega 2171610 3/4"EK*16х2.2                              |
| 20426 | KOTLOV-005592 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega 2172010 3/4"EK*20х2.8                              |
| 20428 | KOTLOV-005594 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM52603 20х2.8-16х2.2/250                          |
| 20429 | KOTLOV-005595 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM52604 16х2.2-20х2.8/250                          |
| 20463 | KOTLOV-005629 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702001815 18x15                                  |
| 20464 | KOTLOV-005630 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002215 22x15                                  |
| 20465 | KOTLOV-005631 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002218 22x18                                  |
| 20466 | KOTLOV-005632 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002815 28x15                                  |
| 20467 | KOTLOV-005633 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002822 28x22                                  |
| 20468 | KOTLOV-005634 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702003528 35x28                                  |
| 20469 | KOTLOV-005635 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702004235 42x35                                  |
| 20470 | KOTLOV-005636 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702005442 54x42                                  |
| 20471 | KOTLOV-005637 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703001515 15x15                                  |
| 20472 | KOTLOV-005638 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703001818 18x18                                  |
| 20473 | KOTLOV-005639 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703002222 22x22                                  |
| 20474 | KOTLOV-005640 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703002828 28x28                                  |
| 20475 | KOTLOV-005641 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703003535 35x35                                  |
| 20476 | KOTLOV-005642 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703004242 42x42                                  |
| 20477 | KOTLOV-005643 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703005454 54x54                                  |
| 20478 | KOTLOV-005644 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704001815 18ax15                                 |
| 20479 | KOTLOV-005645 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002215 22ax15                                 |
| 20480 | KOTLOV-005646 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002218 22ax18                                 |
| 20481 | KOTLOV-005647 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002815 28ax15                                 |
| 20482 | KOTLOV-005648 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002818 28ax18                                 |
| 20483 | KOTLOV-005649 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002822 28ax22                                 |
| 20484 | KOTLOV-005650 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003515 35ax15                                 |
| 20485 | KOTLOV-005651 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003518 35ax18                                 |
| 20486 | KOTLOV-005652 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003522 35ax22                                 |
| 20487 | KOTLOV-005653 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003528 35ax28                                 |
| 20488 | KOTLOV-005654 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704004222 42ax22                                 |
| 20489 | KOTLOV-005655 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704004228 42ax28                                 |
| 20490 | KOTLOV-005656 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704004235 42ax35                                 |
| 20491 | KOTLOV-005657 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704005428 54ax28                                 |
| 20492 | KOTLOV-005658 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704005435 54ax35                                 |
| 20493 | KOTLOV-005659 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704005442 54ax42                                 |
| 20508 | KOTLOV-005674 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706001504 15x1/2"                                |
| 20509 | KOTLOV-005675 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706001505 15x3/4"                                |
| 20510 | KOTLOV-005676 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706001804 18x1/2"                                |
| 20511 | KOTLOV-005677 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706001805 18x3/4"                                |
| 20512 | KOTLOV-005678 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706002204 22x1/2"                                |
| 20513 | KOTLOV-005679 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706002205 22x3/4"                                |
| 20514 | KOTLOV-005680 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706002206 22x1"                                  |
| 20515 | KOTLOV-005681 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706002804 28x1/2"                                |
| 20516 | KOTLOV-005682 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706002805 28x3/4"                                |
| 20517 | KOTLOV-005683 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706002806 28x1"                                  |
| 20518 | KOTLOV-005684 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706003505 35x3/4"                                |
| 20519 | KOTLOV-005685 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706003506 35x1"                                  |
| 20520 | KOTLOV-005686 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706003507 35x1 1/4"                              |
| 20521 | KOTLOV-005687 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706004208 42x1 1/2"                              |
| 20522 | KOTLOV-005688 | Varmega | Пресс-фитинги                             | rn-profi  | 6     | no_photo           | rn-profi.by    | Varmega VM706005409 54x2"                                  |
| 20524 | KOTLOV-005690 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001505 15ax3/4"                               |
| 20525 | KOTLOV-005691 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001804 18ax1/2"                               |
| 20526 | KOTLOV-005692 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001805 18ax3/4"                               |
| 20527 | KOTLOV-005693 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001806 18ax1"                                 |
| 20528 | KOTLOV-005694 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002204 22ax1/2"                               |
| 20529 | KOTLOV-005695 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002205 22ax3/4"                               |
| 20530 | KOTLOV-005696 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002206 22ax1"                                 |
| 20531 | KOTLOV-005697 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002805 28ax3/4"                               |
| 20532 | KOTLOV-005698 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002806 28ax1"                                 |
| 20533 | KOTLOV-005699 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707003507 35ax1 1/4"                             |
| 20534 | KOTLOV-005700 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707004208 42ax1 1/2"                             |
| 20535 | KOTLOV-005701 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707005409 54ax2"                                 |
| 20537 | KOTLOV-005703 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001505 15ax3/4"                               |
| 20538 | KOTLOV-005704 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001804 18ax1/2"                               |
| 20539 | KOTLOV-005705 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001805 18ax3/4"                               |
| 20540 | KOTLOV-005706 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001806 18ax1"                                 |
| 20541 | KOTLOV-005707 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002204 22ax1/2"                               |
| 20542 | KOTLOV-005708 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002205 22ax3/4"                               |
| 20543 | KOTLOV-005709 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002206 22ax1"                                 |
| 20544 | KOTLOV-005710 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002805 28ax3/4"                               |
| 20545 | KOTLOV-005711 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002806 28ax1"                                 |
| 20546 | KOTLOV-005712 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708003507 35ax1 1/4"                             |
| 20547 | KOTLOV-005713 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708004208 42ax1 1/2"                             |
| 20548 | KOTLOV-005714 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708005409 54ax2"                                 |
| 20550 | KOTLOV-005716 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709001505 15x3/4"                                |
| 20551 | KOTLOV-005717 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709001804 18x1/2"                                |
| 20552 | KOTLOV-005718 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709001805 18x3/4"                                |
| 20553 | KOTLOV-005719 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002204 22x1/2"                                |
| 20554 | KOTLOV-005720 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002205 22x3/4"                                |
| 20555 | KOTLOV-005721 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002206 22x1"                                  |
| 20556 | KOTLOV-005722 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002805 28x3/4"                                |
| 20557 | KOTLOV-005723 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002806 28x1"                                  |
| 20558 | KOTLOV-005724 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709003506 35x1"                                  |
+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+----------------+------------------------------------------------------------+

```
