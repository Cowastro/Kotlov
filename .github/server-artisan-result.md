# Server Artisan Result

- Time: 2026-07-11 14:24:08 UTC
- Task: `artisan-apply`
- Artisan args: `product:enrich-content --all --only=content --limit=25 --offset=100 --min-specs=3 --rewrite-thin=350 --source-context --require-source-context --min-source-context-chars=100 --ai-model=deepseek-chat`
- Log file: `storage/logs/server-artisan-deepseek-thin-source-apply-25f.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   e067d01..79d1a69  main       -> origin/main
Updating e067d01..79d1a69
Fast-forward
 .github/server-artisan-result.md | 72 ++++++++++++++++++++--------------------
 .github/server-artisan-task.json |  6 ++--
 2 files changed, 39 insertions(+), 39 deletions(-)
Provider: deepseek-chat (api.deepseek.com)
Candidates: 154 | processing: 25 (offset=100)
[1/25] id=16224 WELLMIX 80WQ2-50-25-7.5/2_380V
  source context: https://aqualider.by/ (52 chars, 1 specs)
  skipped: source context is too short (52 chars, min is 100)
[2/25] id=16250 UNIPUMP MVH 12-12
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_vertikalnye_nasosy/75028/ (52 chars, 7 specs)
  skipped: source context is too short (52 chars, min is 100)
[3/25] id=16276 UNIPUMP CM 4-3
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_gorizontalnye_nasosy/81224/ (52 chars, 6 specs)
  skipped: source context is too short (52 chars, min is 100)
[4/25] id=16294 UNIPUMP SVH 50-18-2,2/2
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/odnostupenchatye_vertikalnye_nasosy_inline/81237/ (52 chars, 6 specs)
  skipped: source context is too short (52 chars, min is 100)
[5/25] id=16296 UNIPUMP SVH 65-20-3/2
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/odnostupenchatye_vertikalnye_nasosy_inline/81239/ (52 chars, 6 specs)
  skipped: source context is too short (52 chars, min is 100)
[6/25] id=16372 UNIPUMP PSB 5-40
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_gorizontalnye_nasosy/82564/ (52 chars, 6 specs)
  skipped: source context is too short (52 chars, min is 100)
[7/25] id=16377 WELLMIX CMI 2-60-BACE (1х220В, 0,75кВт)
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_gorizontalnye_nasosy/83738/ (52 chars, 6 specs)
  skipped: source context is too short (52 chars, min is 100)
[8/25] id=16382 Дверка ЭВЕРЕСТ каминная 320 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-ehverest-kaminnaya-320-antracit (1192 chars, 0 specs)
  specs available: 3
  ✓ content saved
[9/25] id=16388 Дверка ЭТНА Панорама (правая)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-etna-panorama-pravaja (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[10/25] id=16389 Дверка ЭТНА 430 (левая)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-etna-430-pravaya (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[11/25] id=16599 Котел Куппер Практик -10В
  source context: https://bania.by/otoplenie-doma/otopitelnye-kotly/kotel-kupper-praktik-10v (116 chars, 0 specs)
  specs available: 6
  ✓ content saved
[12/25] id=16600 Котел Куппер Практик -16В
  source context: https://bania.by/otoplenie-doma/otopitelnye-kotly/kotel-kupper-praktik-16v (116 chars, 0 specs)
  specs available: 6
  ✓ content saved
[13/25] id=16609 Дверка ВЕЗУВИЙ каминная 218 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-218-bronza (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[14/25] id=16610 Дверка ВЕЗУВИЙ каминная 218 (не крашенная)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-218-ne-krashennaya (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[15/25] id=16611 Дверка ВЕЗУВИЙ каминная 220 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaja-220-antratsit (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[16/25] id=16612 Дверка ВЕЗУВИЙ каминная 240 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-240-antracit (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[17/25] id=16613 Дверка ВЕЗУВИЙ каминная 240 (не крашенная, без стекла)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-240-ne-krashennaya-bez-stekla (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[18/25] id=16615 Дверка ВЕЗУВИЙ каминная 280 (не крашенная, без стекла)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-280-ne-krashennaya (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[19/25] id=16616 Дверка ВЕЗУВИЙ каминная 280 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-280-bronza (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[20/25] id=16617 Дверка ВЕЗУВИЙ каминная 281 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-281-antracit (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[21/25] id=16618 Дверка ВЕЗУВИЙ каминная 281 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-281-bronza (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[22/25] id=16619 Дверка ВЕЗУВИЙ каминная 281 (не крашенная, без стекла)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-281-ne-krashennaya-bez-stekla (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[23/25] id=16622 Дверка ВЕЗУВИЙ 211 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-211-antracit (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[24/25] id=16623 Дверка ВЕЗУВИЙ каминная 218 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-218-antracit (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
[25/25] id=16627 Дверка ВЕЗУВИЙ каминная 230 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaja-230-bronza (77 chars, 0 specs)
  skipped: source context is too short (77 chars, min is 100)
+---------+-------+
| action  | count |
+---------+-------+
| updated | 3     |
| skipped | 22    |
| errors  | 0     |
+---------+-------+

29 more remain. Continue with --offset=125

```
