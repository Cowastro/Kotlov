# Server Artisan Result

- Time: 2026-08-20 15:37:07 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-tm-tmarket --apply --replace-images`
- Log file: `storage/logs/tm-tmarket-enrich-3.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   3eb7a8b..9691e9f  main       -> origin/main
Updating 3eb7a8b..9691e9f
Fast-forward
 .github/server-artisan-result.md                   | 24 ++++++--------
 .github/server-artisan-task.json                   |  8 ++---
 .../Commands/EnrichTmManagementTmarketCommand.php  | 37 ++++++++++++++++++++++
 3 files changed, 51 insertions(+), 18 deletions(-)
APPLY: products will be enriched from TMarket.
Products to check: 443
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
| SFA         | SANICONDENS Pro                               | SANICONDENS Pro                               | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/nasosy-dlya-otvoda-kondensata/sanicondens-pro/                                            |
| SFA         | SANICUBIC 1 IP 67 с измельчителем             | Sanicubic 1 IP 67                             | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosnye-stantsii-kns/sanicubic-1-ip-67/                                 |
| SFA         | Sanicubic 1 VX                                | Sanicubic 1 VX                                | 4 img / 8 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosnye-stantsii-kns/sanicubic-1-vx/                                    |
| SFA         | SANICUBIC 1 WP с измельчителем                | —                                             | skip             | no safe match                                                                                                                   |
| SFA         | Sanicubic 2 Pro                               | —                                             | skip             | no safe match                                                                                                                   |
| SFA         | SANICUBIC 2 XL VX 2 x 2000 Вт                 | —                                             | skip             | no safe match                                                                                                                   |
| SFA         | SANICUBIC 2 XL VX TRI SMART 2 x 3500 Вт       | —                                             | skip             | no safe match                                                                                                                   |
| SFA         | SANIDOUCHE (SOLOLIFT2 D-2)                    | Канализационный насос SANIDOUCHE              | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-dusha/kanalizatsionnyy-nasos-sanidouche/                     |
| SFA         | SANIDOUCHE Flat в комплекте с пооским сифо... | Канализационный насос SANIDOUCHE Flat (под... | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-dusha/kanalizatsionnyy-nasos-sanidouche-flat/                |
| SFA         | SANIFLOOR 1 в комплекте с сифоном для трап... | Канализационный насос SANIFLOOR 1 (для каф... | 4 img / 6 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-dusha/kanalizatsionnyy-nasos-sanifloor-1/                    |
| SFA         | SANIFLOOR 2 в комплекте с сифоном для трап... | —                                             | skip             | no safe match                                                                                                                   |
| SFA         | SANIPACK (SOLOLIFT2 CWC-3)                    | Канализационный насос SANIPACK                | 4 img / 8 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanipack/                     |
| SFA         | SANIPRO                                       | Канализационный насос SANIPRO                 | 4 img / 4 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanipro/                      |
| SFA         | SANIPUMP GR                                   | SANIPUMP GR                                   | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/fekalnye-nasosy/sanipump-gr/                                                              |
| SFA         | SANIPUMP VX                                   | SANIPUMP VX                                   | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/fekalnye-nasosy/sanipump-vx/                                                              |
| SFA         | SANISPEED                                     | Канализационный насос SANISPEED               | 4 img / 8 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-kukhni-i-prachechnoy/kanalizatsionnyy-nasos-sanispeed/       |
| SFA         | SANITOP (SOLOLIFT2 WC-1)                      | Канализационный насос SANITOP                 | 4 img / 4 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanitop/                      |
| SFA         | SANIVITE (SOLOLIFT2 C-3)                      | Канализационный насос SANIVITE                | 4 img / 7 specs  | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-kukhni-i-prachechnoy/kanalizatsionnyy-nasos-sanivite/        |
| SFA         | Аварийная сигнализация SANIALARM              | SANIALARM                                     | 3 img / 1 specs  | https://tmarket.by/product/nasosy-sfa/dopolnitelnoe-oborudovanie/sanialarm/                                                     |
| SFA         | Современная насосная станция Sanicubic 2 C... | —                                             | skip             | no safe match                                                                                                                   |
| SFA         | Шиберная задвижка Vanne DN 100/ Vanne DN 1... | VANNE DN 100                                  | 4 img / 5 specs  | https://tmarket.by/product/nasosy-sfa/dopolnitelnoe-oborudovanie/vanne-dn-100/                                                  |
| SFA         | Шиберная задвижка Vanne DN 50 Шиберная зад... | VANNE DN 50                                   | 4 img / 4 specs  | https://tmarket.by/product/nasosy-sfa/dopolnitelnoe-oborudovanie/vanne-dn-50/                                                   |
| Shinhoo     | BASIC 25-12S 1x230V                           | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 25-16 1x230V                            | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 25-20 1x230V                            | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 32-12 180 1x230V                        | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 32-12F 1x230V                           | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 50-12F 1x230V                           | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 65-12F 1х230V                           | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 65-8F 1х230V                            | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC 65-8SF 3х180V                           | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 32-12SF 1x230V                      | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 32-8SF 1x230V                       | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 40-14SF 1x230V                      | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 40-14SF 3x380V                      | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 40-18SF 1x230V                      | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 40-18SF 3x380V                      | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 40-4SF 1x230V                       | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 40-6SF 1x230V                       | —                                             | skip             | no safe match                                                                                                                   |
| Shinhoo     | BASIC PRO 50-12SF 1x230V                      | —                                             | skip             | no safe match                                                                                                                   |
+-------------+-----------------------------------------------+-----------------------------------------------+------------------+---------------------------------------------------------------------------------------------------------------------------------+
... 383 more rows
+---------------+-------+
| metric        | count |
+---------------+-------+
| matched       | 131   |
| skipped       | 312   |
| images_saved  | 334   |
| specs_found   | 1093  |
| content_found | 0     |
| errors        | 0     |
+---------------+-------+

```
