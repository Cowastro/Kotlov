# Server Artisan Result

- Time: 2026-07-12 17:00:54 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --supplier=rn-profi --brand=Varmega --active-only --not-archived --issues=no_photo,low_attrs --max-attrs=4 --limit=180`
- Log file: `storage/logs/audit-varmega-empty-cards-after-vm709.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   ca75287..38c993e  main       -> origin/main
Updating ca75287..38c993e
Fast-forward
 .github/server-artisan-result.md | 54 ++++++++++++++++++++--------------------
 .github/server-artisan-task.json |  8 +++---
 2 files changed, 31 insertions(+), 31 deletions(-)
Products with content-health issues: 197
Showing rows: 180 (limit 180)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 120      |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 196      |
| no_source  | 0        |
+------------+----------+
By supplier
+----------+----------+----------+------------+-----------+
| Name     | Products | No photo | No content | Low attrs |
+----------+----------+----------+------------+-----------+
| rn-profi | 197      | 120      | 0          | 196       |
+----------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Varmega | 197      | 120      | 0          | 196       |
+---------+----------+----------+------------+-----------+
By category
+-------------------------------------------+----------+----------+------------+-----------+
| Name                                      | Products | No photo | No content | Low attrs |
+-------------------------------------------+----------+----------+------------+-----------+
| Пресс-фитинги                             | 144      | 108      | 0          | 143       |
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
| 20563 | KOTLOV-005729 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710001818 18x18                                  |
| 20564 | KOTLOV-005730 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710002222 22x22                                  |
| 20565 | KOTLOV-005731 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710002828 28x28                                  |
| 20566 | KOTLOV-005732 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710003535 35x35                                  |
| 20567 | KOTLOV-005733 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710004242 42x42                                  |
| 20568 | KOTLOV-005734 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710005454 54x54                                  |
| 20570 | KOTLOV-005736 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711001818 18x18a                                 |
| 20571 | KOTLOV-005737 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711002222 22x22a                                 |
| 20572 | KOTLOV-005738 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711002828 28x28a                                 |
| 20573 | KOTLOV-005739 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711003535 35x35a                                 |
| 20574 | KOTLOV-005740 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711004242 42x42a                                 |
| 20575 | KOTLOV-005741 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711005454 54x54a                                 |
| 20577 | KOTLOV-005743 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712001818 18x18                                  |
| 20578 | KOTLOV-005744 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712002222 22x22                                  |
| 20579 | KOTLOV-005745 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712002828 28x28                                  |
| 20580 | KOTLOV-005746 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712003535 35x35                                  |
| 20581 | KOTLOV-005747 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712004242 42x42                                  |
| 20582 | KOTLOV-005748 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712005454 54x54                                  |
| 20583 | KOTLOV-005749 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713001515 15x15a                                 |
| 20584 | KOTLOV-005750 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713001818 18x18a                                 |
| 20585 | KOTLOV-005751 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713002222 22x22a                                 |
| 20586 | KOTLOV-005752 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713002828 28x28a                                 |
| 20587 | KOTLOV-005753 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713003535 35x35a                                 |
| 20588 | KOTLOV-005754 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713004242 42x42a                                 |
| 20589 | KOTLOV-005755 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713005454 54x54a                                 |
| 20591 | KOTLOV-005757 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714001804 18x1/2"                                |
| 20592 | KOTLOV-005758 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714001805 18x3/4"                                |
| 20593 | KOTLOV-005759 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714002204 22x1/2"                                |
| 20594 | KOTLOV-005760 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714002205 22x3/4"                                |
| 20595 | KOTLOV-005761 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714002805 28x3/4"                                |
| 20596 | KOTLOV-005762 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714003506 35x1"                                  |
| 20598 | KOTLOV-005764 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715001804 18x1/2"                                |
| 20599 | KOTLOV-005765 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715001805 18x3/4"                                |
| 20600 | KOTLOV-005766 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715002204 22x1/2"                                |
| 20601 | KOTLOV-005767 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715002205 22x3/4"                                |
| 20602 | KOTLOV-005768 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715002805 28x3/4"                                |
| 20603 | KOTLOV-005769 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715003506 35x1"                                  |
| 20604 | KOTLOV-005770 | Varmega | Пресс-фитинги                             | rn-profi  | 4     | low_attrs          | rn-profi.by    | Varmega VM716001504 15x1/2"                                |
| 20605 | KOTLOV-005771 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM716001804 18x1/2"                                |
| 20606 | KOTLOV-005772 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM716002204 22x1/2"                                |
| 20607 | KOTLOV-005773 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM716002205 22x3/4"                                |
| 20608 | KOTLOV-005774 | Varmega | Пресс-фитинги                             | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM717001504 15x1/2"                                |
| 20610 | KOTLOV-005776 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718181818 18x18x18                               |
| 20611 | KOTLOV-005777 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718222222 22x22x22                               |
| 20612 | KOTLOV-005778 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718282828 28x28x28                               |
| 20613 | KOTLOV-005779 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718353535 35x35x35                               |
| 20614 | KOTLOV-005780 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718424242 42x42x42                               |
| 20615 | KOTLOV-005781 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718545454 54x54x54                               |
| 20616 | KOTLOV-005782 | Varmega | Пресс-фитинги                             | rn-profi  | 13    | no_photo           | rn-profi.by    | Varmega VM719181518 18x15x18                               |
| 20617 | KOTLOV-005783 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719221522 22x15x22                               |
| 20618 | KOTLOV-005784 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719221822 22x18x22                               |
| 20619 | KOTLOV-005785 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719281528 28x15x28                               |
| 20620 | KOTLOV-005786 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719281828 28x18x28                               |
| 20621 | KOTLOV-005787 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719282222 28x22x22                               |
| 20622 | KOTLOV-005788 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719282228 28x22x28                               |
| 20623 | KOTLOV-005789 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719282822 28x28x22                               |
| 20624 | KOTLOV-005790 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719351535 35x15x35                               |
| 20625 | KOTLOV-005791 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719352235 35x22x35                               |
| 20626 | KOTLOV-005792 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719352835 35x28x35                               |
| 20627 | KOTLOV-005793 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719423542 42x35x42                               |
| 20628 | KOTLOV-005794 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719544254 54x42x54                               |
| 20630 | KOTLOV-005796 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720180418 18x1/2"x18                             |
| 20631 | KOTLOV-005797 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720180518 18x3/4"x18                             |
| 20632 | KOTLOV-005798 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720220422 22x1/2"x22                             |
| 20633 | KOTLOV-005799 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720220522 22x3/4"x22                             |
| 20634 | KOTLOV-005800 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720280428 28x1/2"x28                             |
| 20635 | KOTLOV-005801 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720280528 28x3/4"x28                             |
| 20636 | KOTLOV-005802 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720280628 28x1"x28                               |
| 20637 | KOTLOV-005803 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720350435 35x1/2"x35                             |
| 20638 | KOTLOV-005804 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720350635 35x1"x35                               |
| 20639 | KOTLOV-005805 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720350735 35x1 1/4"x35                           |
| 20640 | KOTLOV-005806 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720420442 42x1/2"x42                             |
| 20641 | KOTLOV-005807 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720420642 42x1"x42                               |
| 20642 | KOTLOV-005808 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720420742 42x1 1/4"x42                           |
| 20643 | KOTLOV-005809 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720540454 54x1/2"x54                             |
| 20644 | KOTLOV-005810 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720540654 54x1"x54                               |
| 20645 | KOTLOV-005811 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720540754 54x1 1/4"x54                           |
| 20647 | KOTLOV-005813 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721180418 18x1/2"x18                             |
| 20648 | KOTLOV-005814 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721180518 18x3/4"x18                             |
| 20649 | KOTLOV-005815 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721220422 22x1/2"x22                             |
| 20650 | KOTLOV-005816 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721220522 22x3/4"x22                             |
| 20651 | KOTLOV-005817 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721280428 28x1/2"x28                             |
| 20652 | KOTLOV-005818 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721280528 28x3/4"x28                             |
| 20653 | KOTLOV-005819 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721280628 28x1"x28                               |
| 20654 | KOTLOV-005820 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721350635 35x1"x35                               |
| 20656 | KOTLOV-005822 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721420642 42x1"x42                               |
| 20657 | KOTLOV-005823 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721420742 42x1 1/4"x42                           |
| 20658 | KOTLOV-005824 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721540654 54x1"x54                               |
| 20659 | KOTLOV-005825 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM721540754 54x1 1/4"x54                           |
| 20668 | KOTLOV-005834 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM723002018 20-2.8x18                              |
| 20669 | KOTLOV-005835 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM723002022 20-2.8x22                              |
| 20670 | KOTLOV-005836 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM723002522 25-3.5x22                              |
| 20671 | KOTLOV-005837 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM723003228 32-4.4x28                              |
| 20673 | KOTLOV-005839 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM724001818 18x18x18x18                            |
| 20674 | KOTLOV-005840 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM724002222 22х22х22х22                            |
| 20675 | KOTLOV-005841 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM724002828 28х28х28х28                            |
| 20677 | KOTLOV-005843 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM725002215 22x15x22x15                            |
| 20678 | KOTLOV-005844 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM725002218 22x18x22x18                            |
| 20679 | KOTLOV-005845 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM725002822 28x22x28x22                            |
| 20680 | KOTLOV-005846 | Varmega | Пресс-фитинги                             | rn-profi  | 4     | low_attrs          | rn-profi.by    | Varmega VM730001515 15x15                                  |
| 20681 | KOTLOV-005847 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM730001818 18x18                                  |
| 20682 | KOTLOV-005848 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM730002222 22x22                                  |
| 20683 | KOTLOV-005849 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM730002828 28x28                                  |
| 20684 | KOTLOV-005850 | Varmega | Пресс-фитинги                             | rn-profi  | 3     | low_attrs          | rn-profi.by    | Varmega VM731001515 15x15a                                 |
| 20685 | KOTLOV-005851 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM731001818 18x18a                                 |
| 20686 | KOTLOV-005852 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM731002222 22x22a                                 |
| 20687 | KOTLOV-005853 | Varmega | Пресс-фитинги                             | rn-profi  | 2     | low_attrs          | rn-profi.by    | Varmega VM733150070 15ax70x160                             |
| 20688 | KOTLOV-005854 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM733150100 15ax100x600                            |
+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+----------------+------------------------------------------------------------+

```
