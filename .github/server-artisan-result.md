# Server Artisan Result

- Time: 2026-07-11 19:15:47 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-gazkotelbel --only-missing`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   a108088..3bd8bc6  main       -> origin/main
Updating a108088..3bd8bc6
Fast-forward
 .github/server-artisan-result.md | 434 ++++++++++++++++++++++++---------------
 .github/server-artisan-task.json |   4 +-
 2 files changed, 272 insertions(+), 166 deletions(-)
DRY RUN — database will not be changed.

── Series: zhytomyr-3
  desc:  Это наиболее популярная серия котлов АТЕМ.

Котлы серии Житомир-3 имеют очень высокую еффе
  specs: 11 columns → [007, 010, 012, 015, 020, 025, 030, 045, 060, 080, 100]
  found: 19 product(s)
  skip (has desc): [Ж3-КС-Г-007СН]
  skip (has desc): [Ж3-КС-Г-010СН]
  skip (has desc): [Ж3-КС-Г-012СН]
  skip (has desc): [Ж3-КС-Г-015СН]
  skip (has desc): [Ж3-КС-Г-020СН]
  skip (has desc): [Ж3-КС-Г-025СН]
  skip (has desc): [Ж3-КС-Г-030СН]
  skip (has desc): [Ж3-КС-Г-035СН]
  skip (has desc): [Ж3-КС-Г-045СН]
  skip (has desc): [Ж3-КС-Г-060СН]
  skip (has desc): [Ж3-КС-Г-080СН]
  skip (has desc): [Ж3-КС-Г-100СН]
  skip (has desc): [Ж3-КС-ГВ-007СН]
  skip (has desc): [Ж3-КС-ГВ-010СН]
  skip (has desc): [Ж3-КС-ГВ-012СН]
  skip (has desc): [Ж3-КС-ГВ-015СН]
  skip (has desc): [Ж3-КС-ГВ-020СН]
  skip (has desc): [Ж3-КС-ГВ-025СН]
  skip (has desc): [Ж3-КС-ГВ-030СН]

── Series: zhytomyr-10
  desc:  Котел Житомир-10 &#8212; это сочетание двух популярных устройств в одном корпусе: эффектив
  specs: 7 columns → [007, 010, 012, 015, 020, 025, 030]
  found: 7 product(s)
  skip (has desc): [Ж10-КС-Г-007СН]
  skip (has desc): [Ж10-КС-Г-010СН]
  skip (has desc): [Ж10-КС-Г-012СН]
  skip (has desc): [Ж10-КС-Г-015СН]
  skip (has desc): [Ж10-КС-Г-020СН]
  skip (has desc): [Ж10-КС-Г-025СН]
  skip (has desc): [Ж10-КС-Г-030СН]

── Series: zhytomyr-turbo
  desc:  Котел «Житомир-Турбо» решает проблему с дымоходом раз и навсегда! Стабильная и максимально
  specs: 7 columns → [010, 012, 016, 020, 025, 030, 040]
  found: 6 product(s)
  skip (has desc): [ТУРБО-КС-Г-10СН]
  skip (has desc): [ТУРБО-КС-Г-12СН]
  skip (has desc): [ТУРБО-КС-Г-16СН]
  skip (has desc): [ТУРБО-КС-Г-20СН]
  skip (has desc): [ТУРБО-КС-Г-25СН]
  skip (has desc): [ТУРБО-КС-Г-30СН]

── Series: zhytomyr-9
  desc:  Котел построен на базе популярной модели «Житомир-3» совмещенной с твердотопливным котлом.
  specs: 4 columns → [010, 012, 016, 020]
  found: 4 product(s)
  skip (has desc): [КС-Г-010СН/АОТВ-10]
  skip (has desc): [КС-Г-012СН/АОТВ-12]
  skip (has desc): [КС-Г-016СН/АОТВ-16]
  skip (has desc): [КС-Г-020СН/АОТВ-20]

── Series: zhytomyr-m
  desc:  &#171;Житомир-М&#187; &#8212; газовые котлы, не требующие дымохода. Отвод продуктов сгоран
  specs: 5 columns → [5, 7, 10, 12, 15]
  found: 8 product(s)
  skip (has desc): [АДГВ-10СН]
  skip (has desc): [АДГВ-12СН]
  skip (has desc): [АДГВ-15СН]
  skip (has desc): [АДГВ-7СН]
  skip (has desc): [АОГВ-10СН]
  skip (has desc): [АОГВ-12СН]
  skip (has desc): [АОГВ-15СН]
  skip (has desc): [АОГВ-7СН]

── Series: zhytomyr-aotv
  desc:  Габаритные размеры топки котла &#171;Житомир&#187; позволяют комфортно загружать топливо р
  specs: 3 columns → [14, 18, 22]
  found: 5 product(s)
  skip (has desc): [АКТВ-14]
  skip (has desc): [АОТВ-12]
  skip (has desc): [АОТВ-14]
  skip (has desc): [АОТВ-18]
  skip (has desc): [АОТВ-22]

── Series: zhytomyr-doors
  desc:  Габаритные размеры топки котла &#171;Житомир&#187; позволяют комфортно загружать топливо р
  specs: 3 columns → [14, 18, 22]
  found: 3 product(s)
  skip (has desc): [ЖИТОМИР-14М]
  skip (has desc): [ЖИТОМИР-18М]
  skip (has desc): [ЖИТОМИР-22М]

── Series: zhytomyr-5
  desc:  Удвоенная, по сравнению с классическим КНС, площадь теплоотдачи делает этот конвектор очен
  specs: 5 columns → [2, 3, 4, 5, 6]
  found: 7 product(s)
  skip (has desc): [КНС-10]
  skip (has desc): [КНС-2]
  skip (has desc): [КНС-3]
  skip (has desc): [КНС-4]
  skip (has desc): [КНС-5]
  skip (has desc): [КНС-6]
  skip (has desc): [КНС-8]

── Series: vpg-20
  desc:  Мощное устройство с производительностью до 9,3 литров горячей воды в минуту и отводом прод
  specs: 2 columns → [1 шт,  ]
  found: 1 product(s)
  skip (has desc): [ВПГ-20]

── Series: vpg-20m
  desc:  Мощное устройство с производительностью до 10 литров горячей воды в минуту и отводом проду
  specs: 2 columns → [1 шт,  ]
  found: 1 product(s)
  skip (has desc): [ВПГ-20М]

── Series: vpg-20tm
  desc:  Мощное устройство с производительностью до 10 литров горячей воды в минуту и отводом проду
  specs: 0 columns → []
  found: 1 product(s)
  skip (has desc): [ВПГ-20ТМ]

── Series: vpg-20t
  desc:  Мощный аппарат для нагрева воды с производительностью 9,4 литров горячей воды в минуту и п
  specs: 2 columns → [1 шт,  ]
  found: 1 product(s)
  skip (has desc): [ВПГ-20Т]

+----------+-------+
| metric   | count |
+----------+-------+
| series   | 12    |
| products | 63    |
| updated  | 0     |
| skipped  | 63    |
| errors   | 0     |
+----------+-------+

```
