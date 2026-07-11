# Server Artisan Result

- Time: 2026-07-11 19:36:55 UTC
- Task: `artisan-dry-run`
- Artisan args: `catalog:audit-menu --empty-only --limit=200`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   4a1c8e5..8f98613  main       -> origin/main
Updating 4a1c8e5..8f98613
Fast-forward
 .github/server-artisan-result.md | 60 ++++++++++++++--------------------------
 .github/server-artisan-task.json |  6 ++--
 2 files changed, 24 insertions(+), 42 deletions(-)
+-----------------------+-------+
| metric                | count |
+-----------------------+-------+
| active_categories     | 160   |
| empty_active_branches | 45    |
| shown_rows            | 44    |
+-----------------------+-------+
+-------+-----+--------+---------------------------------------+-------------------------------------+--------+--------+----------+
| depth | id  | parent | slug                                  | name                                | direct | branch | children |
+-------+-----+--------+---------------------------------------+-------------------------------------+--------+--------+----------+
| 1     | 213 | 49     | kotly-na-pelletah                     | Котлы на пеллетах                   | 0      | 0      | 0        |
| 2     | 227 | 98     | protochnye                            | Проточные                           | 0      | 0      | 0        |
| 1     | 237 | 307    | dymoxody-dlya-pechej                  | Дымоходы для печей                  | 0      | 0      | 0        |
| 1     | 230 | 307    | dymoxody-dlya-bani                    | Дымоходы для бани                   | 0      | 0      | 0        |
| 1     | 232 | 307    | dymoxody-dlya-kaminov                 | Дымоходы для каминов                | 0      | 0      | 0        |
| 1     | 323 | 307    | prochie-dymohod                       | Прочие комплектующие                | 0      | 0      | 0        |
| 1     | 105 | 51     | bio-kaminy                            | Каминокомплекты                     | 0      | 0      | 0        |
| 1     | 365 | 51     | biokaminy                             | Биокамины                           | 0      | 0      | 0        |
| 1     | 338 | 193    | polipropilenovye-truby                | Полипропиленовые трубы              | 0      | 0      | 0        |
| 1     | 339 | 193    | polietilenovye-truby                  | Полиэтиленовые трубы                | 0      | 0      | 0        |
| 1     | 341 | 193    | metalloplastikovye-truby              | Металлопластиковые трубы            | 0      | 0      | 0        |
| 1     | 342 | 193    | kanalizatsionnye-truby                | Канализационные трубы               | 0      | 0      | 0        |
| 1     | 343 | 193    | gofrirovanye-truby                    | Гофрированные трубы                 | 0      | 0      | 0        |
| 1     | 344 | 193    | truby-dlya-teplogo-vodyanogo-pola     | Трубы для теплого водяного пола     | 0      | 0      | 0        |
| 1     | 345 | 193    | napornye-truby-iz-polietilena         | Напорные трубы из полиэтилена       | 0      | 0      | 0        |
| 1     | 346 | 193    | truby-zashchitnye                     | Трубы защитные                      | 0      | 0      | 0        |
| 1     | 347 | 193    | fitingi-dlya-metalloplastikovykh-trub | Фитинги для металлопластиковых труб | 0      | 0      | 0        |
| 1     | 348 | 193    | sharovye-krany                        | Шаровые краны                       | 0      | 0      | 0        |
| 1     | 234 | 87     | chugunnye-radiatory                   | Чугунные радиаторы                  | 0      | 0      | 0        |
| 1     | 350 | 109    | nagrevatelnie-maty                    | Нагревательные маты                 | 0      | 0      | 0        |
| 1     | 351 | 109    | nagrevatelnie-kabeli                  | Нагревательные кабели               | 0      | 0      | 0        |
| 1     | 352 | 109    | ik-plenochny-pol                      | ИК Пленочный пол                    | 0      | 0      | 0        |
| 1     | 353 | 109    | podlozhka-pod-teplyy-pol              | Подложка под теплый пол             | 0      | 0      | 0        |
| 1     | 354 | 109    | teplyy-pol-pod-laminat                | Теплый пол под ламинат              | 0      | 0      | 0        |
| 1     | 355 | 109    | teplyy-pol-pod-plitku                 | Теплый пол под плитку               | 0      | 0      | 0        |
| 1     | 356 | 109    | termoregulyatory-dlya-teplogo-pola    | Терморегуляторы для теплого пола    | 0      | 0      | 0        |
| 1     | 357 | 109    | komplektuyushchie-dlya-teplogo-pola   | Комплектующие для теплого пола      | 0      | 0      | 0        |
| 1     | 366 | 109    | samoreguliruyushchiesya-kabeli        | Саморегулирующиеся кабели           | 0      | 0      | 0        |
| 1     | 358 | 304    | ventilyatory                          | Вентиляторы                         | 0      | 0      | 0        |
| 1     | 359 | 304    | maslyanye-obrevateli                  | Масляные обогреватели               | 0      | 0      | 0        |
| 1     | 360 | 304    | infrakrasnye-obrevateli               | Инфракрасные обогреватели           | 0      | 0      | 0        |
| 1     | 361 | 304    | teploventilyatory                     | Тепловентиляторы                    | 0      | 0      | 0        |
| 1     | 362 | 304    | teplovye-zavesy                       | Тепловые завесы                     | 0      | 0      | 0        |
| 1     | 363 | 304    | uvlazhniteli-vozdukha                 | Увлажнители воздуха                 | 0      | 0      | 0        |
| 1     | 364 | 304    | mojki-vozdukha                        | Мойки воздуха                       | 0      | 0      | 0        |
| 1     | 280 | 304    | teplovye-pushki                       | Тепловые пушки                      | 0      | 0      | 0        |
| 1     | 306 | 304    | mobilnye-kondicionery                 | Мобильные кондиционеры              | 0      | 0      | 0        |
| 1     | 334 | 303    | nasosy-povysheniya-davleniya          | Насосы повышения давления           | 0      | 0      | 0        |
| 1     | 335 | 303    | nasosy-dlya-kolodtsa                  | Насосы для колодца                  | 0      | 0      | 0        |
| 1     | 336 | 303    | fekalnye-nasosy                       | Фекальные насосы                    | 0      | 0      | 0        |
| 1     | 337 | 303    | kanalizatsionnye-nasosy               | Канализационные насосы              | 0      | 0      | 0        |
| 1     | 102 | 195    | akkumuliruyushhie-baki                | Аккумулирующие баки                 | 0      | 0      | 0        |
| 1     | 367 | 195    | sistema-zashchity-ot-protechek        | Система защиты от протечек          | 0      | 0      | 0        |
| 2     | 218 | 69     | metall-dlya-bani                      | Металлические                       | 0      | 0      | 0        |
+-------+-----+--------+---------------------------------------+-------------------------------------+--------+--------+----------+

```
