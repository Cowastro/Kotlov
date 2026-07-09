# Server Artisan Result

- Time: 2026-07-09 16:23:56 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --active-only --not-archived --supplier=rn-profi --brand=Varmega --with-source-only --max-attrs=2 --limit=120 --csv=storage/app/reports/product-content-health/varmega-after-official-fill.csv`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
Products with content-health issues: 268
Showing rows: 120 (limit 120)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 245      |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 266      |
| no_source  | 0        |
+------------+----------+
By supplier
+----------+----------+----------+------------+-----------+
| Name     | Products | No photo | No content | Low attrs |
+----------+----------+----------+------------+-----------+
| rn-profi | 268      | 245      | 0          | 266       |
+----------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Varmega | 268      | 245      | 0          | 266       |
+---------+----------+----------+------------+-----------+
By category
+-------------------------------------------+----------+----------+------------+-----------+
| Name                                      | Products | No photo | No content | Low attrs |
+-------------------------------------------+----------+----------+------------+-----------+
| Пресс-фитинги                             | 215      | 213      | 0          | 213       |
| Котлы отопления                           | 51       | 32       | 0          | 51        |
| Предохранительная и регулирующая арматура | 1        | 0        | 0          | 1         |
| Радиаторная арматура                      | 1        | 0        | 0          | 1         |
+-------------------------------------------+----------+----------+------------+-----------+

+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+----------------+------------------------------------------------------------+
| ID    | SKU           | Brand   | Category                                  | Suppliers | Attrs | Issues             | Source domains | Product                                                    |
+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+----------------+------------------------------------------------------------+
| 20229 | KOTLOV-005395 | Varmega | Предохранительная и регулирующая арматура | rn-profi  | 2     | low_attrs          | varmega.ru     | Соединение быстросъемное Varmega VM09601, 3/4", для рас... |
| 20287 | KOTLOV-005453 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM30701 16x2.0                                     |
| 20288 | KOTLOV-005454 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM30751 16x2.0                                     |
| 20337 | KOTLOV-005503 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | varmega.ru     | Varmega VM15975 24х30                                      |
| 20351 | KOTLOV-005517 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM15981 M18x1.75                                   |
| 20353 | KOTLOV-005519 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM19001 НО, M30х1.5                                |
| 20354 | KOTLOV-005520 | Varmega | Котлы отопления                           | rn-profi  | 2     | low_attrs          | varmega.ru     | Varmega VM19002 НЗ, M30х1.5                                |
| 20357 | KOTLOV-005523 | Varmega | Котлы отопления                           | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM19501 3 м                                        |
| 20365 | KOTLOV-005531 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35500 ШРВ-0 1-3                                  |
| 20366 | KOTLOV-005532 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35501 ШРВ-1 4-5                                  |
| 20367 | KOTLOV-005533 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35502 ШРВ-2 6-7                                  |
| 20368 | KOTLOV-005534 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35503 ШРВ-3 8-10                                 |
| 20369 | KOTLOV-005535 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35504 ШРВ-4 11-12                                |
| 20370 | KOTLOV-005536 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35505 ШРВ-5 13-16                                |
| 20371 | KOTLOV-005537 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35506 ШРВ-6 17-18                                |
| 20372 | KOTLOV-005538 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35510 ШРН-0 1-3                                  |
| 20373 | KOTLOV-005539 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35511 ШРН-1 4-5                                  |
| 20374 | KOTLOV-005540 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35512 ШРН-2 6-7                                  |
| 20375 | KOTLOV-005541 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35513 ШРН-3 8-10                                 |
| 20376 | KOTLOV-005542 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35514 ШРН-4 11-12                                |
| 20377 | KOTLOV-005543 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35515 ШРН-5 13-16                                |
| 20378 | KOTLOV-005544 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35516 ШРН-6 17-18                                |
| 20379 | KOTLOV-005545 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35521 ШРНГ-1 4-5                                 |
| 20380 | KOTLOV-005546 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35522 ШРНГ-2 6-7                                 |
| 20381 | KOTLOV-005547 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35523 ШРНГ-3 8-10                                |
| 20382 | KOTLOV-005548 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35524 ШРНГ-4 11-12                               |
| 20383 | KOTLOV-005549 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35525 ШРНГ-5 13-16                               |
| 20384 | KOTLOV-005550 | Varmega | Котлы отопления                           | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM35526 ШРНГ-6 17-18                               |
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
| 20457 | KOTLOV-005623 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM701001818 18x18                                  |
| 20458 | KOTLOV-005624 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM701002222 22x22                                  |
| 20459 | KOTLOV-005625 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM701002828 28x28                                  |
| 20460 | KOTLOV-005626 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM701003535 35x35                                  |
| 20461 | KOTLOV-005627 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM701004242 42x42                                  |
| 20462 | KOTLOV-005628 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM701005454 54x54                                  |
| 20464 | KOTLOV-005630 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM702002215 22x15                                  |
| 20465 | KOTLOV-005631 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM702002218 22x18                                  |
| 20466 | KOTLOV-005632 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM702002815 28x15                                  |
| 20467 | KOTLOV-005633 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM702002822 28x22                                  |
| 20468 | KOTLOV-005634 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM702003528 35x28                                  |
| 20469 | KOTLOV-005635 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM702004235 42x35                                  |
| 20470 | KOTLOV-005636 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM702005442 54x42                                  |
| 20472 | KOTLOV-005638 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM703001818 18x18                                  |
| 20473 | KOTLOV-005639 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM703002222 22x22                                  |
| 20474 | KOTLOV-005640 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM703002828 28x28                                  |
| 20475 | KOTLOV-005641 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM703003535 35x35                                  |
| 20476 | KOTLOV-005642 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM703004242 42x42                                  |
| 20477 | KOTLOV-005643 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM703005454 54x54                                  |
| 20478 | KOTLOV-005644 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704001815 18ax15                                 |
| 20479 | KOTLOV-005645 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704002215 22ax15                                 |
| 20481 | KOTLOV-005647 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704002815 28ax15                                 |
| 20482 | KOTLOV-005648 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704002818 28ax18                                 |
| 20483 | KOTLOV-005649 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704002822 28ax22                                 |
| 20484 | KOTLOV-005650 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704003515 35ax15                                 |
| 20485 | KOTLOV-005651 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704003518 35ax18                                 |
| 20486 | KOTLOV-005652 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704003522 35ax22                                 |
| 20487 | KOTLOV-005653 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704003528 35ax28                                 |
| 20488 | KOTLOV-005654 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704004222 42ax22                                 |
| 20489 | KOTLOV-005655 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704004228 42ax28                                 |
| 20490 | KOTLOV-005656 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704004235 42ax35                                 |
| 20491 | KOTLOV-005657 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704005428 54ax28                                 |
| 20492 | KOTLOV-005658 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704005435 54ax35                                 |
| 20493 | KOTLOV-005659 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM704005442 54ax42                                 |
| 20495 | KOTLOV-005661 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705001804 18x1/2"                                |
| 20496 | KOTLOV-005662 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705001805 18x3/4"                                |
| 20497 | KOTLOV-005663 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705002204 22x1/2"                                |
| 20498 | KOTLOV-005664 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705002205 22x3/4"                                |
| 20499 | KOTLOV-005665 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705002206 22x1"                                  |
| 20500 | KOTLOV-005666 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705002804 28x1/2"                                |
| 20501 | KOTLOV-005667 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705002805 28x3/4"                                |
| 20502 | KOTLOV-005668 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705002806 28x1"                                  |
| 20503 | KOTLOV-005669 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705003505 35x3/4"                                |
| 20504 | KOTLOV-005670 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705003506 35x1"                                  |
| 20505 | KOTLOV-005671 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705003507 35x1 1/4"                              |
| 20506 | KOTLOV-005672 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705004208 42x1 1/2"                              |
| 20507 | KOTLOV-005673 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM705005409 54x2"                                  |
| 20508 | KOTLOV-005674 | Varmega | Пресс-фитинги                             | rn-profi  | 15    | no_photo           | rn-profi.by    | Varmega VM706001504 15x1/2"                                |
| 20509 | KOTLOV-005675 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001505 15x3/4"                                |
| 20510 | KOTLOV-005676 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001804 18x1/2"                                |
| 20511 | KOTLOV-005677 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001805 18x3/4"                                |
| 20512 | KOTLOV-005678 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002204 22x1/2"                                |
| 20513 | KOTLOV-005679 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002205 22x3/4"                                |
| 20514 | KOTLOV-005680 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002206 22x1"                                  |
| 20515 | KOTLOV-005681 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002804 28x1/2"                                |
| 20516 | KOTLOV-005682 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002805 28x3/4"                                |
| 20517 | KOTLOV-005683 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002806 28x1"                                  |
| 20518 | KOTLOV-005684 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003505 35x3/4"                                |
| 20519 | KOTLOV-005685 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003506 35x1"                                  |
| 20520 | KOTLOV-005686 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003507 35x1 1/4"                              |
| 20521 | KOTLOV-005687 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706004208 42x1 1/2"                              |
| 20522 | KOTLOV-005688 | Varmega | Пресс-фитинги                             | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706005409 54x2"                                  |
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
+-------+---------------+---------+-------------------------------------------+-----------+-------+--------------------+----------------+------------------------------------------------------------+
CSV written: /var/www/h209767/data/www/new.kotlov.by/storage/app/reports/product-content-health/varmega-after-official-fill.csv

```
