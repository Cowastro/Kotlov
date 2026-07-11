# Server Artisan Result

- Time: 2026-07-11 19:12:41 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --active-only --not-archived --with-source-only --issues=no_content,no_short,thin_content --limit=200`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   5de2b75..a108088  main       -> origin/main
Updating 5de2b75..a108088
Fast-forward
 .github/server-artisan-result.md | 172 +++++++++++++++++++++++++++++++++++++--
 .github/server-artisan-task.json |   8 +-
 2 files changed, 167 insertions(+), 13 deletions(-)
Products with content-health issues: 188
Showing rows: 188 (limit 200)

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

+-------+---------------+------------+------------------------------------+--------------+-------+----------------------------------------+-----------------+------------------------------------------------------------+
| ID    | SKU           | Brand      | Category                           | Suppliers    | Attrs | Issues                                 | Source domains  | Product                                                    |
+-------+---------------+------------+------------------------------------+--------------+-------+----------------------------------------+-----------------+------------------------------------------------------------+
| 16991 | KOTLOV-004713 | Blist      | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Blist Печь Berna Lux бежевая                               |
| 21361 | KOTLOV-006527 | Blist      | Дымоходы                           | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Труба 0,5м, Сербия                                   |
| 21362 | KOTLOV-006528 | Blist      | Печи-камины                        | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Печь Roma G бежевая                                  |
| 21363 | KOTLOV-006529 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Бакелитовая ручка Blist (код 2943)                   |
| 21364 | KOTLOV-006530 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Вермикулит на заднюю стенку Blist Polar              |
| 21365 | KOTLOV-006531 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Зольный ящик Oganj (с круглым регулятором подачи ... |
| 21366 | KOTLOV-006532 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 160х295 Blist Ekonomik Lux      |
| 21367 | KOTLOV-006533 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 315x320 Blist (код 2804) (Zar)  |
| 21368 | KOTLOV-006534 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 320x338 Blist (код 3064)        |
| 21369 | KOTLOV-006535 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Колосниковая решетка 325х170 Atene (code 1273)       |
| 21370 | KOTLOV-006536 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Краска Roberlo (для Blist) аэрозоль                  |
| 21371 | KOTLOV-006537 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Наконечник никелированный Blist (к зольному ящику)   |
| 21372 | KOTLOV-006538 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 202x172 (код 2983/2965)    |
| 21373 | KOTLOV-006539 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 240x200 (код 2966)         |
| 21374 | KOTLOV-006540 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 270x240 (код 0669/0890)    |
| 21375 | KOTLOV-006541 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist 330x160 (код 2862)         |
| 21376 | KOTLOV-006542 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Стекло термостойкое Blist Polar 350x275              |
| 21377 | KOTLOV-006543 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Фиксатор стекла Blist                                |
| 21378 | KOTLOV-006544 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Чугунная крышка Modena (Zar) (код 003584)            |
| 21379 | KOTLOV-006545 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist B1 145х340мм (код 3681/1199)   |
| 21380 | KOTLOV-006546 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist B1N 155х340мм (код 3677/4108)  |
| 21381 | KOTLOV-006547 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist B2 135х370мм (код 2879)        |
| 21382 | KOTLOV-006548 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Og1 180х398мм (код 3973/4446)  |
| 21383 | KOTLOV-006549 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Og2 180х370мм (код 3974/4478)  |
| 21384 | KOTLOV-006550 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Og2 Z (для Zar (Modena), с ... |
| 21385 | KOTLOV-006551 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Е1 175х292мм (код 2703/2852)   |
| 21386 | KOTLOV-006552 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Е2/1 113х272мм (код 3680/4447) |
| 21387 | KOTLOV-006553 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич Blist Е2 115х302мм (код 4004/4493)   |
| 21388 | KOTLOV-006554 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шамотный кирпич (с отверстием) Blist Е3 170х145мм... |
| 21389 | KOTLOV-006555 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шнур белый 8х8мм Blist                               |
| 21390 | KOTLOV-006556 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шнур серый 8х8мм Blist                               |
| 21391 | KOTLOV-006557 | Blist      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Blist Шнур черный 6х6мм Blist                              |
| 19628 | KOTLOV-004934 | Electrolux | Бани и сауны                       | rusklimat    | 0     | no_photo,no_content,no_short,low_attrs | rusklimat.by    | Electrolux Модуль EAVS/I-30FA-BLACK                        |
| 17002 | KOTLOV-004724 | Ferguss    | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Ferguss Печь Ferguss L (8606107095288) /Lawa Cook/ (УЦЕ... |
| 17004 | KOTLOV-004726 | FireWay    | Печи-камины                        | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | FireWay Печь чугунная TANGO                                |
| 17030 | KOTLOV-004752 | FireWay    | Каминные топки                     | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | FireWay Каминная топка DAGMAR                              |
| 17134 | KOTLOV-004856 | FireWay    | Дровяные печи (банные)             | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | FireWay Банная печь ПароВар 24 Ковка (К505)                |
| 17177 | KOTLOV-004899 | GKB        | Сигнализаторы загазованности       | gazkotelbel  | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Датчик угарного газа GKB CO999 (без батареек)              |
| 17178 | KOTLOV-004900 | GKB        | Сигнализаторы загазованности       | gazkotelbel  | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Датчик загазованности СО+СН GKB CO888                      |
| 17179 | KOTLOV-004901 | GKB        | Сигнализаторы загазованности       | gazkotelbel  | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Датчик загазованности дым + СО GKB CO777                   |
| 17180 | KOTLOV-004902 | GKB        | Циркуляционные насосы              | gazkotelbel  | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/4-130                       |
| 17203 | KOTLOV-004925 | GKB        | Циркуляционные насосы              | gazkotelbel  | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/6-130                       |
| 17204 | KOTLOV-004926 | GKB        | Циркуляционные насосы              | gazkotelbel  | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/4-180                       |
| 17205 | KOTLOV-004927 | GKB        | Циркуляционные насосы              | gazkotelbel  | 0     | no_photo,no_content,no_short,low_attrs | gazkotelbel.com | Циркуляционный насос GKB GT 25/6-180                       |
| 17031 | KOTLOV-004753 | Invicta    | Каминные топки                     | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Invicta Топка каминная FIREPLACE 1100 GUILLOTINE (P681144) |
| 17032 | KOTLOV-004754 | Invicta    | Каминные топки                     | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Invicta Топка каминная FIREPLACE 700 AIR C. interior (P... |
| 17033 | KOTLOV-004755 | Invicta    | Каминные топки                     | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Invicta Топка каминная FIREPLACE 700 COMPACT VALVE (P92... |
| 16321 | KOTLOV-004043 | JEMIX      | Дренажные насосы                   | tsk_nasosy   | 0     | no_photo,no_content,no_short,low_attrs | aqualider.by    | JEMIX Канализационный погружной насос, 100КПН100-25-11     |
| 10790 | PS-010.790    | Kratki     | Печное и каминное литье            | ligmet       | 5     | no_short                               | ligmet.by       | Дверь каминная Kratki Zuzia                                |
| 10807 | PS-010.807    | Kratki     | Печное и каминное литье            | ligmet       | 5     | no_short                               | ligmet.by       | Дверь каминная Kratki Maja                                 |
| 17035 | KOTLOV-004757 | Kratki     | Каминные топки                     | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Каминная топка FLOKI/L/P                            |
| 17037 | KOTLOV-004759 | Kratki     | Каминные топки                     | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Каминная топка FLOKI/L/PF                           |
| 17038 | KOTLOV-004760 | Kratki     | Каминные топки                     | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Каминная топка FLOKI/L/PF/BLACK                     |
| 17039 | KOTLOV-004761 | Kratki     | Каминные топки                     | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Каминная топка FLOKI/M/P/BLACK                      |
| 17042 | KOTLOV-004764 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная B 11х11 белая                      |
| 17043 | KOTLOV-004765 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная B 11х17 белая                      |
| 17044 | KOTLOV-004766 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х17 белая                      |
| 17045 | KOTLOV-004767 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х30 белая                      |
| 17046 | KOTLOV-004768 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х37 белая                      |
| 17047 | KOTLOV-004769 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная B 17х49 белая                      |
| 17048 | KOTLOV-004770 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х17 белая с жалюзи            |
| 17049 | KOTLOV-004771 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х30 белая с жалюзи            |
| 17050 | KOTLOV-004772 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х37 белая с жалюзи            |
| 17051 | KOTLOV-004773 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная BX 17х49 белая с жалюзи            |
| 17052 | KOTLOV-004774 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная C 11х17 черная                     |
| 17053 | KOTLOV-004775 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная C 17х30 черная                     |
| 17054 | KOTLOV-004776 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная C 17х37 черная                     |
| 17055 | KOTLOV-004777 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная C 17х49 черная                     |
| 17056 | KOTLOV-004778 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная CX 17х17 черная с жалюзи           |
| 17057 | KOTLOV-004779 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная CX 17х30 черная с жалюзи           |
| 17058 | KOTLOV-004780 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная CX 17х37 черная с жалюзи           |
| 17059 | KOTLOV-004781 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная CX 17х49 черная с жалюзи           |
| 17060 | KOTLOV-004782 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 11х17 графитовая                 |
| 17061 | KOTLOV-004783 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная G 17х17 графитовая                 |
| 17062 | KOTLOV-004784 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная G 17х30 графитовая                 |
| 17063 | KOTLOV-004785 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 17х37 графитовая                 |
| 17064 | KOTLOV-004786 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 17х49 графитовая                 |
| 17065 | KOTLOV-004787 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная G 22х30 графитовая                 |
| 17066 | KOTLOV-004788 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная GX 17х17 графитовая с жалюзи       |
| 17067 | KOTLOV-004789 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная GX 17х30 графитовая с жалюзи       |
| 17068 | KOTLOV-004790 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная GX 17х37 графитовая с жалюзи       |
| 17069 | KOTLOV-004791 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная GX 17х49 графитовая с жалюзи       |
| 17070 | KOTLOV-004792 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная Oscar графитовая с жалюзи OGX 1... |
| 17071 | KOTLOV-004793 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная Oskar O 17х17                      |
| 17072 | KOTLOV-004794 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная Oskar медная гальванизированная... |
| 17073 | KOTLOV-004795 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная Oskar черная-латунь OCZ 17х17      |
| 17074 | KOTLOV-004796 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная R 17х17 рустик                     |
| 17075 | KOTLOV-004797 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная R 17х30 рустик                     |
| 17077 | KOTLOV-004799 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная R 17х49 рустик                     |
| 17078 | KOTLOV-004800 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная RX 17х17 рустик с жалюзи           |
| 17079 | KOTLOV-004801 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная RX 17х30 рустик с жалюзи           |
| 17081 | KOTLOV-004803 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная RX 17х49 рустик с жалюзи           |
| 17082 | KOTLOV-004804 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная VENUS белая VB 17х17               |
| 17083 | KOTLOV-004805 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная VENUS графитовая VG 17х17          |
| 17084 | KOTLOV-004806 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная VENUS кремовая VK 17х17            |
| 17085 | KOTLOV-004807 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Kratki Решетка каминная крем FRESH K 17х17                 |
| 17091 | KOTLOV-004813 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NL черная C 547х766х120       |
| 17092 | KOTLOV-004814 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NL черная C 600х400х120       |
| 17093 | KOTLOV-004815 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NL черная C 600х400х90        |
| 17094 | KOTLOV-004816 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NL черная C 800х400х120       |
| 17095 | KOTLOV-004817 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NL черная C 800х400х90        |
| 17096 | KOTLOV-004818 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NL черная C 800х400х90 SF     |
| 17100 | KOTLOV-004822 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NP черная C 547х766х120       |
| 17101 | KOTLOV-004823 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NP черная C 600х400х120       |
| 17102 | KOTLOV-004824 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NP черная C 600х400х90        |
| 17103 | KOTLOV-004825 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Kratki Решетка каминная LUFT NP черная C 800х400х120       |
| 17104 | KOTLOV-004826 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_content,no_short,low_attrs          | ligmet.by       | Kratki Решетка каминная LUFT NP черная C 800х400х90        |
| 17105 | KOTLOV-004827 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_content,no_short,low_attrs          | ligmet.by       | Kratki Решетка каминная LUFT NP черная C 800х400х90 SF     |
| 17118 | KOTLOV-004840 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_content,no_short,low_attrs          | ligmet.by       | Kratki Решетка каминная LUFT черная C 12х40                |
| 17119 | KOTLOV-004841 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_content,no_short,low_attrs          | ligmet.by       | Kratki Решетка каминная LUFT черная C 12х80                |
| 17130 | KOTLOV-004852 | Kratki     | Каминные решётки                   | ligmet       | 0     | no_content,no_short,low_attrs          | ligmet.by       | Kratki Решетка каминная никелированная N 17х17 (имеет п... |
| 21392 | KOTLOV-006558 | Kratki     | Печное и каминное литье            | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Дверь каминная AMELIA                               |
| 21393 | KOTLOV-006559 | Kratki     | Печное и каминное литье            | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Дверь каминная OLIWIA                               |
| 21394 | KOTLOV-006560 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Настенный биокамин DELTA2/CZARNY/TUV                |
| 21395 | KOTLOV-006561 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Настенный биокамин DELTA3/TUV                       |
| 21396 | KOTLOV-006562 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Настенный биокамин DELTA/FLAT/TUV                   |
| 21397 | KOTLOV-006563 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Настенный биокамин GOLF/CZARNY/TUV                  |
| 21398 | KOTLOV-006564 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Настольный биокамин GALINA/TUV                      |
| 21399 | KOTLOV-006565 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Остекление для биокамина DELTA 2                    |
| 21400 | KOTLOV-006566 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Остекление для биокамина DELTA 3                    |
| 21401 | KOTLOV-006567 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Остекление для биокамина DELTA FLAT                 |
| 21402 | KOTLOV-006568 | Kratki     | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Kratki Адаптер подвода воздуха d.100                       |
| 17012 | KOTLOV-004734 | MBS        | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | MBS Печь OLYMPIA L черная                                  |
| 17014 | KOTLOV-004736 | MBS        | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | MBS Печь OLYMP L красная                                   |
| 17015 | KOTLOV-004737 | MBS        | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | MBS Печь OLYMP PLUS L кремовая                             |
| 17016 | KOTLOV-004738 | MBS        | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | MBS Плита на твердом топливе THERMO MAGNUM 4D D S (правый) |
| 17017 | KOTLOV-004739 | MBS        | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | MBS Плита на твердом топливе THERMO MAGNUM 4D L S (левый)  |
| 17131 | KOTLOV-004853 | Nordflam   | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Nordflam Решетка каминная AERO 90*600*400 белая левая      |
| 17132 | KOTLOV-004854 | Nordflam   | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Nordflam Решетка каминная AERO 90*600 белая                |
| 17133 | KOTLOV-004855 | Nordflam   | Каминные решётки                   | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Nordflam Решетка каминная AERO 90*800 белая                |
| 17026 | KOTLOV-004748 | Panadero   | Печи-камины                        | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Panadero Печь-камин ONIX Wall Ecodesign                    |
| 15428 | KOTLOV-003150 | Varmega    | Краны и запорная арматура          | rusklimat    | 0     | no_content,no_short,low_attrs          | rusklimat.by    | Клапан радиаторный VARMEGA 1/2" x 3/4"EK термостатическ... |
| 12283 | KOTLOV-000005 | БелОМО     | Счетчики газа                      | elicon       | 8     | no_short                               | elicon.by       | Счетчик газа диафрагменный СГД 4-3-1 G2,5 И L=110 (левый)  |
| 12284 | KOTLOV-000006 | БелОМО     | Счетчики газа                      | elicon       | 8     | no_short                               | elicon.by       | Счетчик газа диафрагменный СГД 4-3-1-G4ТИ (левый) L=110... |
| 12295 | KOTLOV-000017 | БелОМО     | Счетчики газа                      | elicon       | 6     | no_short                               | elicon.by       | Счетчик газа ультразвуковой “СКАТ”- G10 RP                 |
| 12301 | KOTLOV-000023 | БелОМО     | Счетчики газа                      | elicon       | 5     | no_short                               | elicon.by       | Счетчик газа ультразвуковой ВЕГА G1.6                      |
| 12302 | KOTLOV-000024 | БелОМО     | Счетчики газа                      | elicon       | 19    | no_short                               | elicon.by       | Счетчик газа ультразвуковой ВЕГА G1.6 В                    |
| 12303 | KOTLOV-000025 | БелОМО     | Счетчики газа                      | elicon       | 17    | no_short                               | elicon.by       | Счетчик газа ультразвуковой ВЕГА G2.5                      |
| 12309 | KOTLOV-000031 | БелОМО     | Счетчики газа                      | elicon       | 6     | no_short                               | elicon.by       | Счетчик газа ультразвуковой КАТА-G4 В-2                    |
| 12316 | KOTLOV-000038 | БелОМО     | Счетчики газа                      | elicon       | 16    | no_short                               | elicon.by       | Счетчик газа ультразвуковой КАТА-G6-3                      |
| 17146 | KOTLOV-004868 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак ЕRМАК 16 Сетка - Премиум Чугун                       |
| 17147 | KOTLOV-004869 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак ЕRМАК 20 Премиум Чугун                               |
| 17148 | KOTLOV-004870 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак ЕRМАК 20 Сетка - Премиум Чугун                       |
| 17149 | KOTLOV-004871 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак ЕRМАК 24 Сетка - Премиум Чугун                       |
| 17153 | KOTLOV-004875 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак Короб для камней ERMAK CUBE 16                       |
| 17154 | KOTLOV-004876 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | Ермак Портал ERMAK CUBE 16 Comfort L160                    |
| 17155 | KOTLOV-004877 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак Портал ERMAK CUBE 16 Comfort L250                    |
| 17157 | KOTLOV-004879 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак Экономайзер ERMAK BLACK L500 (INOX-304)              |
| 17158 | KOTLOV-004880 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_short,low_attrs                     | ligmet.by       | Ермак Экономайзер ERMAK CHROM L500 (INOX-430)              |
| 21339 | KOTLOV-006505 | Ермак      | Дровяные печи (банные)             | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак ЕRMAK 16 Стандарт Сталь                              |
| 21340 | KOTLOV-006506 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Катализатор горения Stoker-G                         |
| 21341 | KOTLOV-006507 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Конвектор дымохода ERMAK CHROM L500 D150 (INOX-430)  |
| 21342 | KOTLOV-006508 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак ТЭН 2,5 кВт G1 1/4"                                  |
| 21343 | KOTLOV-006509 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Бак навесной ERMAK - 40л (AISI-439, для 16/20)       |
| 21344 | KOTLOV-006510 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Бак навесной ERMAK - 40л (INOX-304, для 16/20)       |
| 21345 | KOTLOV-006511 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Модуль закрытой каменки ERMAK 12/16                  |
| 21346 | KOTLOV-006512 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Модуль закрытой каменки ERMAK 12/16 Сетка (для пе... |
| 21347 | KOTLOV-006513 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Модуль закрытой каменки ERMAK 20/24                  |
| 21348 | KOTLOV-006514 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Модуль закрытой каменки ERMAK 20/24 Сетка (для пе... |
| 21349 | KOTLOV-006515 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Парообразователь ERMAK CHROM | INOX-430 (на все м... |
| 21350 | KOTLOV-006516 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Сетка-каменка ERMAK - 40 кг (Серия 12/16 - Станда... |
| 21351 | KOTLOV-006517 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Сетка-каменка ERMAK - 40 кг (Серия 12 - Классика,... |
| 21352 | KOTLOV-006518 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Сетка-каменка ERMAK - 50 кг (Серия 16/20/24 - Кла... |
| 21353 | KOTLOV-006519 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Сетка-каменка ERMAK - 50 кг (Серия 20/24 - Станда... |
| 21354 | KOTLOV-006520 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Стекло термостойкое 153x153x4,0 (ПБС)                |
| 21355 | KOTLOV-006521 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Теплообменник универсальный ERMAK (AISI 430, 2кВт)   |
| 21356 | KOTLOV-006522 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Теплообменник универсальный ERMAK INOX (AISI 304,... |
| 21357 | KOTLOV-006523 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Удлинитель тоннеля ERMAK 12-24 L150+зольник          |
| 21358 | KOTLOV-006524 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Удлинитель тоннеля ERMAK 12-24 L200+зольник          |
| 21359 | KOTLOV-006525 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Удлинитель тоннеля ERMAK 12-24 L250+зольник          |
| 21360 | KOTLOV-006526 | Ермак      | Дровницы и каминные принадлежности | ligmet       | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by       | Ермак Удлинитель тоннеля ERMAK 12-24 L90+зольник           |
| 11848 | PS-011.848    | КПД        | Крепления и монтаж                 | ligmet       | 11    | no_short                               | ligmet.by       | КПД ЧЕРНЫЙ Розета 0,7мм ф150                               |
| 11849 | PS-011.849    | КПД        | Трубы одностенные                  | ligmet       | 11    | no_short                               | ligmet.by       | КПД ЧЕРНЫЙ Труба 250мм 2мм ф150                            |
| 17156 | KOTLOV-004878 | КПД        | Дровяные печи (банные)             | ligmet       | 0     | no_photo,no_short,low_attrs            | ligmet.by       | КПД Бак теплообменник КПД 7л (202/1,0мм) ф115              |
| 5701  | PS-005.701    | Мета-Бел   | Печи-камины                        | metabel      | 20    | no_short                               | metabel.by      | Печь-камин Мета-Бел Сена 7 кВт (АОТ-7,0)                   |
| 6589  | PS-006.589    | Мета-Бел   | Дровяные печи (банные)             | metabel      | 19    | no_short                               | metabel.by      | Печь-каменка Мета-Бел ПБМ 16 (в модификации ПС)            |
| 12381 | KOTLOV-000103 | Мета-Бел   | Печи-камины                        | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Печь-камин Мета-Бел Нарва 7М                               |
| 12390 | KOTLOV-000112 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_short,low_attrs                     | metabel.by      | Очаг-гриль ОГ-01                                           |
| 12391 | KOTLOV-000113 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_short,low_attrs                     | metabel.by      | Очаг-гриль ОГ-02                                           |
| 12392 | KOTLOV-000114 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Труба 1 м                                                  |
| 12393 | KOTLOV-000115 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Труба 0,5 м                                                |
| 12394 | KOTLOV-000116 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Фартук трубы                                               |
| 12395 | KOTLOV-000117 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Проходной элемент                                          |
| 12396 | KOTLOV-000118 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Фартук проходного элемента                                 |
| 12398 | KOTLOV-000120 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Мангал М-01 (3 мм.)                                        |
| 12399 | KOTLOV-000121 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Дверь печная ДП 308                                        |
| 12400 | KOTLOV-000122 | Мета-Бел   | Печное и каминное литье            | metabel      | 0     | no_photo,no_short,low_attrs            | metabel.by      | Дверь печная ДП-14                                         |
| 20798 | KOTLOV-005964 | СТЭН       | Электрические                      | maitek-group | 0     | no_photo,no_content,no_short,low_attrs | stenbel.by      | СТЭН Котел "СТЭН ЭВПМ 12" 380                              |
+-------+---------------+------------+------------------------------------+--------------+-------+----------------------------------------+-----------------+------------------------------------------------------------+

```
