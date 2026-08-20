# Server Artisan Result

- Time: 2026-08-20 15:58:30 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-tm-tmarket --apply --replace-images --content --limit=20`
- Log file: `storage/logs/tm-tmarket-seo-1.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   9691e9f..ec7dfe6  main       -> origin/main
Updating 9691e9f..ec7dfe6
Fast-forward
 .github/server-artisan-result.md | 102 +++++++++++++++++++++++++++++++++++----
 .github/server-artisan-task.json |   6 +--
 2 files changed, 95 insertions(+), 13 deletions(-)
APPLY: products will be enriched from TMarket.
Products to check: 20
TMarket categories: 27
De Dietrich: candidate URLs 23
Shinhoo: candidate URLs 8
SFA: candidate URLs 35
Джилекс: candidate URLs 100
+-------------+-----------------------------------------------+-----------------------------------------------+------------------+---------------------------------------------------------------------------------------------------------------------------------+
| brand       | site product                                  | tmarket match                                 | found            | url/status                                                                                                                      |
+-------------+-----------------------------------------------+-----------------------------------------------+------------------+---------------------------------------------------------------------------------------------------------------------------------+
| De Dietrich | HX 28 Набор для переоборудования на пропан    | —                                             | skip             | no safe match                                                                                                                   |
| De Dietrich | HX 31 Датчик наружной температуры             | —                                             | skip             | no safe match                                                                                                                   |
| De Dietrich | HX 52 Датчик ГВС                              | Датчик ГВС (AD 212)                           | 3 img / 0 specs  | https://tmarket.by/product/otopitelnoe-oborudovanie-de-dietrich/avtomatika/datchik-gvs/                                         |
| De Dietrich | Горизонтальный коаксиальный дымоход Ø 60/1... | —                                             | skip             | no safe match                                                                                                                   |
| De Dietrich | Котел газовый MS 24 (одноконтурный, атмосф... | —                                             | skip             | no safe match                                                                                                                   |
| De Dietrich | Котел газовый MS 24 FF (одноконтурный, тур... | —                                             | skip             | no safe match                                                                                                                   |
| De Dietrich | Котел газовый MS 24 MI (двухконутрный, атм... | —                                             | skip             | no safe match                                                                                                                   |
| De Dietrich | Котел газовый MS 24 MI FF (двухконутрный,...  | —                                             | skip             | no safe match                                                                                                                   |
| SFA         | SANIACCESS 1                                  | Канализационный насос SANIACCESS 1            | 4 img / 6 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-saniaccess-1/                 |
| SFA         | SANIACCESS 2                                  | Канализационный насос SANIACCESS 2            | 4 img / 6 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-saniaccess-2/                 |
| SFA         | SANIACCESS 3                                  | Канализационный насос SANIACCESS 3            | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-saniaccess-3/                 |
| SFA         | SANIACCESS Pump                               | Канализационный насос SANIACCESS Pump         | 4 img / 8 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-kukhni-i-prachechnoy/kanalizatsionnyy-nasos-saniaccess-pump/ |
| SFA         | SANIBEST PRO                                  | Канализационный насос SANIBEST Pro (только... | 4 img / 5 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanibest-pro/                 |
| SFA         | SANIBOX (SOLOLIFT2 WC-3)                      | Канализационный насос SANIBOX                 | 4 img / 4 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanibox/                      |
| SFA         | SANIBROYEUR                                   | Канализационный насос SANIBROYEUR             | 4 img / 5 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanibroyeur/                  |
| SFA         | SANICOM 1                                     | Канализационный насос SANICOM 1 (для профе... | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-kukhni-i-prachechnoy/kanalizatsionnyy-nasos-sanicom-1/       |
| SFA         | SANICOM 2                                     | Канализационный насос SANICOM 2 (для профе... | 4 img / 5 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-kukhni-i-prachechnoy/kanalizatsionnyy-nasos-sanicom-2/       |
| SFA         | SANICONDENS Best Flat                         | SANICONDENS Best Flat                         | 4 img / 8 specs  | https://tmarket.by/product/nasosy-sfa/nasosy-dlya-otvoda-kondensata/sanicondens-best-flat/                                      |
| SFA         | SANICONDENS Clim Deco                         | SANICONDENS CLIM DECO                         | 4 img / 11 specs | https://tmarket.by/product/nasosy-sfa/nasosy-dlya-otvoda-kondensata/sanicondens-clim-deco/                                      |
| SFA         | Sanicondens Clim mini S                       | SANICONDENS Clim mini S                       | 4 img / 5 specs  | https://tmarket.by/product/nasosy-sfa/nasosy-dlya-otvoda-kondensata/sanicondens-clim-mini-s/                                    |
+-------------+-----------------------------------------------+-----------------------------------------------+------------------+---------------------------------------------------------------------------------------------------------------------------------+
+---------------+-------+
| metric        | count |
+---------------+-------+
| matched       | 13    |
| skipped       | 7     |
| images_saved  | 50    |
| specs_found   | 77    |
| content_found | 13    |
| errors        | 0     |
+---------------+-------+

```
