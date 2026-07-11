# Server Artisan Result

- Time: 2026-07-11 21:22:43 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --active-only --not-archived --with-source-only --issues=no_content,no_short --max-attrs=2 --limit=80`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   c42f101..a2e67cc  main       -> origin/main
Updating c42f101..a2e67cc
Fast-forward
 .github/server-artisan-result.md | 244 ++++++---------------------------------
 .github/server-artisan-task.json |   4 +-
 2 files changed, 37 insertions(+), 211 deletions(-)
Products with content-health issues: 188
Showing rows: 80 (limit 80)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 123      |
| no_content | 83       |
| no_short   | 188      |
| low_attrs  | 174      |
| no_source  | 0        |
+------------+----------+
By supplier
+--------------+----------+----------+------------+-----------+
| Name         | Products | No photo | No content | Low attrs |
+--------------+----------+----------+------------+-----------+
| ligmet       | 156      | 104      | 72         | 152       |
| metabel      | 13       | 9        | 0          | 11        |
| elicon       | 8        | 0        | 0          | 0         |
| gazkotelbel  | 7        | 7        | 7          | 7         |
| rusklimat    | 2        | 1        | 2          | 2         |
| tsk_nasosy   | 1        | 1        | 1          | 1         |
| maitek-group | 1        | 1        | 1          | 1         |
+--------------+----------+----------+------------+-----------+
By brand
+------------+----------+----------+------------+-----------+
| Name       | Products | No photo | No content | Low attrs |
+------------+----------+----------+------------+-----------+
| Kratki     | 74       | 32       | 16         | 72        |
| Blist      | 32       | 32       | 31         | 32        |
| Ермак      | 31       | 23       | 22         | 31        |
| Мета-Бел   | 13       | 9        | 0          | 11        |
| БелОМО     | 8        | 0        | 0          | 0         |
| GKB        | 7        | 7        | 7          | 7         |
| MBS        | 5        | 5        | 0          | 5         |
| FireWay    | 3        | 3        | 3          | 3         |
| Invicta    | 3        | 3        | 0          | 3         |
| Nordflam   | 3        | 3        | 0          | 3         |
| КПД        | 3        | 1        | 0          | 1         |
| Electrolux | 1        | 1        | 1          | 1         |
| Ferguss    | 1        | 1        | 0          | 1         |
| JEMIX      | 1        | 1        | 1          | 1         |
| Panadero   | 1        | 1        | 0          | 1         |
| Varmega    | 1        | 0        | 1          | 1         |
| СТЭН       | 1        | 1        | 1          | 1         |
+------------+----------+----------+------------+-----------+
By category
+------------------------------------+----------+----------+------------+-----------+
| Name                               | Products | No photo | No content | Low attrs |
+------------------------------------+----------+----------+------------+-----------+
| Каминные решётки                   | 60       | 23       | 5          | 60        |
| Дровницы и каминные принадлежности | 59       | 59       | 59         | 59        |
| Печное и каминное литье            | 14       | 10       | 2          | 12        |
| Дровяные печи (банные)             | 13       | 4        | 2          | 12        |
| Печи-камины                        | 12       | 11       | 2          | 11        |
| Каминные топки                     | 8        | 5        | 1          | 8         |
| Счетчики газа                      | 8        | 0        | 0          | 0         |
| Циркуляционные насосы              | 4        | 4        | 4          | 4         |
| Сигнализаторы загазованности       | 3        | 3        | 3          | 3         |
| Дымоходы                           | 1        | 1        | 1          | 1         |
| Бани и сауны                       | 1        | 1        | 1          | 1         |
| Дренажные насосы                   | 1        | 1        | 1          | 1         |
| Краны и запорная арматура          | 1        | 0        | 1          | 1         |
| Крепления и монтаж                 | 1        | 0        | 0          | 0         |
| Трубы одностенные                  | 1        | 0        | 0          | 0         |
| Электрические                      | 1        | 1        | 1          | 1         |
+------------------------------------+----------+----------+------------+-----------+

+-------+---------------+------------+------------------------------------+-------------+-------+----------------------------------------+-----------------+------------------------------------------------------------+
| ID    | SKU           | Brand      | Category                           | Suppliers   | Attrs | Issues                                 | Source domains  | Product                                                    |
+-------+---------------+------------+------------------------------------+-------------+-------+----------------------------------------+-----------------+------------------------------------------------------------+
| 16991 | KOTLOV-004713 | Blist      | Печи-камины                        | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Blist Печь Berna Lux бежевая                               |
| 21361 | KOTLOV-006527 | Blist      | Дымоходы                           | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Труба 0,5м, Сербия                                   |
| 21362 | KOTLOV-006528 | Blist      | Печи-камины                        | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Печь Roma G бежевая                                  |
| 21363 | KOTLOV-006529 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Бакелитовая ручка Blist (код 2943)                   |
| 21364 | KOTLOV-006530 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Вермикулит на заднюю стенку Blist Polar              |
| 21365 | KOTLOV-006531 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Зольный ящик Oganj (с круглым регулятором подачи ... |
| 21366 | KOTLOV-006532 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 160х295 Blist Ekonomik Lux      |
| 21367 | KOTLOV-006533 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 315x320 Blist (код 2804) (Zar)  |
| 21368 | KOTLOV-006534 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 320x338 Blist (код 3064)        |
| 21369 | KOTLOV-006535 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 325х170 Atene (code 1273)       |
| 21370 | KOTLOV-006536 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Краска Roberlo (для Blist) аэрозоль                  |
| 21371 | KOTLOV-006537 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Наконечник никелированный Blist (к зольному ящику)   |
| 21372 | KOTLOV-006538 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 202x172 (код 2983/2965)    |
| 21373 | KOTLOV-006539 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 240x200 (код 2966)         |
| 21374 | KOTLOV-006540 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 270x240 (код 0669/0890)    |
| 21375 | KOTLOV-006541 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 330x160 (код 2862)         |
| 21376 | KOTLOV-006542 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist Polar 350x275              |
| 21377 | KOTLOV-006543 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Фиксатор стекла Blist                                |
| 21378 | KOTLOV-006544 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Чугунная крышка Modena (Zar) (код 003584)            |
| 21379 | KOTLOV-006545 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist B1 145х340мм (код 3681/1199)   |
| 21380 | KOTLOV-006546 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist B1N 155х340мм (код 3677/4108)  |
| 21381 | KOTLOV-006547 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist B2 135х370мм (код 2879)        |
| 21382 | KOTLOV-006548 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Og1 180х398мм (код 3973/4446)  |
| 21383 | KOTLOV-006549 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Og2 180х370мм (код 3974/4478)  |
| 21384 | KOTLOV-006550 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Og2 Z (для Zar (Modena), с ... |
| 21385 | KOTLOV-006551 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Е1 175х292мм (код 2703/2852)   |
| 21386 | KOTLOV-006552 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Е2/1 113х272мм (код 3680/4447) |
| 21387 | KOTLOV-006553 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Е2 115х302мм (код 4004/4493)   |
| 21388 | KOTLOV-006554 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич (с отверстием) Blist Е3 170х145мм... |
| 21389 | KOTLOV-006555 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шнур белый 8х8мм Blist                               |
| 21390 | KOTLOV-006556 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шнур серый 8х8мм Blist                               |
| 21391 | KOTLOV-006557 | Blist      | Дровницы и каминные принадлежности | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шнур черный 6х6мм Blist                              |
| 19628 | KOTLOV-004934 | Electrolux | Бани и сауны                       | rusklimat   | 0     | no_photo,no_content,no_short,low_attrs | rusklimat.by    | Electrolux Модуль EAVS/I-30FA-BLACK                        |
| 17002 | KOTLOV-004724 | Ferguss    | Печи-камины                        | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Ferguss Печь Ferguss L (8606107095288) /Lawa Cook/ (УЦЕ... |
| 17004 | KOTLOV-004726 | FireWay    | Печи-камины                        | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | FireWay Печь чугунная TANGO                                |
| 17030 | KOTLOV-004752 | FireWay    | Каминные топки                     | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | FireWay Каминная топка DAGMAR                              |
| 17134 | KOTLOV-004856 | FireWay    | Дровяные печи (банные)             | ligmet      | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | FireWay Банная печь ПароВар 24 Ковка (К505)                |
| 17177 | KOTLOV-004899 | GKB        | Сигнализаторы загазованности       | gazkotelbel | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Датчик угарного газа GKB CO999 (без батареек)              |
| 17178 | KOTLOV-004900 | GKB        | Сигнализаторы загазованности       | gazkotelbel | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Датчик загазованности СО+СН GKB CO888                      |
| 17179 | KOTLOV-004901 | GKB        | Сигнализаторы загазованности       | gazkotelbel | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Датчик загазованности дым + СО GKB CO777                   |
| 17180 | KOTLOV-004902 | GKB        | Циркуляционные насосы              | gazkotelbel | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/4-130                       |
| 17203 | KOTLOV-004925 | GKB        | Циркуляционные насосы              | gazkotelbel | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/6-130                       |
| 17204 | KOTLOV-004926 | GKB        | Циркуляционные насосы              | gazkotelbel | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/4-180                       |
| 17205 | KOTLOV-004927 | GKB        | Циркуляционные насосы              | gazkotelbel | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/6-180                       |
| 17031 | KOTLOV-004753 | Invicta    | Каминные топки                     | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Invicta Топка каминная FIREPLACE 1100 GUILLOTINE (P681144) |
| 17032 | KOTLOV-004754 | Invicta    | Каминные топки                     | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Invicta Топка каминная FIREPLACE 700 AIR C. interior (P... |
| 17033 | KOTLOV-004755 | Invicta    | Каминные топки                     | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Invicta Топка каминная FIREPLACE 700 COMPACT VALVE (P92... |
| 16321 | KOTLOV-004043 | JEMIX      | Дренажные насосы                   | tsk_nasosy  | 0     | no_photo,no_content,no_short,low_attrs | aqualider.by    | JEMIX Канализационный погружной насос, 100КПН100-25-11     |
| 10790 | PS-010.790    | Kratki     | Печное и каминное литье            | ligmet      | 5     | no_short                               | ligmet.by       | Дверь каминная Kratki Zuzia                                |
| 10807 | PS-010.807    | Kratki     | Печное и каминное литье            | ligmet      | 5     | no_short                               | ligmet.by       | Дверь каминная Kratki Maja                                 |
| 17035 | KOTLOV-004757 | Kratki     | Каминные топки                     | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Каминная топка FLOKI/L/P                            |
| 17037 | KOTLOV-004759 | Kratki     | Каминные топки                     | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Каминная топка FLOKI/L/PF                           |
| 17038 | KOTLOV-004760 | Kratki     | Каминные топки                     | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Каминная топка FLOKI/L/PF/BLACK                     |
| 17039 | KOTLOV-004761 | Kratki     | Каминные топки                     | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Каминная топка FLOKI/M/P/BLACK                      |
| 17042 | KOTLOV-004764 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная B 11х11 белая                      |
| 17043 | KOTLOV-004765 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная B 11х17 белая                      |
| 17044 | KOTLOV-004766 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х17 белая                      |
| 17045 | KOTLOV-004767 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х30 белая                      |
| 17046 | KOTLOV-004768 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х37 белая                      |
| 17047 | KOTLOV-004769 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х49 белая                      |
| 17048 | KOTLOV-004770 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х17 белая с жалюзи            |
| 17049 | KOTLOV-004771 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х30 белая с жалюзи            |
| 17050 | KOTLOV-004772 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х37 белая с жалюзи            |
| 17051 | KOTLOV-004773 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х49 белая с жалюзи            |
| 17052 | KOTLOV-004774 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная C 11х17 черная                     |
| 17053 | KOTLOV-004775 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная C 17х30 черная                     |
| 17054 | KOTLOV-004776 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная C 17х37 черная                     |
| 17055 | KOTLOV-004777 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная C 17х49 черная                     |
| 17056 | KOTLOV-004778 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная CX 17х17 черная с жалюзи           |
| 17057 | KOTLOV-004779 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная CX 17х30 черная с жалюзи           |
| 17058 | KOTLOV-004780 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная CX 17х37 черная с жалюзи           |
| 17059 | KOTLOV-004781 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная CX 17х49 черная с жалюзи           |
| 17060 | KOTLOV-004782 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 11х17 графитовая                 |
| 17061 | KOTLOV-004783 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная G 17х17 графитовая                 |
| 17062 | KOTLOV-004784 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная G 17х30 графитовая                 |
| 17063 | KOTLOV-004785 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 17х37 графитовая                 |
| 17064 | KOTLOV-004786 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 17х49 графитовая                 |
| 17065 | KOTLOV-004787 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 22х30 графитовая                 |
| 17066 | KOTLOV-004788 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная GX 17х17 графитовая с жалюзи       |
| 17067 | KOTLOV-004789 | Kratki     | Каминные решётки                   | ligmet      | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная GX 17х30 графитовая с жалюзи       |
+-------+---------------+------------+------------------------------------+-------------+-------+----------------------------------------+-----------------+------------------------------------------------------------+

```
