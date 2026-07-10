# Server Artisan Result

- Time: 2026-07-10 17:50:03 UTC
- Task: `artisan-dry-run`
- Artisan args: `catalog:audit-media --type=all --only-with-products --missing-only --limit=160`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7e65dbe..740304b  main       -> origin/main
Updating 7e65dbe..740304b
Fast-forward
 .github/server-artisan-result.md                  | 173 +------------------
 .github/server-artisan-task.json                  |   6 +-
 .github/workflows/server-artisan-queue.yml        |   2 +-
 app/Console/Commands/AuditCatalogMediaCommand.php | 193 ++++++++++++++++++++++
 app/Console/Commands/AuditCatalogMenuCommand.php  | 128 ++++++++++++++
 app/Http/Controllers/BrandController.php          |   8 +-
 app/Http/Controllers/CatalogController.php        |  19 ++-
 app/Models/Brand.php                              |  29 ++++
 app/Providers/AppServiceProvider.php              |  57 ++++++-
 resources/views/pages/brands.blade.php            |   4 +-
 resources/views/pages/catalog-index.blade.php     |   3 +-
 resources/views/pages/home-new.blade.php          |  10 +-
 12 files changed, 439 insertions(+), 193 deletions(-)
 create mode 100644 app/Console/Commands/AuditCatalogMediaCommand.php
 create mode 100644 app/Console/Commands/AuditCatalogMenuCommand.php
Categories
+----------+-------+
| metric   | count |
+----------+-------+
| checked  | 108   |
| missing  | 99    |
| broken   | 0     |
| fallback | 9     |
| ok       | 0     |
+----------+-------+
+-----+--------+-------------------------------------------------+-------------------------------------------+----------+---------+------+
| id  | parent | slug                                            | name                                      | products | media   | path |
+-----+--------+-------------------------------------------------+-------------------------------------------+----------+---------+------+
| 49  | 0      | kotly                                           | Котлы отопления                           | 183      | missing | -    |
| 113 | 0      | pechki                                          | Печи                                      | 14       | missing | -    |
| 286 | 0      | teplovyie-nasosyi                               | Тепловые насосы                           | 13       | missing | -    |
| 53  | 49     | gazovye                                         | Газовые                                   | 802      | missing | -    |
| 54  | 49     | tverdotoplivnye                                 | Твердотопливные                           | 494      | missing | -    |
| 55  | 49     | elektricheskie                                  | Электрические                             | 579      | missing | -    |
| 98  | 50     | electric                                        | Электрические                             | 968      | missing | -    |
| 99  | 50     | gas                                             | Газовые                                   | 17       | missing | -    |
| 100 | 50     | kosvennye                                       | Косвенные                                 | 176      | missing | -    |
| 101 | 50     | kombinirovannye                                 | Комбинированные                           | 24       | missing | -    |
| 298 | 50     | vodogreynaya-kolonka                            | Водогрейная колонка                       | 22       | missing | -    |
| 90  | 51     | topki                                           | Каминные топки                            | 117      | missing | -    |
| 104 | 51     | elektrokamini                                   | Электрические камины                      | 129      | missing | -    |
| 111 | 51     | oblicovki                                       | Порталы для электрокамина                 | 99       | missing | -    |
| 71  | 73     | bloki-upravleniya                               | Блок управления                           | 3        | missing | -    |
| 76  | 73     | kamni-dlya-bani                                 | Камни для бани                            | 12       | missing | -    |
| 92  | 73     | registry                                        | Теплообменники                            | 9        | missing | -    |
| 116 | 73     | izmeritelnye-pribory                            | Измерительные приборы                     | 2        | missing | -    |
| 118 | 73     | ventilyacionnye-klapana                         | Вентиляционные клапана и решетки          | 3        | missing | -    |
| 119 | 73     | kovriki-dlya-bani                               | Коврики для бани                          | 3        | missing | -    |
| 120 | 73     | kupeli-2                                        | Купели                                    | 2        | missing | -    |
| 121 | 73     | shajki-dlya-bani                                | Шайки для бани                            | 14       | missing | -    |
| 122 | 73     | oblivnye-ustrojstva                             | Обливные устройства                       | 5        | missing | -    |
| 124 | 73     | zaparniki                                       | Запарники                                 | 1        | missing | -    |
| 201 | 73     | otdelka-dlya-parnoj                             | Отделка для парной                        | 8        | missing | -    |
| 117 | 73     | abazhury-dlya-bani                              | Абажуры для бани                          | 8        | missing | -    |
| 291 | 73     | drovnitsyi                                      | Дровницы и каминные принадлежности        | 69       | missing | -    |
| 294 | 73     | aksessuaryi-dlya-bani                           | Аксессуары для бани                       | 9        | missing | -    |
| 279 | 73     | kaminnye-nabory                                 | Каминные наборы                           | 35       | missing | -    |
| 233 | 87     | alyuminievye-radiatory                          | Алюминиевые радиаторы                     | 34       | missing | -    |
| 235 | 87     | stalnye-radiatory                               | Стальные радиаторы                        | 710      | missing | -    |
| 236 | 87     | bimetallicheskie-radiatory                      | Биметаллические радиаторы                 | 109      | missing | -    |
| 324 | 87     | vodyanye-konvektory                             | Водяные конвекторы                        | 30       | missing | -    |
| 61  | 113    | pechi-kaminy                                    | Печи-камины                               | 134      | missing | -    |
| 63  | 113    | peci-drovianye-otopitelnye                      | Печи дровяные (отопительные)              | 55       | missing | -    |
| 219 | 113    | burzhuiki-pechi                                 | Буржуйки                                  | 55       | missing | -    |
| 220 | 113    | dlya-dachi                                      | Для дачи                                  | 2        | missing | -    |
| 287 | 113    | pechnoe-i-kaminnoe-lite                         | Печное и каминное литье                   | 151      | missing | -    |
| 129 | 128    | kaminnye-reshyotki                              | Каминные решётки                          | 92       | missing | -    |
| 340 | 193    | truby-iz-sshitogo-polietilena                   | Трубы из сшитого полиэтилена              | 16       | missing | -    |
| 349 | 193    | rezbovye-fitingi                                | Резьбовые фитинги                         | 15       | missing | -    |
| 325 | 193    | vodyanoy-teplyy-pol                             | Водяной теплый пол                        | 23       | missing | -    |
| 369 | 193    | press-fitingi                                   | Пресс-фитинги                             | 465      | missing | -    |
| 368 | 193    | kompressionnye-fitingi                          | Компрессионные фитинги                    | 15       | missing | -    |
| 370 | 193    | krepleniya-dlya-trub                            | Крепления для труб                        | 17       | missing | -    |
| 85  | 195    | komplekty-podklyucheniya                        | Комплекты подключения                     | 68       | missing | -    |
| 94  | 195    | solnechnye-kollektory                           | Солнечные коллекторы                      | 13       | missing | -    |
| 200 | 195    | istochniki-besperebojnogo-pitaniya              | Источники бесперебойного питания          | 8        | missing | -    |
| 267 | 195    | regulyatoryi-davleniya-gaza                     | Регуляторы давления газа                  | 1        | missing | -    |
| 299 | 195    | elektricheskie-teny-dlya-otopleniya             | Электрические ТЭНы                        | 20       | missing | -    |
| 283 | 195    | montajnyie-komplektyi                           | Монтажные комплекты систем отопления      | 6        | missing | -    |
| 196 | 195    | gruppy-bystrogo-montazha-kotelnyx               | Группы быстрого монтажа котельных         | 87       | missing | -    |
| 89  | 195    | membrannye-baki                                 | Мембранные баки                           | 27       | missing | -    |
| 91  | 195    | bufernye-emkosti                                | Буферные емкости                          | 81       | missing | -    |
| 93  | 195    | grebenki                                        | Гребенки                                  | 79       | missing | -    |
| 242 | 195    | gidravlicheskie-razdeliteli-i-kollektory        | Гидравлические разделители и коллекторы   | 20       | missing | -    |
| 58  | 195    | regulyatory                                     | Автоматика и терморегуляторы              | 166      | missing | -    |
| 371 | 195    | radiatornaya-armatura                           | Радиаторная арматура                      | 140      | missing | -    |
| 59  | 195    | stabilizatory-napryazheniya                     | Стабилизаторы напряжения                  | 40       | missing | -    |
| 86  | 195    | datchiki                                        | Датчики температуры                       | 11       | missing | -    |
| 106 | 195    | signalizatory-zagazovannosti                    | Сигнализаторы загазованности              | 18       | missing | -    |
| 96  | 195    | schetchiki-gaza                                 | Счетчики газа                             | 47       | missing | -    |
| 296 | 195    | teplonositeli                                   | Теплоносители (Антифриз)                  | 13       | missing | -    |
| 373 | 195    | predokhranitelnaya-i-reguliruyushchaya-armatura | Предохранительная и регулирующая арматура | 63       | missing | -    |
| 374 | 195    | smesitelnaya-armatura                           | Смесительная арматура                     | 9        | missing | -    |
| 372 | 195    | instrumenty-dlya-montazha                       | Инструменты для монтажа                   | 3        | missing | -    |
| 284 | 283    | komplektyi-dlya-tverdotoplivnyih-kotlov         | Для частных домов                         | 2        | missing | -    |
| 326 | 301    | krany-i-zapornaya-armatura                      | Краны и запорная арматура                 | 37       | missing | -    |
| 327 | 301    | smesitelnye-klapany-i-uzly                      | Смесительные клапаны и узлы               | 29       | missing | -    |
| 328 | 301    | gruppy-bezopasnosti                             | Группы безопасности                       | 18       | missing | -    |
| 329 | 301    | germetiki-i-montazhnye-materialy                | Герметики и монтажные материалы           | 5        | missing | -    |
| 248 | 303    | tsirkulyatsionnyie                              | Циркуляционные насосы                     | 419      | missing | -    |
| 264 | 303    | skvajinnye-nasosy                               | Скважинные насосы                         | 160      | missing | -    |
| 265 | 303    | drenajnyie                                      | Дренажные насосы                          | 68       | missing | -    |
| 249 | 303    | poverhnostnyie                                  | Поверхностные насосы                      | 32       | missing | -    |
| 251 | 303    | nasosnyie-stantsii                              | Насосные станции (гидрофор)               | 65       | missing | -    |
| 202 | 304    | obogrevateli                                    | Обогреватели                              | 80       | missing | -    |
| 52  | 305    | pechi-dlya-bani                                 | Для бани                                  | 2        | missing | -    |
| 69  | 305    | drovianye-peci-bannye                           | Дровяные печи (банные)                    | 298      | missing | -    |
| 70  | 305    | elektrokamenki                                  | Электрокаменки                            | 3        | missing | -    |
| 73  | 305    | aksessuary-dlya-bani                            | Для печей и каминов                       | 162      | missing | -    |
| 74  | 305    | kupeli                                          | Баки для воды                             | 53       | missing | -    |
| 72  | 305    | dveri-dlya-ban-i-saun                           | Двери для бани и сауны                    | 37       | missing | -    |
| 295 | 305    | mangalyi                                        | Мангалы                                   | 34       | missing | -    |
| 78  | 307    | dymohody-nerzhaveyushchie                       | Дымоходы                                  | 2        | missing | -    |
| 57  | 307    | koaxial-dymoxod                                 | Дымоходы коаксиальные                     | 75       | missing | -    |
| 316 | 307    | shibery-dymohod                                 | Шиберы и задвижки                         | 37       | missing | -    |
| 317 | 307    | kondensatootvody                                | Конденсатоотводы и ревизии                | 43       | missing | -    |
| 318 | 307    | krepleniya-dymohod                              | Крепления и монтаж                        | 119      | missing | -    |
| 319 | 307    | zonty-deflektory                                | Зонты и дефлекторы                        | 54       | missing | -    |
| 320 | 307    | teplosyomniki                                   | Теплосъёмники                             | 21       | missing | -    |
| 321 | 307    | perehody-adaptery-dymohod                       | Переходы и адаптеры                       | 130      | missing | -    |
| 322 | 307    | zaglushki-dymohod                               | Заглушки и оголовки                       | 4        | missing | -    |
| 309 | 308    | truby-mono                                      | Трубы одностенные                         | 118      | missing | -    |
| 310 | 308    | troyniki-mono                                   | Тройники моно                             | 91       | missing | -    |
| 311 | 308    | kolena-mono                                     | Колена и отводы моно                      | 140      | missing | -    |
| 313 | 312    | truby-sendvich                                  | Трубы сэндвич                             | 88       | missing | -    |
| 314 | 312    | troyniki-sendvich                               | Тройники сэндвич                          | 21       | missing | -    |
| 315 | 312    | kolena-sendvich                                 | Колена и отводы сэндвич                   | 37       | missing | -    |
+-----+--------+-------------------------------------------------+-------------------------------------------+----------+---------+------+
Brands
+----------+-------+
| metric   | count |
+----------+-------+
| checked  | 200   |
| missing  | 98    |
| broken   | 102   |
| fallback | 0     |
| ok       | 0     |
+----------+-------+
+-----+---------------------------------------+---------------------------------------+----------+---------+--------------------------------------+
| id  | slug                                  | name                                  | products | media   | path                                 |
+-----+---------------------------------------+---------------------------------------+----------+---------+--------------------------------------+
| 395 | ac-electric                           | AC Electric                           | 8        | missing | -                                    |
| 253 | antifrogen                            | Antifrogen                            | 2        | missing | -                                    |
| 361 | aqualink-                             | AQUALINK                              | 1        | missing | -                                    |
| 326 | aquastic                              | Aquastic                              | 7        | missing | -                                    |
| 426 | aquaverso                             | AquaVerso                             | 2        | missing | -                                    |
| 337 | arderia                               | ARDERIA                               | 19       | missing | -                                    |
| 22  | ariston                               | Ariston                               | 248      | broken  | 792-ariston-logo.jpg                 |
| 404 | aston                                 | ASTON                                 | 27       | missing | -                                    |
| 287 | auraton                               | Auraton                               | 3        | missing | -                                    |
| 309 | av-engineering                        | AV Engineering                        | 6        | broken  | e3f3ff7d6e3715ea54b69b604636b363.jpg |
| 297 | ballu                                 | Ballu                                 | 126      | missing | -                                    |
| 115 | baxi                                  | BAXI                                  | 74       | broken  | 288-baxi_logo.jpg                    |
| 215 | biawar                                | BIAWAR                                | 11       | broken  | 738-biawar.jpg                       |
| 311 | blist                                 | Blist                                 | 34       | missing | -                                    |
| 397 | boneco                                | Boneco                                | 7        | missing | -                                    |
| 36  | bosch                                 | Bosch                                 | 99       | broken  | 792-bosch-logo.jpg                   |
| 219 | brv-modvlvs                           | BRV-MODVLVS                           | 18       | broken  | 683-brv_logo.jpg                     |
| 188 | buderus                               | Buderus                               | 69       | broken  | 433-buderus_logo.jpg                 |
| 421 | candy                                 | Candy                                 | 8        | missing | -                                    |
| 418 | dab                                   | DAB                                   | 216      | missing | -                                    |
| 267 | darco                                 | Darco                                 | 179      | missing | -                                    |
| 89  | de-dietrich                           | De Dietrich                           | 5        | broken  | 625-de-dietrich-logo.jpg             |
| 203 | dimplex                               | Dimplex                               | 30       | broken  | 218-dimplex-logo-rot.jpg             |
| 185 | doorwood                              | DoorWood                              | 37       | broken  | 837-doorwood-logo.jpg                |
| 378 | e.c.a.                                | E.C.A.                                | 9        | broken  | eca.png                              |
| 425 | edisson                               | Edisson                               | 12       | missing | -                                    |
| 328 | elboom-2                              | Elboom                                | 8        | missing | -                                    |
| 58  | electrolux                            | Electrolux                            | 481      | broken  | 145-electrolux.jpg                   |
| 399 | energolux                             | Energolux                             | 3        | missing | -                                    |
| 210 | esbe                                  | ESBE                                  | 12       | broken  | 139-index2.jpg                       |
| 387 | ech                                   | ESH                                   | 5        | missing | -                                    |
| 427 | eurostar                              | Eurostar                              | 7        | missing | -                                    |
| 206 | euroster                              | Euroster                              | 11       | broken  | 686-euroster-logo.gif                |
| 198 | ewt                                   | EWT                                   | 4        | broken  | 533-ewt_logo.jpg                     |
| 371 | federica-bugatti-2                    | Federica Bugatti                      | 13       | broken  | screenshot_15.png                    |
| 336 | ferguss                               | Ferguss                               | 1        | missing | -                                    |
| 94  | ferroli                               | Ferroli                               | 211      | broken  | 642-ferroli-logo.jpg                 |
| 259 | ferrum                                | Ferrum                                | 191      | broken  | 198-ferrum2.jpg                      |
| 220 | fireblaze                             | FireBlaze                             | 2        | broken  | 583-logo-fireblaze.png               |
| 390 | firelight                             | Firelight                             | 5        | missing | -                                    |
| 299 | Fireway                               | FireWay                               | 28       | broken  | logo-test.svg                        |
| 256 | flamco                                | Flamco                                | 9        | broken  | 211-flamco.png                       |
| 102 | fondital                              | Fondital                              | 51       | broken  | 897-fondital-logo.jpg                |
| 60  | galmet                                | Galmet                                | 30       | broken  | 543-galmet-logo.jpg                  |
| 424 | garanterm                             | Garanterm                             | 19       | missing | -                                    |
| 408 | gardana                               | GARDANA                               | 43       | missing | -                                    |
| 354 | gas-spezialisten                      | Gas Spezialisten                      | 1        | missing | -                                    |
| 199 | giacomini                             | Giacomini                             | 59       | broken  | 335-giacomini_logo.jpg               |
| 416 | gkb                                   | GKB                                   | 7        | missing | -                                    |
| 367 | greolit                               | Greolit                               | 28       | missing | -                                    |
| 275 | grill'd                               | Grill'D                               | 1        | broken  | 02.png                               |
| 30  | grundfos                              | Grundfos                              | 22       | broken  | 778-grundfos-logo.jpg                |
| 360 | haier                                 | HAIER                                 | 197      | missing | -                                    |
| 21  | harvia                                | Harvia                                | 3        | broken  | 176-harvia-logo.jpg                  |
| 225 | herz                                  | HERZ                                  | 21       | broken  | 767-herz.jpg                         |
| 396 | hommyn                                | Hommyn                                | 22       | missing | -                                    |
| 315 | hotta                                 | Hotta                                 | 9        | missing | -                                    |
| 224 | hyundai                               | Hyundai                               | 33       | broken  | 983-logo_hyundai_biale.jpg           |
| 409 | imp-pumps                             | IMP PUMPS                             | 1        | missing | -                                    |
| 151 | invicta                               | Invicta                               | 5        | broken  | 284-invicta-logo-small.jpg           |
| 410 | jemix                                 | JEMIX                                 | 1        | missing | -                                    |
| 229 | jotul                                 | JOTUL                                 | 1        | broken  | 191-jotul.jpg                        |
| 172 | junkers                               | Junkers                               | 4        | broken  | 753-junkers.jpg                      |
| 242 | kan                                   | KAN                                   | 23       | broken  | 183-system-kan-therm-biale-tlo.png   |
| 338 | karina                                | KARINA                                | 2        | missing | -                                    |
| 344 | kennet                                | Kennet                                | 1        | missing | -                                    |
| 303 | kenantsu                              | Kentatsu                              | 4        | broken  | 581836706a6c71581e94a734.png         |
| 76  | kermi                                 | Kermi                                 | 456      | broken  | 179-kermi_logo.jpg                   |
| 419 | kiturami                              | Kiturami                              | 2        | missing | -                                    |
| 75  | kospel                                | Kospel                                | 247      | broken  | 838-kospel.jpg                       |
| 385 | kotlov                                | KOTLOV                                | 38       | missing | -                                    |
| 139 | kratki                                | Kratki                                | 115      | broken  | 238-kratki.jpg                       |
| 170 | lamborghini                           | Lamborghini                           | 23       | broken  | 645-lamborghini.jpg                  |
| 365 | lava                                  | Lava                                  | 6        | missing | -                                    |
| 359 | lavoro-                               | Lavoro                                | 13       | missing | -                                    |
| 349 | ltek                                  | LTEK                                  | 9        | missing | -                                    |
| 324 | maxima                                | Maxima                                | 7        | missing | -                                    |
| 356 | mbs                                   | MBS                                   | 6        | missing | -                                    |
| 212 | meibes                                | Meibes                                | 27       | broken  | 722-meibes.jpg                       |
| 412 | meran                                 | MERAN                                 | 7        | missing | -                                    |
| 201 | merlin                                | Merlin                                | 4        | missing | -                                    |
| 329 | mr.-tektum-                           | Mr. Tektum                            | 2        | missing | -                                    |
| 276 | navien                                | Navien                                | 16       | broken  | navien.png                           |
| 307 | nordflam                              | Nordflam                              | 12       | missing | -                                    |
| 113 | nova-florida                          | Nova Florida                          | 3        | broken  | 217-logonovaflorida.jpg              |
| 369 | nova-florida-                         | Nova Florida                          | 6        | missing | -                                    |
| 363 | ole-pro                               | Ole-pro                               | 1        | missing | -                                    |
| 28  | opop                                  | OPOP                                  | 6        | broken  | 147-opop.png                         |
| 415 | panadero                              | Panadero                              | 7        | missing | -                                    |
| 300 | parkanex                              | Parkanex                              | 7        | broken  | parkanex.png                         |
| 321 | pegas                                 | Pegas                                 | 81       | broken  | 2_Pegas_LOGO_krasnyy_1x.png          |
| 372 | poer                                  | Poer                                  | 1        | missing | -                                    |
| 245 | prado                                 | PRADO                                 | 24       | broken  | 235-prado1.jpg                       |
| 136 | profline                              | Profline                              | 25       | broken  | 609-profline.jpg                     |
| 317 | prometall                             | Prometall                             | 1        | broken  | 13-4.png                             |
| 23  | protherm                              | Protherm                              | 43       | broken  | 385-protherm.png                     |
| 411 | purity                                | PURITY                                | 1        | missing | -                                    |
| 204 | real-flame                            | Real Flame                            | 7        | broken  | 869-realflame.jpg                    |
| 189 | regulus                               | Regulus                               | 2        | broken  | 321-logo_regulus.jpg                 |
| 207 | rehau                                 | REHAU                                 | 9        | broken  | 435-rehau.jpg                        |
| 318 | rifar                                 | Rifar                                 | 21       | missing | -                                    |
| 182 | rihters                               | Rihters                               | 6        | broken  | 961-rihters-logo-small.jpg           |
| 213 | rinnai                                | Rinnai                                | 9        | broken  | 446-rinnai.jpg                       |
| 202 | royal-flame                           | Royal Flame                           | 57       | broken  | 486-royalflame.jpg                   |
| 144 | royal-thermo                          | Royal Thermo                          | 426      | broken  | 751-royalthermo_logo.jpg             |
| 155 | s-tank                                | S-TANK                                | 46       | broken  | 428-s-tank.jpg                       |
| 334 | sakovich                              | Sakovich                              | 67       | missing | -                                    |
| 104 | salus                                 | Salus                                 | 30       | broken  | 759-salus_logotip4.jpg               |
| 301 | scamol                                | Scamol                                | 6        | missing | -                                    |
| 391 | shuft                                 | SHUFT                                 | 17       | missing | -                                    |
| 43  | sime                                  | Sime                                  | 3        | broken  | 583-sime-logo.png                    |
| 92  | solpi                                 | Solpi                                 | 30       | broken  | 453-solpi-m.jpg                      |
| 420 | superlux                              | Superlux                              | 9        | missing | -                                    |
| 241 | sven                                  | SVEN                                  | 1        | broken  | 626-sven_logo2.jpg                   |
| 423 | tec-line                              | TEC Line                              | 15       | missing | -                                    |
| 228 | tech                                  | TECH                                  | 10       | missing | -                                    |
| 375 | teknix                                | TEKNIX                                | 19       | broken  | logo-header.svg                      |
| 279 | tenko                                 | Tenko                                 | 52       | missing | -                                    |
| 34  | teplocom                              | Teplocom                              | 1        | broken  | 817-teplocom-logo.png                |
| 134 | termica                               | Termica                               | 8        | broken  | 251-logo_termica.jpg                 |
| 288 | tesy-2                                | Tesy                                  | 13       | broken  | 1183.png                             |
| 186 | thermex                               | Thermex                               | 300      | broken  | 867-logo-thermex.jpg                 |
| 163 | timberk                               | Timberk                               | 137      | broken  | 290-stroy-rtl-timberk-logo.jpg       |
| 211 | tis-2                                 | TIS                                   | 84       | broken  | 455-265-belkomin-logo.jpg            |
| 386 | tmf                                   | TMF                                   | 66       | broken  | logo_385x165.svg                     |
| 394 | toshiba                               | Toshiba                               | 7        | missing | -                                    |
| 380 | tyfocor                               | TYFOCOR                               | 2        | missing | -                                    |
| 97  | unical                                | Unical                                | 20       | broken  | 188-unical_logo.jpg                  |
| 216 | unipump                               | UNIPUMP                               | 154      | broken  | 731-unipump.gif                      |
| 105 | vvd                                   | V.V.D.                                | 2        | broken  | 165-vvd.jpg                          |
| 173 | vaillant                              | Vaillant                              | 131      | broken  | 614-vaillant-logo.jpg                |
| 392 | varmega                               | Varmega                               | 951      | missing | -                                    |
| 355 | venma-                                | VENMA                                 | 11       | missing | -                                    |
| 37  | viadrus                               | Viadrus                               | 7        | broken  | 288-viadrus.png                      |
| 227 | victory                               | VICTORY                               | 7        | broken  | 152-victory.jpg                      |
| 171 | viessmann                             | Viessmann                             | 24       | broken  | 795-viessmann-logo.jpg               |
| 234 | watts                                 | Watts                                 | 13       | broken  | 127-watts.jpg                        |
| 223 | wavin                                 | WAVIN                                 | 13       | broken  | 994-wavin.jpg                        |
| 407 | wellmix                               | WELLMIX                               | 18       | missing | -                                    |
| 40  | wester                                | Wester                                | 17       | broken  | 459-wester.png                       |
| 31  | wilo                                  | WILO                                  | 121      | broken  | 442-wilo_logo_grn_pan.gif            |
| 398 | xommet                                | XOMMET                                | 10       | missing | -                                    |
| 417 | zehnder                               | Zehnder                               | 103      | missing | -                                    |
| 142 | analitpribor                          | Аналитприбор                          | 1        | broken  | 355-981-analitpribor.jpg             |
| 42  | atem                                  | АТЕМ                                  | 21       | broken  | 182-logo-atem-min.png                |
| 413 | bania                                 | Банька                                | 74       | missing | -                                    |
| 112 | belomo                                | БелОМО                                | 40       | broken  | 441-logo_belomo.jpg                  |
| 347 | borinskoe-                            | Боринское                             | 1        | missing | -                                    |
| 370 | federica-bugatti                      | Бренд 370                             | 1        | broken  | wf1roygad2ennnw6e4f66c2b8zt7p546.png |
| 35  | breneran                              | Бренеран                              | 1        | broken  | 147-breneran_logo_small.jpg          |
| 296 | varvara                               | Варвара                               | 6        | missing | -                                    |
| 178 | vezuvij                               | Везувий                               | 251      | broken  | 295-vezuviy-logo.jpg                 |
| 289 | veles-elektro                         | Велес Электро                         | 38       | missing | -                                    |
| 236 | grodnotorgmash                        | Гродторгмаш                           | 4        | missing | -                                    |
| 403 | dzhileks                              | Джилекс                               | 2        | missing | -                                    |
| 298 | dzenzelevskiy-kotlostroitelnyiy-zavod | Дзензелевский котлостроительный завод | 1        | missing | -                                    |
| 258 | ermak                                 | Ермак                                 | 52       | broken  | 197-ermak_logo.jpg                   |
| 366 | jitomir                               | Житомир                               | 42       | missing | -                                    |
| 264 | konord                                | Конорд                                | 2        | missing | -                                    |
| 340 | kosmos                                | Космос                                | 1        | missing | -                                    |
+-----+---------------------------------------+---------------------------------------+----------+---------+--------------------------------------+

```
