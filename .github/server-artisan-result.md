# Server Artisan Result

- Time: 2026-07-09 17:39:04 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --active-only --not-archived --limit=0`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
APPLY: sanitized content was written.
+--------------------+-------+
| metric             | count |
+--------------------+-------+
| checked            | 8498  |
| changed            | 3714  |
| written            | 3714  |
| images_removed     | 231   |
| styles_removed     | 24816 |
| bad_blocks_removed | 746   |
+--------------------+-------+
+-----+------------+----------+-----------------------------------------------------------+
| ID  | SKU        | Brand    | Product                                                   |
+-----+------------+----------+-----------------------------------------------------------+
| 123 | PS-000.123 | -        | Камни Габбро-диабаз колотый (20 кг)                       |
| 124 | PS-000.124 | -        | Камни Малиновый кварцит колотый (20 кг)                   |
| 125 | PS-000.125 | -        | Камни Талькохлорит обвалованный (20 кг)                   |
| 126 | PS-000.126 | -        | Камни Жадеит колотый (20 кг)                              |
| 178 | PS-000.178 | Harvia   | Блок управления Harvia C150                               |
| 179 | PS-000.179 | Harvia   | Блок управления Harvia C105S Logix                        |
| 180 | PS-000.180 | Harvia   | Блок управления Harvia C260-20                            |
| 226 | PS-000.226 | Vaillant | Газовый котел Vaillant atmoTEC pro VUW 240/5-3            |
| 229 | PS-000.229 | Vaillant | Газовый котел Vaillant atmoTEC pro VUW 280/5-3            |
| 230 | PS-000.230 | Vaillant | Газовый котел Vaillant turboTEC pro VUW 282/5-3           |
| 234 | PS-000.234 | Vaillant | Газовый котел Vaillant atmoTEC plus VUW 240/3-5           |
| 236 | PS-000.236 | Vaillant | Газовый котел Vaillant atmoTEC plus VUW 280/3-5           |
| 260 | PS-000.260 | Vaillant | Газовый котел Vaillant turboTEC plus VUW 282/3-5          |
| 268 | PS-000.268 | Vaillant | Дымоход коаксиальный Vaillant 303807                      |
| 299 | PS-000.299 | Vaillant | Газовый котел Vaillant turboTEC plus VUW 322/3-5          |
| 301 | PS-000.301 | Vaillant | Газовый котел Vaillant turboTEC plus VUW 362/3-5          |
| 322 | PS-000.322 | Vaillant | Газовый котел Vaillant atmoTEC plus VU 240/5-5            |
| 323 | PS-000.323 | Protherm | Газовый котел Protherm Пантера 25 KOV                     |
| 324 | PS-000.324 | Protherm | Газовый котел Protherm Пантера 25 KTV                     |
| 325 | PS-000.325 | Protherm | Газовый котел Protherm Пантера 30 KTV                     |
| 326 | PS-000.326 | Protherm | Газовый котел Protherm Гепард 23 MOV                      |
| 327 | PS-000.327 | Vaillant | Газовый котел Vaillant atmoTEC plus VU 280/5-5            |
| 328 | PS-000.328 | Vaillant | Газовый котел Vaillant turboTEC pro VUW 242/5-3           |
| 329 | PS-000.329 | Protherm | Газовый котел Protherm Гепард 23 MTV                      |
| 332 | PS-000.332 | Protherm | Газовый котел Protherm Пантера 25 KOO                     |
| 335 | PS-000.335 | Protherm | Газовый котел Protherm Медведь 20 KLOM                    |
| 336 | PS-000.336 | Protherm | Газовый котел Protherm Медведь 20 KLZR                    |
| 339 | PS-000.339 | Protherm | Газовый котел Protherm Медведь 30 KLOM                    |
| 340 | PS-000.340 | Protherm | Газовый котел Protherm Медведь 30 KLZR                    |
| 343 | PS-000.343 | Protherm | Газовый котел Protherm Медведь 40 KLOM                    |
| 344 | PS-000.344 | Protherm | Газовый котел Protherm Медведь 40 KLZ                     |
| 346 | PS-000.346 | Protherm | Газовый котел Protherm Медведь 50 KLOM                    |
| 347 | PS-000.347 | Protherm | Газовый котел Protherm Медведь 50 KLZ                     |
| 356 | PS-000.356 | Protherm | Твердотопливный котел Protherm Бобер 20 DLO               |
| 360 | PS-000.360 | Protherm | Твердотопливный котел Protherm Бобер 40 DLO               |
| 363 | PS-000.363 | Protherm | Твердотопливный котел Protherm Бобер 30 DLO               |
| 375 | PS-000.375 | Ariston  | Водонагреватель электрический Ariston ABS PRO R 50 V      |
| 376 | PS-000.376 | Ariston  | Водонагреватель электрический Ariston ABS PRO R 80 V      |
| 379 | PS-000.379 | Ariston  | Водонагреватель электрический Ariston ABS PRO R 65 V Slim |
| 380 | PS-000.380 | Ariston  | Водонагреватель электрический Ariston ABS PRO R 80 V Slim |
| 381 | PS-000.381 | Ariston  | Водонагреватель электрический Ariston ABS PRO ECO 80 V    |
| 382 | PS-000.382 | Ariston  | Водонагреватель электрический Ariston ABS PRO ECO 100 V   |
| 438 | PS-000.438 | Vaillant | Газовый котел Vaillant atmoVIT exclusiv VK 264/8 E        |
| 555 | PS-000.555 | Protherm | Электрический котел Protherm Скат 6K (Ray)                |
| 556 | PS-000.556 | Protherm | Электрический котел Protherm Скат 9K (Ray)                |
| 557 | PS-000.557 | Protherm | Электрический котел Protherm Скат 12K (Ray)               |
| 558 | PS-000.558 | Protherm | Электрический котел Protherm Скат 14K (Ray)               |
| 559 | PS-000.559 | Protherm | Электрический котел Protherm Скат 18K (Ray)               |
| 560 | PS-000.560 | Protherm | Электрический котел Protherm Скат 21K (Ray)               |
| 561 | PS-000.561 | Protherm | Электрический котел Protherm Скат 24K (Ray)               |
| 562 | PS-000.562 | Protherm | Электрический котел Protherm Скат 28K (Ray)               |
| 621 | PS-000.621 | АТЕМ     | Газовый котел Atem Житомир-М АОГВ 10 СН                   |
| 622 | PS-000.622 | АТЕМ     | Газовый котел Atem Житомир-М АОГВ 7 СН                    |
| 623 | PS-000.623 | АТЕМ     | Газовый котел Atem Житомир-3 КС-Г-010 СН                  |
| 624 | PS-000.624 | АТЕМ     | Газовый котел Atem Житомир-3 КС-Г-012 СН                  |
| 625 | PS-000.625 | АТЕМ     | Газовый котел Atem Житомир-3 КС-ГВ-010 СН                 |
| 627 | PS-000.627 | АТЕМ     | Газовый котел Atem Житомир-3 КС-ГВ-012 СН                 |
| 628 | PS-000.628 | АТЕМ     | Газовый котел Atem Житомир-3 КС-Г-015 СН                  |
| 629 | PS-000.629 | АТЕМ     | Газовый котел Atem Житомир-М АДГВ 10 СН                   |
| 631 | PS-000.631 | АТЕМ     | Газовый котел Atem Житомир-3 КС-ГВ-015 СН                 |
| 632 | PS-000.632 | АТЕМ     | Газовый котел Atem Житомир-3 КС-Г-020 СН                  |
| 634 | PS-000.634 | АТЕМ     | Газовый котел Atem Житомир-3 КС-ГВ-020 СН                 |
| 635 | PS-000.635 | АТЕМ     | Газовый котел Atem Житомир-3 КС-Г-030 СН                  |
| 636 | PS-000.636 | АТЕМ     | Газовый котел Atem Житомир-3 КС-ГВ-030 СН                 |
| 637 | PS-000.637 | АТЕМ     | Газовый котел Atem Житомир-3 КС-Г-045 СН                  |
| 639 | PS-000.639 | Vaillant | Газовый котел Vaillant atmoVIT exclusiv VK 314/8 E        |
| 640 | PS-000.640 | Vaillant | Газовый котел Vaillant atmoVIT exclusiv VK 364/8 E        |
| 641 | PS-000.641 | Vaillant | Газовый котел Vaillant atmoVIT exclusiv VK 424/8 E        |
| 642 | PS-000.642 | Vaillant | Газовый котел Vaillant atmoVIT exclusiv VK 474/8 E        |
| 643 | PS-000.643 | Vaillant | Газовый котел Vaillant atmoVIT VK INT 254/1-5             |
| 644 | PS-000.644 | Vaillant | Газовый котел Vaillant atmoVIT VK INT 324/1-5             |
| 645 | PS-000.645 | Vaillant | Газовый котел Vaillant atmoVIT VK INT 414/1-5             |
| 646 | PS-000.646 | Vaillant | Газовый котел Vaillant atmoVIT VK INT 484/1-5             |
| 647 | PS-000.647 | Vaillant | Газовый котел Vaillant atmoVIT VK INT 564/1-5             |
| 648 | PS-000.648 | Vaillant | Газовый котел Vaillant atmoCRAFT VK INT 654/9             |
| 649 | PS-000.649 | Vaillant | Газовый котел Vaillant atmoCRAFT VK INT 754/9             |
| 650 | PS-000.650 | Vaillant | Газовый котел Vaillant atmoCRAFT VK INT 854/9             |
| 651 | PS-000.651 | Vaillant | Газовый котел Vaillant atmoCRAFT VK INT 1004/9            |
| 652 | PS-000.652 | Vaillant | Газовый котел Vaillant atmoCRAFT VK INT 1154/9            |
| 653 | PS-000.653 | Vaillant | Газовый котел Vaillant atmoCRAFT VK INT 1254/9            |
+-----+------------+----------+-----------------------------------------------------------+

```
