# Server Artisan Result

- Time: 2026-07-11 14:31:51 UTC
- Task: `artisan-apply`
- Artisan args: `product:enrich-content --all --only=content --limit=25 --offset=100 --min-specs=3 --rewrite-thin=350 --source-context --require-source-context --skip-root-source-context --ai-model=deepseek-chat`
- Log file: `storage/logs/server-artisan-deepseek-thin-source-apply-product-urls.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   79d1a69..485feef  main       -> origin/main
Updating 79d1a69..485feef
Fast-forward
 .github/server-artisan-result.md                   | 175 +++++++++++----------
 .github/server-artisan-task.json                   |   6 +-
 .../Commands/EnrichProductContentCommand.php       |  17 ++
 3 files changed, 109 insertions(+), 89 deletions(-)
Provider: deepseek-chat (api.deepseek.com)
Candidates: 151 | processing: 25 (offset=100)
[1/25] id=16224 WELLMIX 80WQ2-50-25-7.5/2_380V
  source context: https://aqualider.by/ (52 chars, 1 specs)
  skipped: source URL points to a bare domain/home page
[2/25] id=16250 UNIPUMP MVH 12-12
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_vertikalnye_nasosy/75028/ (52 chars, 7 specs)
  specs available: 28
  ✓ content saved
[3/25] id=16276 UNIPUMP CM 4-3
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_gorizontalnye_nasosy/81224/ (52 chars, 6 specs)
  specs available: 25
  ✓ content saved
[4/25] id=16294 UNIPUMP SVH 50-18-2,2/2
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/odnostupenchatye_vertikalnye_nasosy_inline/81237/ (52 chars, 6 specs)
  specs available: 27
  ✓ content saved
[5/25] id=16296 UNIPUMP SVH 65-20-3/2
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/odnostupenchatye_vertikalnye_nasosy_inline/81239/ (52 chars, 6 specs)
  specs available: 27
  ✓ content saved
[6/25] id=16372 UNIPUMP PSB 5-40
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_gorizontalnye_nasosy/82564/ (52 chars, 6 specs)
  specs available: 25
  ✓ content saved
[7/25] id=16377 WELLMIX CMI 2-60-BACE (1х220В, 0,75кВт)
  source context: https://aqualider.by/catalog/promyshlennye_nasosy/mnogostupenchatye_gorizontalnye_nasosy/83738/ (52 chars, 6 specs)
  specs available: 23
  ✓ content saved
[8/25] id=16388 Дверка ЭТНА Панорама (правая)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-etna-panorama-pravaja (77 chars, 0 specs)
  specs available: 3
  ✓ content saved
[9/25] id=16389 Дверка ЭТНА 430 (левая)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-etna-430-pravaya (77 chars, 0 specs)
  specs available: 3
  ✓ content saved
[10/25] id=16609 Дверка ВЕЗУВИЙ каминная 218 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-218-bronza (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[11/25] id=16610 Дверка ВЕЗУВИЙ каминная 218 (не крашенная)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-218-ne-krashennaya (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[12/25] id=16611 Дверка ВЕЗУВИЙ каминная 220 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaja-220-antratsit (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[13/25] id=16612 Дверка ВЕЗУВИЙ каминная 240 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-240-antracit (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[14/25] id=16613 Дверка ВЕЗУВИЙ каминная 240 (не крашенная, без стекла)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-240-ne-krashennaya-bez-stekla (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[15/25] id=16615 Дверка ВЕЗУВИЙ каминная 280 (не крашенная, без стекла)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-280-ne-krashennaya (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[16/25] id=16616 Дверка ВЕЗУВИЙ каминная 280 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-280-bronza (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[17/25] id=16617 Дверка ВЕЗУВИЙ каминная 281 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-281-antracit (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[18/25] id=16618 Дверка ВЕЗУВИЙ каминная 281 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-281-bronza (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[19/25] id=16619 Дверка ВЕЗУВИЙ каминная 281 (не крашенная, без стекла)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-281-ne-krashennaya-bez-stekla (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[20/25] id=16622 Дверка ВЕЗУВИЙ 211 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-211-antracit (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[21/25] id=16623 Дверка ВЕЗУВИЙ каминная 218 (Антрацит)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaya-218-antracit (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[22/25] id=16627 Дверка ВЕЗУВИЙ каминная 230 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaja-230-bronza (77 chars, 0 specs)
  specs available: 3
  ✓ content saved
[23/25] id=16629 Дверка ВЕЗУВИЙ каминная 217 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaja-217-bronza (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[24/25] id=16631 Дверка ВЕЗУВИЙ каминная 224 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaja-224-bronza (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
[25/25] id=16632 Дверка ВЕЗУВИЙ каминная 260 (Бронза)
  source context: https://bania.by/kaminnoe-i-pechnoe-lite/chugunnye-dverki/dverka-vezuvij-kaminnaja-260-bronza (77 chars, 0 specs)
  specs available: 4
  ✓ content saved
+---------+-------+
| action  | count |
+---------+-------+
| updated | 24    |
| skipped | 1     |
| errors  | 0     |
+---------+-------+

26 more remain. Continue with --offset=125

```
