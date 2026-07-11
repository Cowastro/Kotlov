# Server Artisan Result

- Time: 2026-07-11 07:38:54 UTC
- Task: `artisan-dry-run`
- Artisan args: `catalog:audit-media --type=brands --only-with-products --missing-only --limit=120`
- Log file: `storage/logs/server-artisan-media-audit.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   e29270f..4460dd5  main       -> origin/main
Updating e29270f..4460dd5
Fast-forward
 .github/server-artisan-result.md                  | 163 +++++++++++++++++++---
 .github/server-artisan-task.json                  |   2 +-
 app/Console/Commands/AuditCatalogMediaCommand.php |   4 +
 3 files changed, 147 insertions(+), 22 deletions(-)
Brands
+----------+-------+
| metric   | count |
+----------+-------+
| checked  | 200   |
| missing  | 39    |
| broken   | 32    |
| fallback | 0     |
| ok       | 129   |
+----------+-------+
+-----+-----------------------------------------------------+--------------------------------------------+----------+---------+--------------------------------------+
| id  | slug                                                | name                                       | products | media   | path                                 |
+-----+-----------------------------------------------------+--------------------------------------------+----------+---------+--------------------------------------+
| 253 | antifrogen                                          | Antifrogen                                 | 2        | missing | -                                    |
| 219 | brv-modvlvs                                         | BRV-MODVLVS                                | 18       | broken  | 683-brv_logo.jpg                     |
| 210 | esbe                                                | ESBE                                       | 12       | broken  | 139-index2.jpg                       |
| 387 | ech                                                 | ESH                                        | 5        | missing | -                                    |
| 427 | eurostar                                            | Eurostar                                   | 7        | missing | -                                    |
| 198 | ewt                                                 | EWT                                        | 4        | broken  | 533-ewt_logo.jpg                     |
| 220 | fireblaze                                           | FireBlaze                                  | 2        | broken  | 583-logo-fireblaze.png               |
| 354 | gas-spezialisten                                    | Gas Spezialisten                           | 1        | missing | -                                    |
| 416 | gkb                                                 | GKB                                        | 7        | missing | -                                    |
| 225 | herz                                                | HERZ                                       | 21       | broken  | 767-herz.jpg                         |
| 315 | hotta                                               | Hotta                                      | 9        | missing | -                                    |
| 229 | jotul                                               | JOTUL                                      | 1        | broken  | 191-jotul.jpg                        |
| 172 | junkers                                             | Junkers                                    | 4        | broken  | 753-junkers.jpg                      |
| 242 | kan                                                 | KAN                                        | 23       | broken  | 183-system-kan-therm-biale-tlo.png   |
| 385 | kotlov                                              | KOTLOV                                     | 38       | missing | -                                    |
| 349 | ltek                                                | LTEK                                       | 9        | missing | -                                    |
| 324 | maxima                                              | Maxima                                     | 7        | missing | -                                    |
| 412 | meran                                               | MERAN                                      | 7        | missing | -                                    |
| 201 | merlin                                              | Merlin                                     | 4        | missing | -                                    |
| 329 | mr.-tektum-                                         | Mr. Tektum                                 | 2        | missing | -                                    |
| 113 | nova-florida                                        | Nova Florida                               | 3        | broken  | 217-logonovaflorida.jpg              |
| 369 | nova-florida-                                       | Nova Florida                               | 6        | missing | -                                    |
| 363 | ole-pro                                             | Ole-pro                                    | 1        | missing | -                                    |
| 28  | opop                                                | OPOP                                       | 6        | broken  | 147-opop.png                         |
| 300 | parkanex                                            | Parkanex                                   | 7        | broken  | parkanex.png                         |
| 411 | purity                                              | PURITY                                     | 1        | missing | -                                    |
| 207 | rehau                                               | REHAU                                      | 9        | broken  | 435-rehau.jpg                        |
| 182 | rihters                                             | Rihters                                    | 6        | broken  | 961-rihters-logo-small.jpg           |
| 301 | scamol                                              | Scamol                                     | 6        | missing | -                                    |
| 92  | solpi                                               | Solpi                                      | 30       | broken  | 453-solpi-m.jpg                      |
| 420 | superlux                                            | Superlux                                   | 9        | missing | -                                    |
| 34  | teplocom                                            | Teplocom                                   | 1        | broken  | 817-teplocom-logo.png                |
| 386 | tmf                                                 | TMF                                        | 66       | broken  | logo_385x165.svg                     |
| 380 | tyfocor                                             | TYFOCOR                                    | 2        | missing | -                                    |
| 97  | unical                                              | Unical                                     | 20       | broken  | 188-unical_logo.jpg                  |
| 105 | vvd                                                 | V.V.D.                                     | 2        | broken  | 165-vvd.jpg                          |
| 355 | venma-                                              | VENMA                                      | 11       | missing | -                                    |
| 37  | viadrus                                             | Viadrus                                    | 7        | broken  | 288-viadrus.png                      |
| 227 | victory                                             | VICTORY                                    | 7        | broken  | 152-victory.jpg                      |
| 234 | watts                                               | Watts                                      | 13       | broken  | 127-watts.jpg                        |
| 407 | wellmix                                             | WELLMIX                                    | 18       | missing | -                                    |
| 398 | xommet                                              | XOMMET                                     | 10       | missing | -                                    |
| 142 | analitpribor                                        | Аналитприбор                               | 1        | broken  | 355-981-analitpribor.jpg             |
| 42  | atem                                                | АТЕМ                                       | 21       | broken  | 182-logo-atem-min.png                |
| 413 | bania                                               | Банька                                     | 74       | missing | -                                    |
| 112 | belomo                                              | БелОМО                                     | 40       | broken  | 441-logo_belomo.jpg                  |
| 370 | federica-bugatti                                    | Бренд 370                                  | 1        | broken  | wf1roygad2ennnw6e4f66c2b8zt7p546.png |
| 289 | veles-elektro                                       | Велес Электро                              | 38       | missing | -                                    |
| 236 | grodnotorgmash                                      | Гродторгмаш                                | 4        | missing | -                                    |
| 298 | dzenzelevskiy-kotlostroitelnyiy-zavod               | Дзензелевский котлостроительный завод      | 1        | missing | -                                    |
| 366 | jitomir                                             | Житомир                                    | 42       | missing | -                                    |
| 264 | konord                                              | Конорд                                     | 2        | missing | -                                    |
| 340 | kosmos                                              | Космос                                     | 1        | missing | -                                    |
| 351 | merkuriy                                            | Меркурий                                   | 17       | missing | -                                    |
| 314 | neus                                                | Неус                                       | 3        | missing | -                                    |
| 393 | nzs                                                 | НЗС                                        | 11       | missing | -                                    |
| 414 | nmk                                                 | НМК                                        | 13       | missing | -                                    |
| 305 | novosibirskaya-metalloobrabatyivayuschaya-kompaniya | Новосибирская металлообрабатывающая ком... | 13       | broken  | 97050b0cd760b29e1b2769b51626cdf8.png |
| 335 | ooo-                                                | ООО БлицПром                               | 6        | missing | -                                    |
| 290 | pechkin                                             | Печкин                                     | 1        | broken  | logo_pechkin.png                     |
| 373 | rubtsovskiy-liteynyiy-kompleks-ldv                  | Рубцовский литейный комплекс ЛДВ           | 1        | missing | -                                    |
| 239 | svod                                                | СВОД                                       | 3        | missing | -                                    |
| 138 | smolkom                                             | Смолком                                    | 88       | broken  | 591-smolkom.jpeg                     |
| 353 | stalsnabdizayn-                                     | СтальСнабДизайн                            | 43       | missing | -                                    |
| 61  | termopass                                           | ТЕРМОПАСС                                  | 9        | broken  | 530-termopass.jpg                    |
| 382 | everest                                             | Эверест                                    | 21       | missing | -                                    |
| 381 | ekohitin                                            | Экохитин                                   | 4        | missing | -                                    |
| 140 | yelektroteplopribor                                 | Электротеплоприбор                         | 3        | broken  | 380-elektroteplopribor.png           |
| 261 | yenergiya                                           | Энергия                                    | 8        | broken  | 124-logotip_energiya2.png            |
| 149 | yergotex                                            | Эрготех                                    | 1        | broken  | 885-ergotech-logo.jpg                |
| 240 | yunker                                              | ЮНКЕР                                      | 2        | missing | -                                    |
+-----+-----------------------------------------------------+--------------------------------------------+----------+---------+--------------------------------------+

```
