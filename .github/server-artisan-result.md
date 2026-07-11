# Server Artisan Result

- Time: 2026-07-11 20:50:28 UTC
- Task: `artisan-dry-run`
- Artisan args: `catalog:audit-media --type=categories --only-with-products --limit=200`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   3e5e090..7097f7c  main       -> origin/main
Updating 3e5e090..7097f7c
Fast-forward
 .github/server-artisan-result.md | 35 ++++++++++++++---------------------
 .github/server-artisan-task.json |  4 ++--
 2 files changed, 16 insertions(+), 23 deletions(-)
Categories
+----------+-------+
| metric   | count |
+----------+-------+
| checked  | 108   |
| missing  | 0     |
| broken   | 0     |
| fallback | 108   |
| ok       | 0     |
+----------+-------+
+-----+--------+-------------------------------------------------+-------------------------------------------+----------+----------+--------------------------------------------------+
| id  | parent | slug                                            | name                                      | products | media    | path                                             |
+-----+--------+-------------------------------------------------+-------------------------------------------+----------+----------+--------------------------------------------------+
| 49  | 0      | kotly                                           | Котлы отопления                           | 183      | fallback | img/popular/boiler_img.jpg                       |
| 50  | 0      | vodonagrevateli                                 | Водонагреватели                           | 88       | fallback | img/banners/baner_boiler.jpg                     |
| 113 | 0      | pechki                                          | Печи                                      | 14       | fallback | img/popular/pech.jpg                             |
| 307 | 0      | dymohody                                        | Дымоходы                                  | 4        | fallback | img/popular/chimney.jpg                          |
| 193 | 0      | truby-i-fitingi                                 | Трубы и фитинги                           | 1        | fallback | img/popular/truby-i-fitingi.jpg                  |
| 109 | 0      | teplyj-pol                                      | Теплый пол                                | 30       | fallback | img/popular/teplyj-pol.jpg                       |
| 304 | 0      | klimat                                          | Климат                                    | 415      | fallback | img/popular/air.jpg                              |
| 195 | 0      | komplektuyushhie-dlya-otopleniya                | Комплектующие                             | 10       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 203 | 0      | filtry                                          | Фильтры                                   | 21       | fallback | img/popular/filtry.jpg                           |
| 281 | 0      | elektricheskie-konvektoryi                      | Конвекторы                                | 70       | fallback | img/popular/elektricheskie-konvektoryi.jpg       |
| 286 | 0      | teplovyie-nasosyi                               | Тепловые насосы                           | 13       | fallback | img/popular/heatpump.jpg                         |
| 297 | 0      | pelletnye-gorelki                               | Пеллетные горелки                         | 50       | fallback | img/popular/pellet_burner.jpg                    |
| 53  | 49     | gazovye                                         | Газовые                                   | 802      | fallback | img/popular/boiler_img.jpg                       |
| 54  | 49     | tverdotoplivnye                                 | Твердотопливные                           | 494      | fallback | img/popular/boiler_img.jpg                       |
| 55  | 49     | elektricheskie                                  | Электрические                             | 579      | fallback | img/popular/boiler_img.jpg                       |
| 98  | 50     | electric                                        | Электрические                             | 968      | fallback | img/banners/baner_boiler.jpg                     |
| 99  | 50     | gas                                             | Газовые                                   | 17       | fallback | img/banners/baner_boiler.jpg                     |
| 100 | 50     | kosvennye                                       | Косвенные                                 | 176      | fallback | img/banners/baner_boiler.jpg                     |
| 101 | 50     | kombinirovannye                                 | Комбинированные                           | 24       | fallback | img/banners/baner_boiler.jpg                     |
| 298 | 50     | vodogreynaya-kolonka                            | Водогрейная колонка                       | 22       | fallback | img/banners/baner_boiler.jpg                     |
| 90  | 51     | topki                                           | Каминные топки                            | 117      | fallback | img/popular/fireplace.jpg                        |
| 104 | 51     | elektrokamini                                   | Электрические камины                      | 129      | fallback | img/popular/fireplace.jpg                        |
| 111 | 51     | oblicovki                                       | Порталы для электрокамина                 | 99       | fallback | img/popular/fireplace.jpg                        |
| 71  | 73     | bloki-upravleniya                               | Блок управления                           | 3        | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 76  | 73     | kamni-dlya-bani                                 | Камни для бани                            | 12       | fallback | img/popular/sauna.jpg                            |
| 92  | 73     | registry                                        | Теплообменники                            | 9        | fallback | img/popular/sauna.jpg                            |
| 116 | 73     | izmeritelnye-pribory                            | Измерительные приборы                     | 2        | fallback | img/popular/sauna.jpg                            |
| 118 | 73     | ventilyacionnye-klapana                         | Вентиляционные клапана и решетки          | 3        | fallback | img/popular/sauna.jpg                            |
| 119 | 73     | kovriki-dlya-bani                               | Коврики для бани                          | 3        | fallback | img/popular/sauna.jpg                            |
| 120 | 73     | kupeli-2                                        | Купели                                    | 2        | fallback | img/popular/sauna.jpg                            |
| 121 | 73     | shajki-dlya-bani                                | Шайки для бани                            | 14       | fallback | img/popular/sauna.jpg                            |
| 122 | 73     | oblivnye-ustrojstva                             | Обливные устройства                       | 5        | fallback | img/popular/sauna.jpg                            |
| 124 | 73     | zaparniki                                       | Запарники                                 | 1        | fallback | img/popular/sauna.jpg                            |
| 201 | 73     | otdelka-dlya-parnoj                             | Отделка для парной                        | 8        | fallback | img/popular/sauna.jpg                            |
| 117 | 73     | abazhury-dlya-bani                              | Абажуры для бани                          | 8        | fallback | img/popular/sauna.jpg                            |
| 291 | 73     | drovnitsyi                                      | Дровницы и каминные принадлежности        | 69       | fallback | img/popular/fireplace.jpg                        |
| 294 | 73     | aksessuaryi-dlya-bani                           | Аксессуары для бани                       | 9        | fallback | img/popular/sauna.jpg                            |
| 279 | 73     | kaminnye-nabory                                 | Каминные наборы                           | 35       | fallback | img/popular/fireplace.jpg                        |
| 233 | 87     | alyuminievye-radiatory                          | Алюминиевые радиаторы                     | 34       | fallback | img/popular/radiatory.jpg                        |
| 235 | 87     | stalnye-radiatory                               | Стальные радиаторы                        | 710      | fallback | img/popular/radiatory.jpg                        |
| 236 | 87     | bimetallicheskie-radiatory                      | Биметаллические радиаторы                 | 109      | fallback | img/popular/radiatory.jpg                        |
| 324 | 87     | vodyanye-konvektory                             | Водяные конвекторы                        | 30       | fallback | img/popular/radiatory.jpg                        |
| 61  | 113    | pechi-kaminy                                    | Печи-камины                               | 134      | fallback | img/popular/pech.jpg                             |
| 63  | 113    | peci-drovianye-otopitelnye                      | Печи дровяные (отопительные)              | 55       | fallback | img/popular/pech.jpg                             |
| 219 | 113    | burzhuiki-pechi                                 | Буржуйки                                  | 55       | fallback | img/popular/pech.jpg                             |
| 220 | 113    | dlya-dachi                                      | Для дачи                                  | 2        | fallback | img/popular/pech.jpg                             |
| 287 | 113    | pechnoe-i-kaminnoe-lite                         | Печное и каминное литье                   | 151      | fallback | img/popular/pech.jpg                             |
| 129 | 128    | kaminnye-reshyotki                              | Каминные решётки                          | 92       | fallback | img/popular/fireplace.jpg                        |
| 340 | 193    | truby-iz-sshitogo-polietilena                   | Трубы из сшитого полиэтилена              | 16       | fallback | img/popular/truby-i-fitingi.jpg                  |
| 349 | 193    | rezbovye-fitingi                                | Резьбовые фитинги                         | 15       | fallback | img/popular/truby-i-fitingi.jpg                  |
| 325 | 193    | vodyanoy-teplyy-pol                             | Водяной теплый пол                        | 23       | fallback | img/popular/teplyj-pol.jpg                       |
| 369 | 193    | press-fitingi                                   | Пресс-фитинги                             | 465      | fallback | img/popular/truby-i-fitingi.jpg                  |
| 368 | 193    | kompressionnye-fitingi                          | Компрессионные фитинги                    | 15       | fallback | img/popular/truby-i-fitingi.jpg                  |
| 370 | 193    | krepleniya-dlya-trub                            | Крепления для труб                        | 17       | fallback | img/popular/truby-i-fitingi.jpg                  |
| 85  | 195    | komplekty-podklyucheniya                        | Комплекты подключения                     | 68       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 94  | 195    | solnechnye-kollektory                           | Солнечные коллекторы                      | 13       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 200 | 195    | istochniki-besperebojnogo-pitaniya              | Источники бесперебойного питания          | 8        | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 267 | 195    | regulyatoryi-davleniya-gaza                     | Регуляторы давления газа                  | 1        | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 299 | 195    | elektricheskie-teny-dlya-otopleniya             | Электрические ТЭНы                        | 20       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 283 | 195    | montajnyie-komplektyi                           | Монтажные комплекты систем отопления      | 6        | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 196 | 195    | gruppy-bystrogo-montazha-kotelnyx               | Группы быстрого монтажа котельных         | 87       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 89  | 195    | membrannye-baki                                 | Мембранные баки                           | 27       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 91  | 195    | bufernye-emkosti                                | Буферные емкости                          | 81       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 93  | 195    | grebenki                                        | Гребенки                                  | 79       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 242 | 195    | gidravlicheskie-razdeliteli-i-kollektory        | Гидравлические разделители и коллекторы   | 20       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 58  | 195    | regulyatory                                     | Автоматика и терморегуляторы              | 166      | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 371 | 195    | radiatornaya-armatura                           | Радиаторная арматура                      | 140      | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 59  | 195    | stabilizatory-napryazheniya                     | Стабилизаторы напряжения                  | 40       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 86  | 195    | datchiki                                        | Датчики температуры                       | 11       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 106 | 195    | signalizatory-zagazovannosti                    | Сигнализаторы загазованности              | 18       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 96  | 195    | schetchiki-gaza                                 | Счетчики газа                             | 47       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 296 | 195    | teplonositeli                                   | Теплоносители (Антифриз)                  | 13       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 373 | 195    | predokhranitelnaya-i-reguliruyushchaya-armatura | Предохранительная и регулирующая арматура | 63       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 374 | 195    | smesitelnaya-armatura                           | Смесительная арматура                     | 9        | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 372 | 195    | instrumenty-dlya-montazha                       | Инструменты для монтажа                   | 3        | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 284 | 283    | komplektyi-dlya-tverdotoplivnyih-kotlov         | Для частных домов                         | 2        | fallback | img/popular/boiler_img.jpg                       |
| 326 | 301    | krany-i-zapornaya-armatura                      | Краны и запорная арматура                 | 37       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 327 | 301    | smesitelnye-klapany-i-uzly                      | Смесительные клапаны и узлы               | 29       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 328 | 301    | gruppy-bezopasnosti                             | Группы безопасности                       | 18       | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 329 | 301    | germetiki-i-montazhnye-materialy                | Герметики и монтажные материалы           | 5        | fallback | img/popular/komplektuyushhie-dlya-otopleniya.jpg |
| 248 | 303    | tsirkulyatsionnyie                              | Циркуляционные насосы                     | 419      | fallback | img/popular/nasosy.jpg                           |
| 264 | 303    | skvajinnye-nasosy                               | Скважинные насосы                         | 160      | fallback | img/popular/nasosy.jpg                           |
| 265 | 303    | drenajnyie                                      | Дренажные насосы                          | 68       | fallback | img/popular/nasosy.jpg                           |
| 249 | 303    | poverhnostnyie                                  | Поверхностные насосы                      | 32       | fallback | img/popular/nasosy.jpg                           |
| 251 | 303    | nasosnyie-stantsii                              | Насосные станции (гидрофор)               | 65       | fallback | img/popular/nasosy.jpg                           |
| 202 | 304    | obogrevateli                                    | Обогреватели                              | 80       | fallback | img/popular/elektricheskie-konvektoryi.jpg       |
| 52  | 305    | pechi-dlya-bani                                 | Для бани                                  | 2        | fallback | img/popular/pech.jpg                             |
| 69  | 305    | drovianye-peci-bannye                           | Дровяные печи (банные)                    | 298      | fallback | img/popular/sauna.jpg                            |
| 70  | 305    | elektrokamenki                                  | Электрокаменки                            | 3        | fallback | img/popular/sauna.jpg                            |
| 73  | 305    | aksessuary-dlya-bani                            | Для печей и каминов                       | 162      | fallback | img/popular/sauna.jpg                            |
| 74  | 305    | kupeli                                          | Баки для воды                             | 53       | fallback | img/popular/sauna.jpg                            |
| 72  | 305    | dveri-dlya-ban-i-saun                           | Двери для бани и сауны                    | 37       | fallback | img/popular/sauna.jpg                            |
| 295 | 305    | mangalyi                                        | Мангалы                                   | 34       | fallback | img/popular/sauna.jpg                            |
| 78  | 307    | dymohody-nerzhaveyushchie                       | Дымоходы                                  | 2        | fallback | img/popular/chimney.jpg                          |
| 57  | 307    | koaxial-dymoxod                                 | Дымоходы коаксиальные                     | 75       | fallback | img/popular/chimney.jpg                          |
| 316 | 307    | shibery-dymohod                                 | Шиберы и задвижки                         | 37       | fallback | img/popular/chimney.jpg                          |
| 317 | 307    | kondensatootvody                                | Конденсатоотводы и ревизии                | 43       | fallback | img/popular/chimney.jpg                          |
| 318 | 307    | krepleniya-dymohod                              | Крепления и монтаж                        | 119      | fallback | img/popular/chimney.jpg                          |
| 319 | 307    | zonty-deflektory                                | Зонты и дефлекторы                        | 54       | fallback | img/popular/chimney.jpg                          |
| 320 | 307    | teplosyomniki                                   | Теплосъёмники                             | 21       | fallback | img/popular/chimney.jpg                          |
| 321 | 307    | perehody-adaptery-dymohod                       | Переходы и адаптеры                       | 130      | fallback | img/popular/chimney.jpg                          |
| 322 | 307    | zaglushki-dymohod                               | Заглушки и оголовки                       | 4        | fallback | img/popular/chimney.jpg                          |
| 309 | 308    | truby-mono                                      | Трубы одностенные                         | 118      | fallback | img/popular/chimney.jpg                          |
| 310 | 308    | troyniki-mono                                   | Тройники моно                             | 91       | fallback | img/popular/chimney.jpg                          |
| 311 | 308    | kolena-mono                                     | Колена и отводы моно                      | 140      | fallback | img/popular/chimney.jpg                          |
| 313 | 312    | truby-sendvich                                  | Трубы сэндвич                             | 88       | fallback | img/popular/chimney.jpg                          |
| 314 | 312    | troyniki-sendvich                               | Тройники сэндвич                          | 21       | fallback | img/popular/chimney.jpg                          |
| 315 | 312    | kolena-sendvich                                 | Колена и отводы сэндвич                   | 37       | fallback | img/popular/chimney.jpg                          |
+-----+--------+-------------------------------------------------+-------------------------------------------+----------+----------+--------------------------------------------------+

```
