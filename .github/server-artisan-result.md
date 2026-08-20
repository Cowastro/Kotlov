# Server Artisan Result

- Time: 2026-08-20 16:16:06 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-tm-tmarket --apply --replace-images --content --limit=20 --offset=20`
- Log file: `storage/logs/tm-tmarket-seo-2.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   cb9c0f9..d84fe73  main       -> origin/main
Updating cb9c0f9..d84fe73
Fast-forward
 .github/server-artisan-result.md                          | 13 +++++++------
 .github/server-artisan-task.json                          |  6 +++---
 app/Console/Commands/EnrichTmManagementTmarketCommand.php | 10 ++++++++--
 3 files changed, 18 insertions(+), 11 deletions(-)
APPLY: products will be enriched from TMarket.
Products to check: 20
TMarket categories: 27
De Dietrich: candidate URLs 23
Shinhoo: candidate URLs 8
SFA: candidate URLs 35
Джилекс: candidate URLs 100
+-------+-----------------------------------------------+-----------------------------------------------+-----------------+---------------------------------------------------------------------------------------------------------------------------+
| brand | site product                                  | tmarket match                                 | found           | url/status                                                                                                                |
+-------+-----------------------------------------------+-----------------------------------------------+-----------------+---------------------------------------------------------------------------------------------------------------------------+
| SFA   | SANICONDENS Pro                               | SANICONDENS Pro                               | 4 img / 7 specs | https://tmarket.by/product/nasosy-sfa/nasosy-dlya-otvoda-kondensata/sanicondens-pro/                                      |
| SFA   | SANICUBIC 1 IP 67 с измельчителем             | Sanicubic 1 IP 67                             | 4 img / 7 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosnye-stantsii-kns/sanicubic-1-ip-67/                           |
| SFA   | Sanicubic 1 VX                                | Sanicubic 1 VX                                | 4 img / 8 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosnye-stantsii-kns/sanicubic-1-vx/                              |
| SFA   | SANICUBIC 1 WP с измельчителем                | —                                             | skip            | no safe match                                                                                                             |
| SFA   | Sanicubic 2 Pro                               | —                                             | skip            | no safe match                                                                                                             |
| SFA   | SANICUBIC 2 XL VX 2 x 2000 Вт                 | —                                             | skip            | no safe match                                                                                                             |
| SFA   | SANICUBIC 2 XL VX TRI SMART 2 x 3500 Вт       | —                                             | skip            | no safe match                                                                                                             |
| SFA   | SANIDOUCHE (SOLOLIFT2 D-2)                    | Канализационный насос SANIDOUCHE              | 4 img / 7 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-dusha/kanalizatsionnyy-nasos-sanidouche/               |
| SFA   | SANIDOUCHE Flat в комплекте с пооским сифо... | Канализационный насос SANIDOUCHE Flat (под... | 4 img / 7 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-dusha/kanalizatsionnyy-nasos-sanidouche-flat/          |
| SFA   | SANIFLOOR 1 в комплекте с сифоном для трап... | Канализационный насос SANIFLOOR 1 (для каф... | 4 img / 6 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-dusha/kanalizatsionnyy-nasos-sanifloor-1/              |
| SFA   | SANIFLOOR 2 в комплекте с сифоном для трап... | —                                             | skip            | no safe match                                                                                                             |
| SFA   | SANIPACK (SOLOLIFT2 CWC-3)                    | Канализационный насос SANIPACK                | 4 img / 8 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanipack/               |
| SFA   | SANIPRO                                       | Канализационный насос SANIPRO                 | 4 img / 4 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanipro/                |
| SFA   | SANIPUMP GR                                   | SANIPUMP GR                                   | 4 img / 7 specs | https://tmarket.by/product/nasosy-sfa/fekalnye-nasosy/sanipump-gr/                                                        |
| SFA   | SANIPUMP VX                                   | SANIPUMP VX                                   | 4 img / 7 specs | https://tmarket.by/product/nasosy-sfa/fekalnye-nasosy/sanipump-vx/                                                        |
| SFA   | SANISPEED                                     | Канализационный насос SANISPEED               | 4 img / 8 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-kukhni-i-prachechnoy/kanalizatsionnyy-nasos-sanispeed/ |
| SFA   | SANITOP (SOLOLIFT2 WC-1)                      | Канализационный насос SANITOP                 | 4 img / 4 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-tualeta/kanalizatsionnyy-nasos-sanitop/                |
| SFA   | SANIVITE (SOLOLIFT2 C-3)                      | Канализационный насос SANIVITE                | 4 img / 7 specs | https://tmarket.by/product/nasosy-sfa/kanalizatsionnye-nasosy-dlya-kukhni-i-prachechnoy/kanalizatsionnyy-nasos-sanivite/  |
| SFA   | Аварийная сигнализация SANIALARM              | SANIALARM                                     | 3 img / 1 specs | https://tmarket.by/product/nasosy-sfa/dopolnitelnoe-oborudovanie/sanialarm/                                               |
| SFA   | Современная насосная станция Sanicubic 2 C... | —                                             | skip            | no safe match                                                                                                             |
+-------+-----------------------------------------------+-----------------------------------------------+-----------------+---------------------------------------------------------------------------------------------------------------------------+
+---------------+-------+
| metric        | count |
+---------------+-------+
| matched       | 14    |
| skipped       | 6     |
| images_saved  | 54    |
| specs_found   | 88    |
| content_found | 14    |
| errors        | 0     |
+---------------+-------+

```
