# Server Artisan Result

- Time: 2026-07-11 16:42:15 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --active-only --not-archived --with-source-only --limit=120`
- Log file: `storage/logs/server-artisan-content-health-source-audit.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   2183c38..5b828fa  main       -> origin/main
Updating 2183c38..5b828fa
Fast-forward
 .github/server-artisan-result.md | 28 ++++++++++++++--------------
 .github/server-artisan-task.json |  6 +++---
 2 files changed, 17 insertions(+), 17 deletions(-)
Products with content-health issues: 992
Showing rows: 120 (limit 120)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 515      |
| no_content | 83       |
| no_short   | 347      |
| low_attrs  | 769      |
| no_source  | 0        |
+------------+----------+
By supplier
+---------------------+----------+----------+------------+-----------+
| Name                | Products | No photo | No content | Low attrs |
+---------------------+----------+----------+------------+-----------+
| rn-profi            | 268      | 245      | 0          | 266       |
| ligmet              | 206      | 139      | 72         | 197       |
| rusklimat           | 181      | 52       | 2          | 134       |
| bania               | 72       | 0        | 0          | 69        |
| metabel             | 60       | 9        | 0          | 14        |
| belkomin            | 59       | 0        | 0          | 0         |
| elicon              | 47       | 0        | 0          | 0         |
| akvatermex          | 36       | 36       | 0          | 36        |
| maitek-group        | 23       | 3        | 1          | 21        |
| gazkotelbel         | 22       | 15       | 7          | 14        |
| tsk_nasosy          | 16       | 16       | 1          | 16        |
| rn-profi, rusklimat | 2        | 0        | 0          | 2         |
+---------------------+----------+----------+------------+-----------+
By brand
+--------------+----------+----------+------------+-----------+
| Name         | Products | No photo | No content | Low attrs |
+--------------+----------+----------+------------+-----------+
| Varmega      | 281      | 245      | 1          | 279       |
| Kratki       | 110      | 60       | 16         | 108       |
| Royal Thermo | 69       | 5        | 0          | 64        |
| Мета-Бел     | 60       | 9        | 0          | 14        |
| TIS          | 59       | 0        | 0          | 0         |
| Ballu        | 49       | 34       | 0          | 17        |
| БелОМО       | 47       | 0        | 0          | 0         |
| Blist        | 37       | 32       | 31         | 32        |
| Thermex      | 33       | 33       | 0          | 33        |
| Ермак        | 32       | 23       | 22         | 32        |
| Везувий      | 24       | 0        | 0          | 22        |
| DoorWood     | 22       | 0        | 0          | 22        |
| ASTON        | 21       | 0        | 0          | 21        |
| Greolit      | 18       | 0        | 0          | 18        |
| UNIPUMP      | 15       | 15       | 0          | 15        |
| Житомир      | 15       | 8        | 0          | 7         |
| Hommyn       | 11       | 0        | 0          | 11        |
| НЗС          | 11       | 0        | 0          | 11        |
| XOMMET       | 10       | 0        | 0          | 10        |
| GKB          | 7        | 7        | 7          | 7         |
+--------------+----------+----------+------------+-----------+
By category
+------------------------------------+----------+----------+------------+-----------+
| Name                               | Products | No photo | No content | Low attrs |
+------------------------------------+----------+----------+------------+-----------+
| Пресс-фитинги                      | 215      | 213      | 0          | 213       |
| Каминные решётки                   | 92       | 49       | 5          | 92        |
| Твердотопливные                    | 87       | 8        | 0          | 18        |
| Дровницы и каминные принадлежности | 59       | 59       | 59         | 59        |
| Печи-камины                        | 51       | 18       | 2          | 21        |
| Котлы отопления                    | 51       | 32       | 0          | 51        |
| Электрические                      | 49       | 45       | 1          | 41        |
| Счетчики газа                      | 47       | 0        | 0          | 0         |
| Печное и каминное литье            | 44       | 10       | 2          | 38        |
| Дровяные печи (банные)             | 35       | 4        | 2          | 29        |
| Обогреватели                       | 33       | 18       | 0          | 15        |
| Стальные радиаторы                 | 33       | 0        | 0          | 33        |
| Климат                             | 30       | 24       | 0          | 10        |
| Каминные топки                     | 24       | 7        | 1          | 12        |
| Двери для бани и сауны             | 22       | 0        | 0          | 22        |
| Автоматика и терморегуляторы       | 18       | 0        | 0          | 18        |
| Дренажные насосы                   | 17       | 16       | 1          | 17        |
| Комплекты подключения              | 15       | 0        | 0          | 15        |
| Водяной теплый пол                 | 10       | 0        | 0          | 10        |
| Фильтры                            | 6        | 0        | 0          | 6         |
+------------------------------------+----------+----------+------------+-----------+

+-------+---------------+-------------+------------------------------------+-----------+-------+----------------------------------------+----------------+------------------------------------------------------------+
| ID    | SKU           | Brand       | Category                           | Suppliers | Attrs | Issues                                 | Source domains | Product                                                    |
+-------+---------------+-------------+------------------------------------+-----------+-------+----------------------------------------+----------------+------------------------------------------------------------+
| 12991 | KOTLOV-000713 | AC Electric | Климат                             | rusklimat | 4     | no_photo                               | rusklimat.by   | AC Electric ACE-07 FH/N6                                   |
| 16747 | KOTLOV-004469 | ASTON       | Для печей и каминов                | bania     | 2     | low_attrs                              | pech-aston.ru  | Сетка для камней ASTON                                     |
| 16748 | KOTLOV-004470 | ASTON       | Для печей и каминов                | bania     | 2     | low_attrs                              | pech-aston.ru  | Сетка для камней ASTON (INOX)                              |
| 16807 | KOTLOV-004529 | ASTON       | Печи-камины                        | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь-Камин ASTON 11кВт (180 м3) пристенно-угловой Ø 150мм  |
| 16808 | KOTLOV-004530 | ASTON       | Печи-камины                        | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь-Камин ASTON 12 кВт (200 м3) Призматик                 |
| 16892 | KOTLOV-004614 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 12                                     |
| 16893 | KOTLOV-004615 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 12 INOX                                |
| 16894 | KOTLOV-004616 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 12 INOX стекло                         |
| 16895 | KOTLOV-004617 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 12 стекло                              |
| 16896 | KOTLOV-004618 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 16                                     |
| 16897 | KOTLOV-004619 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 16 INOX                                |
| 16898 | KOTLOV-004620 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 16 INOX стекло                         |
| 16899 | KOTLOV-004621 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 16 стекло                              |
| 16900 | KOTLOV-004622 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 20                                     |
| 16901 | KOTLOV-004623 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 20 INOX                                |
| 16902 | KOTLOV-004624 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 20 INOX стекло                         |
| 16903 | KOTLOV-004625 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 20 стекло                              |
| 16904 | KOTLOV-004626 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON 24 INOX (310) Long                     |
| 16905 | KOTLOV-004627 | ASTON       | Для печей и каминов                | bania     | 2     | low_attrs                              | vezuviy.su     | Стекло ASTON (0,170*0,220)                                 |
| 16919 | KOTLOV-004641 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON «Шторм 16» (ДТ-4)                      |
| 16920 | KOTLOV-004642 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | aston-pech.ru  | Печь для бани ASTON «Шторм 20» (350)                       |
| 16921 | KOTLOV-004643 | ASTON       | Дровяные печи (банные)             | bania     | 2     | low_attrs                              | pech-aston.ru  | Печь для бани ASTON «Шторм 20» Long (350)                  |
| 13056 | KOTLOV-000778 | Ballu       | Электрические                      | rusklimat | 0     | low_attrs                              | rusklimat.by   | Ballu BWH/S 30 Lorica                                      |
| 13057 | KOTLOV-000779 | Ballu       | Электрические                      | rusklimat | 0     | low_attrs                              | rusklimat.by   | Ballu BWH/S 50 Lorica                                      |
| 13058 | KOTLOV-000780 | Ballu       | Электрические                      | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BWH/S 80 Lorica                                      |
| 13059 | KOTLOV-000781 | Ballu       | Электрические                      | rusklimat | 0     | low_attrs                              | rusklimat.by   | Ballu BWH/S 100 Lorica                                     |
| 13133 | KOTLOV-000855 | Ballu       | Обогреватели                       | rusklimat | 5     | no_photo                               | rusklimat.by   | Ballu BEC/EVU-1500                                         |
| 13134 | KOTLOV-000856 | Ballu       | Обогреватели                       | rusklimat | 5     | no_photo                               | rusklimat.by   | Ballu BEC/EVU-2000                                         |
| 13135 | KOTLOV-000857 | Ballu       | Обогреватели                       | rusklimat | 5     | no_photo                               | rusklimat.by   | Ballu BEC/EVU-2500                                         |
| 13137 | KOTLOV-000859 | Ballu       | Обогреватели                       | rusklimat | 27    | no_photo                               | rusklimat.by   | Ballu BFT/EVUR                                             |
| 13140 | KOTLOV-000862 | Ballu       | Обогреватели                       | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BEC/SMT-2500                                         |
| 13141 | KOTLOV-000863 | Ballu       | Обогреватели                       | rusklimat | 3     | no_photo                               | rusklimat.by   | Ballu CWM-02                                               |
| 13145 | KOTLOV-000867 | Ballu       | Обогреватели                       | rusklimat | 57    | no_photo                               | rusklimat.by   | Ballu BEC/EZMR-1500 (SC)                                   |
| 13149 | KOTLOV-000871 | Ballu       | Обогреватели                       | rusklimat | 59    | no_photo                               | rusklimat.by   | Ballu BEC/ETMR-1500                                        |
| 13152 | KOTLOV-000874 | Ballu       | Обогреватели                       | rusklimat | 58    | no_photo                               | rusklimat.by   | Ballu BEC/ETER-1500                                        |
| 13156 | KOTLOV-000878 | Ballu       | Обогреватели                       | rusklimat | 58    | no_photo                               | rusklimat.by   | Ballu BEC/EMT-2000                                         |
| 13157 | KOTLOV-000879 | Ballu       | Обогреватели                       | rusklimat | 5     | no_photo                               | rusklimat.by   | Ballu BEC/EMT-2500                                         |
| 13180 | KOTLOV-000902 | Ballu       | Обогреватели                       | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BOH/CL-05WRN                                         |
| 13183 | KOTLOV-000905 | Ballu       | Обогреватели                       | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BOH/CL-11WRN                                         |
| 13188 | KOTLOV-000910 | Ballu       | Обогреватели                       | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BOH/CB-07W                                           |
| 13189 | KOTLOV-000911 | Ballu       | Обогреватели                       | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BOH/CB-09W                                           |
| 13249 | KOTLOV-000971 | Ballu       | Климат                             | rusklimat | 1     | low_attrs                              | rusklimat.by   | Ballu PTC-1000                                             |
| 13279 | KOTLOV-001001 | Ballu       | Климат                             | rusklimat | 0     | no_photo,low_attrs                     | rusklimat.by   | Ballu UHB-340 MT                                           |
| 13294 | KOTLOV-001016 | Ballu       | Климат                             | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu UHB-1100 AURA                                        |
| 13498 | KOTLOV-001220 | Ballu       | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Ballu BPAC-07 EPW/N6 white                                 |
| 13502 | KOTLOV-001224 | Ballu       | Климат                             | rusklimat | 4     | no_photo                               | rusklimat.by   | Ballu BPAC-18 CE                                           |
| 13503 | KOTLOV-001225 | Ballu       | Климат                             | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BPAC-20 CE                                           |
| 13509 | KOTLOV-001231 | Ballu       | Климат                             | rusklimat | 0     | low_attrs                              | rusklimat.by   | Ballu UniPort 1.0                                          |
| 13612 | KOTLOV-001334 | Ballu       | Климат                             | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BAF-FW 120 N                                         |
| 13650 | KOTLOV-001372 | Ballu       | Климат                             | rusklimat | 0     | no_photo,low_attrs                     | rusklimat.by   | Ballu BFF-907                                              |
| 13653 | KOTLOV-001375 | Ballu       | Климат                             | rusklimat | 3     | no_photo                               | rusklimat.by   | Ballu BFF–802                                              |
| 13655 | KOTLOV-001377 | Ballu       | Климат                             | rusklimat | 3     | no_photo                               | rusklimat.by   | Ballu BFT-110R                                             |
| 13953 | KOTLOV-001675 | Ballu       | Электрические                      | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BWH/S 80 PRIMEX                                      |
| 13954 | KOTLOV-001676 | Ballu       | Электрические                      | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BWH/S 100 PRIMEX                                     |
| 14248 | KOTLOV-001970 | Ballu       | Фильтры                            | rusklimat | 1     | low_attrs                              | rusklimat.by   | Фильтр высокоэффективный Ballu FB-H13-2 для ASP-200*, A... |
| 14249 | KOTLOV-001971 | Ballu       | Фильтры                            | rusklimat | 1     | low_attrs                              | rusklimat.by   | Фильтр тонкой очистки Ballu FB-M5-2 для ASP-200*, AIR M... |
| 14251 | KOTLOV-001973 | Ballu       | Фильтры                            | rusklimat | 1     | low_attrs                              | rusklimat.by   | Фильтр высокоэффективный Ballu FB-H13-1 для ASP-100/100W   |
| 14253 | KOTLOV-001975 | Ballu       | Фильтры                            | rusklimat | 1     | low_attrs                              | rusklimat.by   | Фильтр высокоэффективный Ballu FB-H13-8 для ASP-80         |
| 14256 | KOTLOV-001978 | Ballu       | Фильтры                            | rusklimat | 1     | low_attrs                              | rusklimat.by   | Фильтр CARBON для Ballu ASP-200                            |
| 14259 | KOTLOV-001981 | Ballu       | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Приточный очиститель воздуха Ballu ONEAIR ASP-100          |
| 14265 | KOTLOV-001987 | Ballu       | Обогреватели                       | rusklimat | 5     | no_photo                               | rusklimat.by   | Ballu BEC/AT-1500                                          |
| 14269 | KOTLOV-001991 | Ballu       | Обогреватели                       | rusklimat | 4     | no_photo                               | rusklimat.by   | Ballu BFT/AT                                               |
| 14370 | KOTLOV-002092 | Ballu       | Фильтры                            | rusklimat | 0     | low_attrs                              | rusklimat.by   | Ballu Фильтр HEPA-фильтр + угольный фильтр для климатич... |
| 15317 | KOTLOV-003039 | Ballu       | Обогреватели                       | rusklimat | 2     | low_attrs                              | rusklimat.by   | Вентилятор промышленный Ballu BIF-4BB                      |
| 15318 | KOTLOV-003040 | Ballu       | Обогреватели                       | rusklimat | 2     | low_attrs                              | rusklimat.by   | Вентилятор промышленный Ballu BIF-8BB                      |
| 15319 | KOTLOV-003041 | Ballu       | Обогреватели                       | rusklimat | 2     | low_attrs                              | rusklimat.by   | Вентилятор промышленный Ballu BIF-10SB                     |
| 15321 | KOTLOV-003043 | Ballu       | Обогреватели                       | rusklimat | 2     | low_attrs                              | rusklimat.by   | Вентилятор промышленный Ballu BIF-20DB                     |
| 15993 | KOTLOV-003715 | Ballu       | Климат                             | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BAHD-1000AS                                          |
| 15996 | KOTLOV-003718 | Ballu       | Климат                             | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BAHD-1800                                            |
| 15997 | KOTLOV-003719 | Ballu       | Климат                             | rusklimat | 6     | no_photo                               | rusklimat.by   | Ballu BAHD-1010                                            |
| 15998 | KOTLOV-003720 | Ballu       | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Ballu BAHD-1250                                            |
| 16991 | KOTLOV-004713 | Blist       | Печи-камины                        | ligmet    | 0     | no_photo,no_short,low_attrs            | ligmet.by      | Blist Печь Berna Lux бежевая                               |
| 16992 | KOTLOV-004714 | Blist       | Печи-камины                        | ligmet    | 11    | no_short                               | 100kaminov.by  | Blist Печь Berna Lux красная                               |
| 16995 | KOTLOV-004717 | Blist       | Печи-камины                        | ligmet    | 11    | no_short                               | 100kaminov.by  | Blist Печь Modena бежевая                                  |
| 16998 | KOTLOV-004720 | Blist       | Печи-камины                        | ligmet    | 11    | no_short                               | 100kaminov.by  | Blist Печь Napoli                                          |
| 16999 | KOTLOV-004721 | Blist       | Печи-камины                        | ligmet    | 10    | no_short                               | 100kaminov.by  | Blist Печь Padova E                                        |
| 17000 | KOTLOV-004722 | Blist       | Печи-камины                        | ligmet    | 10    | no_short                               | 100kaminov.by  | Blist Печь Roma E бежевая                                  |
| 21361 | KOTLOV-006527 | Blist       | Дымоходы                           | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Труба 0,5м, Сербия                                   |
| 21362 | KOTLOV-006528 | Blist       | Печи-камины                        | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Печь Roma G бежевая                                  |
| 21363 | KOTLOV-006529 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Бакелитовая ручка Blist (код 2943)                   |
| 21364 | KOTLOV-006530 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Вермикулит на заднюю стенку Blist Polar              |
| 21365 | KOTLOV-006531 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Зольный ящик Oganj (с круглым регулятором подачи ... |
| 21366 | KOTLOV-006532 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 160х295 Blist Ekonomik Lux      |
| 21367 | KOTLOV-006533 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 315x320 Blist (код 2804) (Zar)  |
| 21368 | KOTLOV-006534 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 320x338 Blist (код 3064)        |
| 21369 | KOTLOV-006535 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 325х170 Atene (code 1273)       |
| 21370 | KOTLOV-006536 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Краска Roberlo (для Blist) аэрозоль                  |
| 21371 | KOTLOV-006537 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Наконечник никелированный Blist (к зольному ящику)   |
| 21372 | KOTLOV-006538 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 202x172 (код 2983/2965)    |
| 21373 | KOTLOV-006539 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 240x200 (код 2966)         |
| 21374 | KOTLOV-006540 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 270x240 (код 0669/0890)    |
| 21375 | KOTLOV-006541 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 330x160 (код 2862)         |
| 21376 | KOTLOV-006542 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist Polar 350x275              |
| 21377 | KOTLOV-006543 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Фиксатор стекла Blist                                |
| 21378 | KOTLOV-006544 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Чугунная крышка Modena (Zar) (код 003584)            |
| 21379 | KOTLOV-006545 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist B1 145х340мм (код 3681/1199)   |
| 21380 | KOTLOV-006546 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist B1N 155х340мм (код 3677/4108)  |
| 21381 | KOTLOV-006547 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist B2 135х370мм (код 2879)        |
| 21382 | KOTLOV-006548 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Og1 180х398мм (код 3973/4446)  |
| 21383 | KOTLOV-006549 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Og2 180х370мм (код 3974/4478)  |
| 21384 | KOTLOV-006550 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Og2 Z (для Zar (Modena), с ... |
| 21385 | KOTLOV-006551 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Е1 175х292мм (код 2703/2852)   |
| 21386 | KOTLOV-006552 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Е2/1 113х272мм (код 3680/4447) |
| 21387 | KOTLOV-006553 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Е2 115х302мм (код 4004/4493)   |
| 21388 | KOTLOV-006554 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич (с отверстием) Blist Е3 170х145мм... |
| 21389 | KOTLOV-006555 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шнур белый 8х8мм Blist                               |
| 21390 | KOTLOV-006556 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шнур серый 8х8мм Blist                               |
| 21391 | KOTLOV-006557 | Blist       | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шнур черный 6х6мм Blist                              |
| 14336 | KOTLOV-002058 | Boneco      | Климат                             | rusklimat | 6     | no_photo                               | rusklimat.by   | Boneco P230                                                |
| 16055 | KOTLOV-003777 | Boneco      | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Boneco W200                                                |
| 16057 | KOTLOV-003779 | Boneco      | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Boneco W200A                                               |
| 16061 | KOTLOV-003783 | Boneco      | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Boneco H300                                                |
| 16062 | KOTLOV-003784 | Boneco      | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Boneco H700, белый                                         |
| 16064 | KOTLOV-003786 | Boneco      | Климат                             | rusklimat | 5     | no_photo                               | rusklimat.by   | Boneco H400                                                |
| 3539  | PS-003.539    | DoorWood    | Двери для бани и сауны             | bania     | 1     | low_attrs                              | bania.by       | Дверь DoorWood ТЕПЛАЯ НОЧЬ бронза матоваря 190 х 70        |
| 16391 | KOTLOV-004113 | DoorWood    | Двери для бани и сауны             | bania     | 1     | low_attrs                              | bania.by       | ДВЕРЬ "ТЕПЛОЕ УТРО" САТИН 190 х 70                         |
| 16392 | KOTLOV-004114 | DoorWood    | Двери для бани и сауны             | bania     | 1     | low_attrs                              | bania.by       | ДВЕРЬ "ТЕПЛАЯ НОЧЬ" БРОНЗА МАТОВАЯ 190 х 60                |
| 16393 | KOTLOV-004115 | DoorWood    | Двери для бани и сауны             | bania     | 1     | low_attrs                              | bania.by       | ДВЕРЬ "ТЕПЛОЕ УТРО" САТИН 200 х 80                         |
| 16394 | KOTLOV-004116 | DoorWood    | Двери для бани и сауны             | bania     | 1     | low_attrs                              | bania.by       | ДВЕРЬ "ТЕПЛЫЙ ДЕНЬ" БРОНЗА 190 х 60                        |
| 16395 | KOTLOV-004117 | DoorWood    | Двери для бани и сауны             | bania     | 1     | low_attrs                              | bania.by       | ДВЕРЬ "ЗАТМЕНИЕ" ГРАФИТ МАТОВЫЙ 170 х 70                   |
+-------+---------------+-------------+------------------------------------+-----------+-------+----------------------------------------+----------------+------------------------------------------------------------+

```
