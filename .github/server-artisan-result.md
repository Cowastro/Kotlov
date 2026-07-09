# Server Artisan Result

- Time: 2026-07-09 18:47:16 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --brand=Blist --active-only --not-archived --with-source-only --max-attrs=2 --limit=80`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
Products with content-health issues: 37
Showing rows: 37 (limit 80)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 37       |
| no_content | 31       |
| no_short   | 37       |
| low_attrs  | 37       |
| no_source  | 0        |
+------------+----------+
By supplier
+--------+----------+----------+------------+-----------+
| Name   | Products | No photo | No content | Low attrs |
+--------+----------+----------+------------+-----------+
| ligmet | 37       | 37       | 31         | 37        |
+--------+----------+----------+------------+-----------+
By brand
+-------+----------+----------+------------+-----------+
| Name  | Products | No photo | No content | Low attrs |
+-------+----------+----------+------------+-----------+
| Blist | 37       | 37       | 31         | 37        |
+-------+----------+----------+------------+-----------+
By category
+------------------------------------+----------+----------+------------+-----------+
| Name                               | Products | No photo | No content | Low attrs |
+------------------------------------+----------+----------+------------+-----------+
| Дровницы и каминные принадлежности | 29       | 29       | 29         | 29        |
| Печи-камины                        | 7        | 7        | 1          | 7         |
| Дымоходы                           | 1        | 1        | 1          | 1         |
+------------------------------------+----------+----------+------------+-----------+

+-------+---------------+-------+------------------------------------+-----------+-------+----------------------------------------+----------------+------------------------------------------------------------+
| ID    | SKU           | Brand | Category                           | Suppliers | Attrs | Issues                                 | Source domains | Product                                                    |
+-------+---------------+-------+------------------------------------+-----------+-------+----------------------------------------+----------------+------------------------------------------------------------+
| 16991 | KOTLOV-004713 | Blist | Печи-камины                        | ligmet    | 0     | no_photo,no_short,low_attrs            | ligmet.by      | Blist Печь Berna Lux бежевая                               |
| 16992 | KOTLOV-004714 | Blist | Печи-камины                        | ligmet    | 0     | no_photo,no_short,low_attrs            | ligmet.by      | Blist Печь Berna Lux красная                               |
| 16995 | KOTLOV-004717 | Blist | Печи-камины                        | ligmet    | 0     | no_photo,no_short,low_attrs            | ligmet.by      | Blist Печь Modena бежевая                                  |
| 16998 | KOTLOV-004720 | Blist | Печи-камины                        | ligmet    | 0     | no_photo,no_short,low_attrs            | ligmet.by      | Blist Печь Napoli                                          |
| 16999 | KOTLOV-004721 | Blist | Печи-камины                        | ligmet    | 0     | no_photo,no_short,low_attrs            | ligmet.by      | Blist Печь Padova E                                        |
| 17000 | KOTLOV-004722 | Blist | Печи-камины                        | ligmet    | 0     | no_photo,no_short,low_attrs            | ligmet.by      | Blist Печь Roma E бежевая                                  |
| 21361 | KOTLOV-006527 | Blist | Дымоходы                           | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Труба 0,5м, Сербия                                   |
| 21362 | KOTLOV-006528 | Blist | Печи-камины                        | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Печь Roma G бежевая                                  |
| 21363 | KOTLOV-006529 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Бакелитовая ручка Blist (код 2943)                   |
| 21364 | KOTLOV-006530 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Вермикулит на заднюю стенку Blist Polar              |
| 21365 | KOTLOV-006531 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Зольный ящик Oganj (с круглым регулятором подачи ... |
| 21366 | KOTLOV-006532 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 160х295 Blist Ekonomik Lux      |
| 21367 | KOTLOV-006533 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 315x320 Blist (код 2804) (Zar)  |
| 21368 | KOTLOV-006534 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 320x338 Blist (код 3064)        |
| 21369 | KOTLOV-006535 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Колосниковая решетка 325х170 Atene (code 1273)       |
| 21370 | KOTLOV-006536 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Краска Roberlo (для Blist) аэрозоль                  |
| 21371 | KOTLOV-006537 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Наконечник никелированный Blist (к зольному ящику)   |
| 21372 | KOTLOV-006538 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 202x172 (код 2983/2965)    |
| 21373 | KOTLOV-006539 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 240x200 (код 2966)         |
| 21374 | KOTLOV-006540 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 270x240 (код 0669/0890)    |
| 21375 | KOTLOV-006541 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist 330x160 (код 2862)         |
| 21376 | KOTLOV-006542 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Стекло термостойкое Blist Polar 350x275              |
| 21377 | KOTLOV-006543 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Фиксатор стекла Blist                                |
| 21378 | KOTLOV-006544 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Чугунная крышка Modena (Zar) (код 003584)            |
| 21379 | KOTLOV-006545 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist B1 145х340мм (код 3681/1199)   |
| 21380 | KOTLOV-006546 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist B1N 155х340мм (код 3677/4108)  |
| 21381 | KOTLOV-006547 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist B2 135х370мм (код 2879)        |
| 21382 | KOTLOV-006548 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Og1 180х398мм (код 3973/4446)  |
| 21383 | KOTLOV-006549 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Og2 180х370мм (код 3974/4478)  |
| 21384 | KOTLOV-006550 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Og2 Z (для Zar (Modena), с ... |
| 21385 | KOTLOV-006551 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Е1 175х292мм (код 2703/2852)   |
| 21386 | KOTLOV-006552 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Е2/1 113х272мм (код 3680/4447) |
| 21387 | KOTLOV-006553 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич Blist Е2 115х302мм (код 4004/4493)   |
| 21388 | KOTLOV-006554 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шамотный кирпич (с отверстием) Blist Е3 170х145мм... |
| 21389 | KOTLOV-006555 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шнур белый 8х8мм Blist                               |
| 21390 | KOTLOV-006556 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шнур серый 8х8мм Blist                               |
| 21391 | KOTLOV-006557 | Blist | Дровницы и каминные принадлежности | ligmet    | 0     | no_photo,no_content,no_short,low_attrs | ligmet.by      | Blist Шнур черный 6х6мм Blist                              |
+-------+---------------+-------+------------------------------------+-----------+-------+----------------------------------------+----------------+------------------------------------------------------------+

```
