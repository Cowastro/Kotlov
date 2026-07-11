# Server Artisan Result

- Time: 2026-07-11 21:15:39 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --active-only --not-archived --issues=no_content,no_short --max-attrs=2 --limit=120`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   e2dea3d..81fc5fb  main       -> origin/main
Updating e2dea3d..81fc5fb
Fast-forward
 .github/server-artisan-result.md | 36 ++++++++++++++++--------------------
 .github/server-artisan-task.json |  4 ++--
 2 files changed, 18 insertions(+), 22 deletions(-)
Products with content-health issues: 2412
Showing rows: 120 (limit 120)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 212      |
| no_content | 1561     |
| no_short   | 2410     |
| low_attrs  | 481      |
| no_source  | 2224     |
+------------+----------+
By supplier
+--------------+----------+----------+------------+-----------+
| Name         | Products | No photo | No content | Low attrs |
+--------------+----------+----------+------------+-----------+
| -            | 2159     | 37       | 1413       | 255       |
| ligmet       | 156      | 104      | 72         | 152       |
| thermostudio | 41       | 28       | 41         | 28        |
| maitek-group | 23       | 23       | 23         | 23        |
| metabel      | 13       | 9        | 0          | 11        |
| gazkotelbel  | 8        | 8        | 8          | 8         |
| elicon       | 8        | 0        | 0          | 0         |
| rusklimat    | 2        | 1        | 2          | 2         |
| tsk_nasosy   | 1        | 1        | 1          | 1         |
| akvatermex   | 1        | 1        | 1          | 1         |
+--------------+----------+----------+------------+-----------+
By brand
+------------------+----------+----------+------------+-----------+
| Name             | Products | No photo | No content | Low attrs |
+------------------+----------+----------+------------+-----------+
| -                | 276      | 17       | 260        | 48        |
| Ferrum           | 190      | 0        | 190        | 6         |
| Darco            | 179      | 0        | 179        | 2         |
| ТехноЛит         | 95       | 0        | 0          | 0         |
| Теплов и Сухов   | 88       | 0        | 10         | 8         |
| Pegas            | 81       | 0        | 0          | 6         |
| Kratki           | 74       | 32       | 16         | 72        |
| Sakovich         | 67       | 0        | 6          | 0         |
| Лемакс           | 58       | 0        | 54         | 0         |
| Tenko            | 53       | 0        | 52         | 6         |
| Теплоприбор      | 50       | 0        | 6          | 0         |
| Смолком          | 43       | 0        | 43         | 0         |
| СтальСнабДизайн  | 43       | 0        | 5          | 43        |
| S-TANK           | 42       | 1        | 26         | 0         |
| Royal Flame      | 41       | 0        | 41         | 0         |
| KOTLOV           | 38       | 0        | 0          | 0         |
| Велес Электро    | 38       | 17       | 33         | 27        |
| Ермак            | 38       | 23       | 22         | 31        |
| СТЭН             | 38       | 23       | 34         | 23        |
| Blist            | 32       | 32       | 31         | 32        |
+------------------+----------+----------+------------+-----------+
By category
+------------------------------------+----------+----------+------------+-----------+
| Name                               | Products | No photo | No content | Low attrs |
+------------------------------------+----------+----------+------------+-----------+
| Твердотопливные                    | 265      | 1        | 52         | 1         |
| Электрические                      | 257      | 22       | 205        | 33        |
| Дровяные печи (банные)             | 177      | 4        | 2          | 12        |
| Газовые                            | 152      | 10       | 132        | 18        |
| Переходы и адаптеры                | 82       | 0        | 74         | 0         |
| Дровницы и каминные принадлежности | 78       | 59       | 65         | 78        |
| Циркуляционные насосы              | 77       | 8        | 76         | 9         |
| Колена и отводы моно               | 76       | 0        | 67         | 0         |
| Трубы одностенные                  | 71       | 0        | 61         | 0         |
| Крепления и монтаж                 | 66       | 0        | 45         | 10        |
| Электрические камины               | 61       | 1        | 61         | 1         |
| Каминные решётки                   | 60       | 23       | 5          | 60        |
| Насосные станции (гидрофор)        | 58       | 5        | 58         | 3         |
| Группы быстрого монтажа котельных  | 53       | 17       | 52         | 53        |
| Порталы для электрокамина          | 51       | 1        | 45         | 1         |
| Пеллетные горелки                  | 48       | 0        | 0          | 0         |
| Тройники моно                      | 46       | 0        | 45         | 1         |
| Косвенные                          | 41       | 1        | 21         | 1         |
| Стальные радиаторы                 | 35       | 0        | 35         | 1         |
| Трубы сэндвич                      | 34       | 0        | 27         | 0         |
+------------------------------------+----------+----------+------------+-----------+

+------+------------+-------+-----------------------------------+-----------+-------+--------------------------------------------------+----------------+------------------------------------------------------------+
| ID   | SKU        | Brand | Category                          | Suppliers | Attrs | Issues                                           | Source domains | Product                                                    |
+------+------------+-------+-----------------------------------+-----------+-------+--------------------------------------------------+----------------+------------------------------------------------------------+
| 5218 | PS-005.218 | -     | Счетчики газа                     | -         | 12    | no_content,no_short,no_source                    | -              | Шланг для газа ADVIXON Г-Ш 1/2"-1/2" (150см)               |
| 5635 | PS-005.635 | -     | Теплоносители (Антифриз)          | -         | 2     | no_content,no_short,low_attrs,no_source          | -              | Антифриз ANTIFROGEN N/ АНТИФРОГЕН N 20 л (теплоноситель... |
| 5636 | PS-005.636 | -     | Теплоносители (Антифриз)          | -         | 2     | no_content,no_short,low_attrs,no_source          | -              | Антифриз ANTIFROGEN L/ АНТИФРОГЕН L 20 л (теплоноситель... |
| 5663 | PS-005.663 | -     | Фильтры                           | -         | 3     | no_content,no_short,no_source                    | -              | Фильтр BWT для холодной воды Protector Mini C/R 1/2        |
| 5664 | PS-005.664 | -     | Фильтры                           | -         | 3     | no_content,no_short,no_source                    | -              | Фильтр BWT для горячей воды Protector Mini H/R 1/2         |
| 5692 | PS-005.692 | -     | Колена и отводы сэндвич           | -         | 9     | no_content,no_short,no_source                    | -              | Колено 90 утепленное нерж/оцинк D 150/220 1mm              |
| 5704 | PS-005.704 | -     | Сигнализаторы загазованности      | -         | 14    | no_content,no_short,no_source                    | -              | Сигнализатор загазованности Барьер-CH4                     |
| 5705 | PS-005.705 | -     | Сигнализаторы загазованности      | -         | 14    | no_content,no_short,no_source                    | -              | Датчик CO Барьер (сигнализатор угарного газа)              |
| 5725 | PS-005.725 | -     | Фильтры                           | -         | 3     | no_content,no_short,no_source                    | -              | Фильтр BWT для холодной воды Protector Mini C/R 3/4        |
| 5726 | PS-005.726 | -     | Фильтры                           | -         | 3     | no_content,no_short,no_source                    | -              | Фильтр BWT для горячей воды Protector Mini H/R 3/4         |
| 5752 | PS-005.752 | -     | Баки для воды                     | -         | 8     | no_content,no_short,no_source                    | -              | Бак для воды Феролайф ф150/V80 AISI 430 БКС-80             |
| 5763 | PS-005.763 | -     | Баки для воды                     | -         | 9     | no_content,no_short,no_source                    | -              | Труба EUROTIS (Италия) из нержавеющей стали 3/4            |
| 5764 | PS-005.764 | -     | Баки для воды                     | -         | 9     | no_content,no_short,no_source                    | -              | Труба EUROTIS (Италия) из нержавеющей стали 1/2            |
| 5770 | PS-005.770 | -     | Переходы и адаптеры               | -         | 9     | no_content,no_short,no_source                    | -              | Старт-сэндвич 120х180 (1 метр) 1mm                         |
| 5785 | PS-005.785 | -     | Переходы и адаптеры               | -         | 9     | no_content,no_short,no_source                    | -              | Переход 90х115                                             |
| 5798 | PS-005.798 | -     | Теплоносители (Антифриз)          | -         | 1     | no_content,no_short,low_attrs,no_source          | -              | Ингибитор коррозии ANTIFROGEN Protectogen C Aqua           |
| 5822 | PS-005.822 | -     | Обливные устройства               | -         | 4     | no_content,no_short,no_source                    | -              | Обливное устройство 22 л                                   |
| 5841 | PS-005.841 | -     | Переходы и адаптеры               | -         | 14    | no_content,no_short,no_source                    | -              | Старт-сэндвич 115х180 (0.5 метр) 1mm                       |
| 5899 | PS-005.899 | -     | Скважинные насосы                 | -         | 0     | no_content,no_short,low_attrs,no_source          | -              | Блок управления SIRIO ENTRY 230 ITALTECNICA                |
| 5924 | PS-005.924 | -     | Баки для воды                     | -         | 9     | no_content,no_short,no_source                    | -              | Бак для воды Термофор Цеппелин 70 (выносной)               |
| 6059 | PS-006.059 | -     | Газовые                           | -         | 28    | no_content,no_short,no_source                    | -              | Водонагреватель газовый Superflame SF0322                  |
| 6062 | PS-006.062 | -     | Тройники моно                     | -         | 11    | no_content,no_short,no_source                    | -              | Комплект дымохода Schiedel Rondo Plus d=160мм с тройник... |
| 6073 | PS-006.073 | -     | Источники бесперебойного питания  | -         | 4     | no_content,no_short,no_source                    | -              | Аккумуляторная батарея (АКБ) для ИБП 65А/h (Ventura GPL... |
| 6672 | PS-006.672 | -     | Теплоносители (Антифриз)          | -         | 0     | no_content,no_short,low_attrs,no_source          | -              | Антифриз (теплоноситель) Hot Stream – Тепло вашего дома... |
| 6673 | PS-006.673 | -     | Теплоносители (Антифриз)          | -         | 0     | no_content,no_short,low_attrs,no_source          | -              | Антифриз (теплоноситель) Hot Stream – Тепло вашего дома... |
| 6836 | PS-006.836 | -     | Счетчики газа                     | -         | 12    | no_content,no_short,no_source                    | -              | Счетчик газа бытовой Русбелгаз РБГ У G1,6 A                |
| 7059 | PS-007.059 | -     | Счетчики газа                     | -         | 12    | no_short,no_source                               | -              | Счетчик газа бытовой Элехант СГБ-1,8                       |
| 7125 | PS-007.125 | -     | Сигнализаторы загазованности      | -         | 14    | no_content,no_short,no_source                    | -              | Извещатель Технозащита ИП 401-12Т (сигнализатор угарног... |
| 7229 | PS-007.229 | -     | Стабилизаторы напряжения          | -         | 26    | no_content,no_short,no_source                    | -              | Стабилизатор напряжения SUNTEK СНЭТ PR 500ВА               |
| 7269 | PS-007.269 | -     | Стабилизаторы напряжения          | -         | 26    | no_content,no_short,no_source                    | -              | Стабилизатор напряжения SUNTEK СНЭТ PR 1000ВА              |
| 7289 | PS-007.289 | -     | Дымоходы коаксиальные             | -         | 8     | no_content,no_short,no_source                    | -              | Дымоход коаксиальный Thermex 60/100 (Xantus)               |
| 7291 | PS-007.291 | -     | Дымоходы коаксиальные             | -         | 8     | no_content,no_short,no_source                    | -              | Дымоход коаксиальный Thermex 60/100 (EuroElite)            |
| 7558 | PS-007.558 | -     | Группы быстрого монтажа котельных | -         | 2     | no_content,no_short,low_attrs,no_source          | -              | Насосно-смесительный узел Profactor PF MB 842 (без насоса) |
| 7697 | PS-007.697 | -     | Герметики и монтажные материалы   | -         | 2     | no_content,no_short,low_attrs,no_source          | -              | Краска термостойкая (эмаль) Certa черная (520мл)           |
| 7699 | PS-007.699 | -     | Источники бесперебойного питания  | -         | 4     | no_content,no_short,no_source                    | -              | Аккумуляторная батарея (АКБ) для ИБП 65А/h (Security Po... |
| 7700 | PS-007.700 | -     | Источники бесперебойного питания  | -         | 4     | no_content,no_short,no_source                    | -              | Аккумуляторная батарея (АКБ) для ИБП 100А/h (Security P... |
| 7702 | PS-007.702 | -     | Порталы для электрокамина         | -         | 0     | no_content,no_short,low_attrs,no_source          | -              | Портал VERONA STD (Бел. дуб, патина золото)                |
| 7707 | PS-007.707 | -     | Газовые                           | -         | 44    | no_photo,no_content,no_short,no_source           | -              | еще тест4                                                  |
| 7752 | PS-007.752 | -     | Дымоходы коаксиальные             | -         | 8     | no_content,no_short,no_source                    | -              | AB 605 (71.BE7.00.10) Коаксиальный удлинитель 1000мм  8... |
| 7753 | PS-007.753 | -     | Дымоходы коаксиальные             | -         | 8     | no_content,no_short,no_source                    | -              | AB 607 (71.BE7.00.12) Коаксиальный отвод 90 80/125 Bosch   |
| 7777 | PS-007.777 | -     | Сигнализаторы загазованности      | -         | 14    | no_content,no_short,no_source                    | -              | Детектор загазованности Счетприбор CH4 (природный газ) ... |
| 7778 | PS-007.778 | -     | Сигнализаторы загазованности      | -         | 14    | no_content,no_short,no_source                    | -              | Детектор загазованности Счетприбор CО (угарный газ) (ДЗ... |
| 7779 | PS-007.779 | -     | Сигнализаторы загазованности      | -         | 14    | no_content,no_short,no_source                    | -              | Детектор загазованности Счетприбор СН4,СО (два газа) (Д... |
| 7799 | PS-007.799 | -     | Колена и отводы сэндвич           | -         | 11    | no_content,no_short,no_source                    | -              | Сэндвич-колено 90 FERRUM (430/0,8мм + нерж.) Ф150х210      |
| 7831 | PS-007.831 | -     | Баки для воды                     | -         | 9     | no_content,no_short,no_source                    | -              | Бак для воды Ferrum Комфорт (201/1.0) горизонтальный эл... |
| 8092 | PS-008.092 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Foreman 4.5              |
| 8093 | PS-008.093 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Foreman 6                |
| 8094 | PS-008.094 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Foreman 8                |
| 8095 | PS-008.095 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Foreman 12               |
| 8096 | PS-008.096 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Master 4.5               |
| 8097 | PS-008.097 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Master 6                 |
| 8098 | PS-008.098 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Master 8                 |
| 8099 | PS-008.099 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Master 12                |
| 8100 | PS-008.100 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (LF) 4             |
| 8102 | PS-008.102 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (LF) 6             |
| 8103 | PS-008.103 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (LF) 8             |
| 8105 | PS-008.105 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 4              |
| 8107 | PS-008.107 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 6              |
| 8109 | PS-008.109 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 8              |
| 8110 | PS-008.110 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 12             |
| 8111 | PS-008.111 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 15             |
| 8112 | PS-008.112 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 18             |
| 8113 | PS-008.113 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 21             |
| 8114 | PS-008.114 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Wespe Heizung Elite (L) 24             |
| 8180 | PS-008.180 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-18 (380В)                    |
| 8181 | PS-008.181 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-24 (380В)                    |
| 8182 | PS-008.182 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-30 (380В)                    |
| 8183 | PS-008.183 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-36 (380В)                    |
| 8188 | PS-008.188 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-12 ЭУ (380В)                 |
| 8193 | PS-008.193 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-30 ЭУ (380В)                 |
| 8194 | PS-008.194 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-36 ЭУ (380В)                 |
| 8195 | PS-008.195 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-24 ЭУ (380В)                 |
| 8196 | PS-008.196 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Элвин ЭВП-18 ЭУ (380В)                 |
| 8234 | PS-008.234 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел ЭВАН WARMOS M (7.5-кВт) 220            |
| 8278 | PS-008.278 | -     | Электрические                     | -         | 22    | no_content,no_short,no_source                    | -              | Электрический котел Kospel EKCO.R2-24                      |
| 8314 | PS-008.314 | -     | Солнечные коллекторы              | -         | 0     | no_content,no_short,low_attrs,no_source          | -              | Солнечный вакуумный коллектор JMC-5818-18                  |
| 8315 | PS-008.315 | -     | Солнечные коллекторы              | -         | 0     | no_content,no_short,low_attrs,no_source          | -              | Солнечный вакуумный коллектор JMC-5818-24                  |
| 8316 | PS-008.316 | -     | Солнечные коллекторы              | -         | 0     | no_content,no_short,low_attrs,no_source          | -              | Солнечный вакуумный коллектор JMC-5818-30                  |
| 8374 | PS-008.374 | -     | Твердотопливные                   | -         | 0     | no_photo,no_content,no_short,low_attrs,no_source | -              | Твердотопливный котел Altep DUO UNI Plus 200               |
| 8391 | PS-008.391 | -     | Твердотопливные                   | -         | 29    | no_content,no_short,no_source                    | -              | Твердотопливный котел Altep TRIO UNI Plus 97               |
| 8461 | PS-008.461 | -     | Поверхностные насосы              | -         | 11    | no_content,no_short,no_source                    | -              | Насос центробежный Unipump JET 40S                         |
| 8465 | PS-008.465 | -     | Насосные станции (гидрофор)       | -         | 13    | no_content,no_short,no_source                    | -              | Насосная станция Unipump AUTO JET 40 S                     |
| 8474 | PS-008.474 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA1 L 32-40               |
| 8475 | PS-008.475 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA1 L 32-60               |
| 8476 | PS-008.476 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA1 L 20-40 N             |
| 8477 | PS-008.477 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 25-40                 |
| 8478 | PS-008.478 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 25-60 N               |
| 8479 | PS-008.479 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 32-40                 |
| 8480 | PS-008.480 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 32-60                 |
| 8481 | PS-008.481 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 25-40 N               |
| 8485 | PS-008.485 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 25-80 N               |
| 8487 | PS-008.487 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 32-40 N               |
| 8488 | PS-008.488 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 32-60 N               |
| 8489 | PS-008.489 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA2 32-80 N               |
| 8490 | PS-008.490 | -     | Циркуляционные насосы             | -         | 0     | no_photo,no_content,no_short,low_attrs,no_source | -              | Насос циркуляционный Grundfos ALPHA2 15-40 130             |
| 8491 | PS-008.491 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 25-40                 |
| 8492 | PS-008.492 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 25-60                 |
| 8493 | PS-008.493 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 25-80                 |
| 8494 | PS-008.494 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 32-40                 |
| 8495 | PS-008.495 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 32-60                 |
| 8497 | PS-008.497 | -     | Циркуляционные насосы             | -         | 0     | no_photo,no_content,no_short,low_attrs,no_source | -              | Товар 8497                                                 |
| 8498 | PS-008.498 | -     | Циркуляционные насосы             | -         | 11    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 32-80                 |
| 8499 | PS-008.499 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 25-40 130             |
| 8500 | PS-008.500 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 25-60 130             |
| 8501 | PS-008.501 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA3 25-80 130             |
| 8502 | PS-008.502 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA SOLAR 15-75 130        |
| 8503 | PS-008.503 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA SOLAR 25-75 130        |
| 8504 | PS-008.504 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA SOLAR 25-75 180        |
| 8505 | PS-008.505 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos ALPHA SOLAR 25-145 180       |
| 8506 | PS-008.506 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный  Grundfos UPS 25-40 A                 |
| 8507 | PS-008.507 | -     | Циркуляционные насосы             | -         | 14    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 25-60 A                  |
| 8509 | PS-008.509 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 25-40                    |
| 8510 | PS-008.510 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 25-60                    |
| 8511 | PS-008.511 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 25-70                    |
| 8512 | PS-008.512 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 25-80                    |
| 8513 | PS-008.513 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 25-100                   |
| 8514 | PS-008.514 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 25-120                   |
| 8515 | PS-008.515 | -     | Циркуляционные насосы             | -         | 12    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 32-40                    |
| 8516 | PS-008.516 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 32-60                    |
| 8517 | PS-008.517 | -     | Циркуляционные насосы             | -         | 13    | no_content,no_short,no_source                    | -              | Насос циркуляционный Grundfos UPS 32-70                    |
+------+------------+-------+-----------------------------------+-----------+-------+--------------------------------------------------+----------------+------------------------------------------------------------+

```
