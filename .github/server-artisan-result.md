# Server Artisan Result

- Time: 2026-07-12 14:37:28 UTC
- Task: `artisan-apply`
- Artisan args: `products:audit-content-health --supplier=rn-profi --brand=Varmega --category=Пресс-фитинги --active-only --not-archived --issues=no_photo,low_attrs,no_source --max-attrs=2 --limit=160`
- Log file: `storage/logs/varmega-press-fitting-health-audit.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   263d778..8ae3669  main       -> origin/main
Updating 263d778..8ae3669
Fast-forward
 .github/server-artisan-result.md | 69 ++++++++++------------------------------
 .github/server-artisan-task.json |  6 ++--
 2 files changed, 20 insertions(+), 55 deletions(-)
Products with content-health issues: 199
Showing rows: 160 (limit 160)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 166      |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 197      |
| no_source  | 0        |
+------------+----------+
By supplier
+----------+----------+----------+------------+-----------+
| Name     | Products | No photo | No content | Low attrs |
+----------+----------+----------+------------+-----------+
| rn-profi | 199      | 166      | 0          | 197       |
+----------+----------+----------+------------+-----------+
By brand
+---------+----------+----------+------------+-----------+
| Name    | Products | No photo | No content | Low attrs |
+---------+----------+----------+------------+-----------+
| Varmega | 199      | 166      | 0          | 197       |
+---------+----------+----------+------------+-----------+
By category
+---------------+----------+----------+------------+-----------+
| Name          | Products | No photo | No content | Low attrs |
+---------------+----------+----------+------------+-----------+
| Пресс-фитинги | 199      | 166      | 0          | 197       |
+---------------+----------+----------+------------+-----------+

+-------+---------------+---------+---------------+-----------+-------+--------------------+----------------+-----------------------------------+
| ID    | SKU           | Brand   | Category      | Suppliers | Attrs | Issues             | Source domains | Product                           |
+-------+---------------+---------+---------------+-----------+-------+--------------------+----------------+-----------------------------------+
| 20425 | KOTLOV-005591 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega 2171610 3/4"EK*16х2.2     |
| 20426 | KOTLOV-005592 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega 2172010 3/4"EK*20х2.8     |
| 20428 | KOTLOV-005594 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM52603 20х2.8-16х2.2/250 |
| 20429 | KOTLOV-005595 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM52604 16х2.2-20х2.8/250 |
| 20463 | KOTLOV-005629 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702001815 18x15         |
| 20464 | KOTLOV-005630 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002215 22x15         |
| 20465 | KOTLOV-005631 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002218 22x18         |
| 20466 | KOTLOV-005632 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002815 28x15         |
| 20467 | KOTLOV-005633 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702002822 28x22         |
| 20468 | KOTLOV-005634 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702003528 35x28         |
| 20469 | KOTLOV-005635 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702004235 42x35         |
| 20470 | KOTLOV-005636 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM702005442 54x42         |
| 20471 | KOTLOV-005637 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703001515 15x15         |
| 20472 | KOTLOV-005638 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703001818 18x18         |
| 20473 | KOTLOV-005639 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703002222 22x22         |
| 20474 | KOTLOV-005640 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703002828 28x28         |
| 20475 | KOTLOV-005641 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703003535 35x35         |
| 20476 | KOTLOV-005642 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703004242 42x42         |
| 20477 | KOTLOV-005643 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM703005454 54x54         |
| 20478 | KOTLOV-005644 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704001815 18ax15        |
| 20479 | KOTLOV-005645 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002215 22ax15        |
| 20480 | KOTLOV-005646 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002218 22ax18        |
| 20481 | KOTLOV-005647 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002815 28ax15        |
| 20482 | KOTLOV-005648 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002818 28ax18        |
| 20483 | KOTLOV-005649 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704002822 28ax22        |
| 20484 | KOTLOV-005650 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003515 35ax15        |
| 20485 | KOTLOV-005651 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003518 35ax18        |
| 20486 | KOTLOV-005652 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003522 35ax22        |
| 20487 | KOTLOV-005653 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704003528 35ax28        |
| 20488 | KOTLOV-005654 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704004222 42ax22        |
| 20489 | KOTLOV-005655 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704004228 42ax28        |
| 20490 | KOTLOV-005656 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704004235 42ax35        |
| 20491 | KOTLOV-005657 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704005428 54ax28        |
| 20492 | KOTLOV-005658 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704005435 54ax35        |
| 20493 | KOTLOV-005659 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM704005442 54ax42        |
| 20508 | KOTLOV-005674 | Varmega | Пресс-фитинги | rn-profi  | 15    | no_photo           | rn-profi.by    | Varmega VM706001504 15x1/2"       |
| 20509 | KOTLOV-005675 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001505 15x3/4"       |
| 20510 | KOTLOV-005676 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001804 18x1/2"       |
| 20511 | KOTLOV-005677 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706001805 18x3/4"       |
| 20512 | KOTLOV-005678 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002204 22x1/2"       |
| 20513 | KOTLOV-005679 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002205 22x3/4"       |
| 20514 | KOTLOV-005680 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002206 22x1"         |
| 20515 | KOTLOV-005681 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002804 28x1/2"       |
| 20516 | KOTLOV-005682 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002805 28x3/4"       |
| 20517 | KOTLOV-005683 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706002806 28x1"         |
| 20518 | KOTLOV-005684 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003505 35x3/4"       |
| 20519 | KOTLOV-005685 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003506 35x1"         |
| 20520 | KOTLOV-005686 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706003507 35x1 1/4"     |
| 20521 | KOTLOV-005687 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706004208 42x1 1/2"     |
| 20522 | KOTLOV-005688 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM706005409 54x2"         |
| 20524 | KOTLOV-005690 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001505 15ax3/4"      |
| 20525 | KOTLOV-005691 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001804 18ax1/2"      |
| 20526 | KOTLOV-005692 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001805 18ax3/4"      |
| 20527 | KOTLOV-005693 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707001806 18ax1"        |
| 20528 | KOTLOV-005694 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002204 22ax1/2"      |
| 20529 | KOTLOV-005695 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002205 22ax3/4"      |
| 20530 | KOTLOV-005696 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002206 22ax1"        |
| 20531 | KOTLOV-005697 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002805 28ax3/4"      |
| 20532 | KOTLOV-005698 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707002806 28ax1"        |
| 20533 | KOTLOV-005699 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707003507 35ax1 1/4"    |
| 20534 | KOTLOV-005700 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707004208 42ax1 1/2"    |
| 20535 | KOTLOV-005701 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM707005409 54ax2"        |
| 20537 | KOTLOV-005703 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001505 15ax3/4"      |
| 20538 | KOTLOV-005704 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001804 18ax1/2"      |
| 20539 | KOTLOV-005705 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001805 18ax3/4"      |
| 20540 | KOTLOV-005706 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708001806 18ax1"        |
| 20541 | KOTLOV-005707 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002204 22ax1/2"      |
| 20542 | KOTLOV-005708 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002205 22ax3/4"      |
| 20543 | KOTLOV-005709 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002206 22ax1"        |
| 20544 | KOTLOV-005710 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002805 28ax3/4"      |
| 20545 | KOTLOV-005711 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708002806 28ax1"        |
| 20546 | KOTLOV-005712 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708003507 35ax1 1/4"    |
| 20547 | KOTLOV-005713 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708004208 42ax1 1/2"    |
| 20548 | KOTLOV-005714 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM708005409 54ax2"        |
| 20550 | KOTLOV-005716 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709001505 15x3/4"       |
| 20551 | KOTLOV-005717 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709001804 18x1/2"       |
| 20552 | KOTLOV-005718 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709001805 18x3/4"       |
| 20553 | KOTLOV-005719 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002204 22x1/2"       |
| 20554 | KOTLOV-005720 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002205 22x3/4"       |
| 20555 | KOTLOV-005721 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002206 22x1"         |
| 20556 | KOTLOV-005722 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002805 28x3/4"       |
| 20557 | KOTLOV-005723 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709002806 28x1"         |
| 20558 | KOTLOV-005724 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709003506 35x1"         |
| 20559 | KOTLOV-005725 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709003507 35x1 1/4"     |
| 20560 | KOTLOV-005726 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709004208 42x1 1/2"     |
| 20561 | KOTLOV-005727 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM709005409 54x2"         |
| 20563 | KOTLOV-005729 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710001818 18x18         |
| 20564 | KOTLOV-005730 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710002222 22x22         |
| 20565 | KOTLOV-005731 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710002828 28x28         |
| 20566 | KOTLOV-005732 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710003535 35x35         |
| 20567 | KOTLOV-005733 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710004242 42x42         |
| 20568 | KOTLOV-005734 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM710005454 54x54         |
| 20570 | KOTLOV-005736 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711001818 18x18a        |
| 20571 | KOTLOV-005737 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711002222 22x22a        |
| 20572 | KOTLOV-005738 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711002828 28x28a        |
| 20573 | KOTLOV-005739 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711003535 35x35a        |
| 20574 | KOTLOV-005740 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711004242 42x42a        |
| 20575 | KOTLOV-005741 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM711005454 54x54a        |
| 20577 | KOTLOV-005743 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712001818 18x18         |
| 20578 | KOTLOV-005744 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712002222 22x22         |
| 20579 | KOTLOV-005745 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712002828 28x28         |
| 20580 | KOTLOV-005746 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712003535 35x35         |
| 20581 | KOTLOV-005747 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712004242 42x42         |
| 20582 | KOTLOV-005748 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM712005454 54x54         |
| 20583 | KOTLOV-005749 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713001515 15x15a        |
| 20584 | KOTLOV-005750 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713001818 18x18a        |
| 20585 | KOTLOV-005751 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713002222 22x22a        |
| 20586 | KOTLOV-005752 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713002828 28x28a        |
| 20587 | KOTLOV-005753 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713003535 35x35a        |
| 20588 | KOTLOV-005754 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713004242 42x42a        |
| 20589 | KOTLOV-005755 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM713005454 54x54a        |
| 20591 | KOTLOV-005757 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714001804 18x1/2"       |
| 20592 | KOTLOV-005758 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714001805 18x3/4"       |
| 20593 | KOTLOV-005759 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714002204 22x1/2"       |
| 20594 | KOTLOV-005760 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714002205 22x3/4"       |
| 20595 | KOTLOV-005761 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714002805 28x3/4"       |
| 20596 | KOTLOV-005762 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM714003506 35x1"         |
| 20598 | KOTLOV-005764 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715001804 18x1/2"       |
| 20599 | KOTLOV-005765 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715001805 18x3/4"       |
| 20600 | KOTLOV-005766 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715002204 22x1/2"       |
| 20601 | KOTLOV-005767 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715002205 22x3/4"       |
| 20602 | KOTLOV-005768 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715002805 28x3/4"       |
| 20603 | KOTLOV-005769 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM715003506 35x1"         |
| 20605 | KOTLOV-005771 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM716001804 18x1/2"       |
| 20606 | KOTLOV-005772 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM716002204 22x1/2"       |
| 20607 | KOTLOV-005773 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM716002205 22x3/4"       |
| 20608 | KOTLOV-005774 | Varmega | Пресс-фитинги | rn-profi  | 1     | low_attrs          | rn-profi.by    | Varmega VM717001504 15x1/2"       |
| 20610 | KOTLOV-005776 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718181818 18x18x18      |
| 20611 | KOTLOV-005777 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718222222 22x22x22      |
| 20612 | KOTLOV-005778 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718282828 28x28x28      |
| 20613 | KOTLOV-005779 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718353535 35x35x35      |
| 20614 | KOTLOV-005780 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718424242 42x42x42      |
| 20615 | KOTLOV-005781 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM718545454 54x54x54      |
| 20616 | KOTLOV-005782 | Varmega | Пресс-фитинги | rn-profi  | 13    | no_photo           | rn-profi.by    | Varmega VM719181518 18x15x18      |
| 20617 | KOTLOV-005783 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719221522 22x15x22      |
| 20618 | KOTLOV-005784 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719221822 22x18x22      |
| 20619 | KOTLOV-005785 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719281528 28x15x28      |
| 20620 | KOTLOV-005786 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719281828 28x18x28      |
| 20621 | KOTLOV-005787 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719282222 28x22x22      |
| 20622 | KOTLOV-005788 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719282228 28x22x28      |
| 20623 | KOTLOV-005789 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719282822 28x28x22      |
| 20624 | KOTLOV-005790 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719351535 35x15x35      |
| 20625 | KOTLOV-005791 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719352235 35x22x35      |
| 20626 | KOTLOV-005792 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719352835 35x28x35      |
| 20627 | KOTLOV-005793 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719423542 42x35x42      |
| 20628 | KOTLOV-005794 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM719544254 54x42x54      |
| 20630 | KOTLOV-005796 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720180418 18x1/2"x18    |
| 20631 | KOTLOV-005797 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720180518 18x3/4"x18    |
| 20632 | KOTLOV-005798 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720220422 22x1/2"x22    |
| 20633 | KOTLOV-005799 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720220522 22x3/4"x22    |
| 20634 | KOTLOV-005800 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720280428 28x1/2"x28    |
| 20635 | KOTLOV-005801 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720280528 28x3/4"x28    |
| 20636 | KOTLOV-005802 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720280628 28x1"x28      |
| 20637 | KOTLOV-005803 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720350435 35x1/2"x35    |
| 20638 | KOTLOV-005804 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720350635 35x1"x35      |
| 20639 | KOTLOV-005805 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720350735 35x1 1/4"x35  |
| 20640 | KOTLOV-005806 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720420442 42x1/2"x42    |
| 20641 | KOTLOV-005807 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720420642 42x1"x42      |
| 20642 | KOTLOV-005808 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720420742 42x1 1/4"x42  |
| 20643 | KOTLOV-005809 | Varmega | Пресс-фитинги | rn-profi  | 0     | no_photo,low_attrs | rn-profi.by    | Varmega VM720540454 54x1/2"x54    |
+-------+---------------+---------+---------------+-----------+-------+--------------------+----------------+-----------------------------------+

```
