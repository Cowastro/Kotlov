# Server Artisan Result

- Time: 2026-08-20 16:33:06 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-tm-tmarket --apply --replace-images --content --limit=20 --offset=40`
- Log file: `storage/logs/tm-tmarket-seo-3.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   88f0774..bedb676  main       -> origin/main
Updating 88f0774..bedb676
Fast-forward
 .github/server-artisan-result.md                          | 13 +++++++------
 .github/server-artisan-task.json                          |  2 +-
 app/Console/Commands/EnrichTmManagementTmarketCommand.php | 11 ++++++++++-
 3 files changed, 18 insertions(+), 8 deletions(-)
APPLY: products will be enriched from TMarket.
Products to check: 20
TMarket categories: 27
De Dietrich: candidate URLs 23
Shinhoo: candidate URLs 8
SFA: candidate URLs 35
Джилекс: candidate URLs 100
+---------+------------------------------------+---------------+-----------------+--------------------------------------------------------------------------------+
| brand   | site product                       | tmarket match | found           | url/status                                                                     |
+---------+------------------------------------+---------------+-----------------+--------------------------------------------------------------------------------+
| SFA     | Шиберная задвижка SFA VANNE DN 100 | VANNE DN 100  | 4 img / 5 specs | https://tmarket.by/product/nasosy-sfa/dopolnitelnoe-oborudovanie/vanne-dn-100/ |
| SFA     | Шиберная задвижка SFA VANNE DN 50  | VANNE DN 50   | 4 img / 4 specs | https://tmarket.by/product/nasosy-sfa/dopolnitelnoe-oborudovanie/vanne-dn-50/  |
| Shinhoo | BASIC 25-12S 1x230V                | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 25-16 1x230V                 | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 25-20 1x230V                 | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 32-12 180 1x230V             | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 32-12F 1x230V                | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 50-12F 1x230V                | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 65-12F 1х230V                | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 65-8F 1х230V                 | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC 65-8SF 3х180V                | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 32-12SF 1x230V           | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 32-8SF 1x230V            | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 40-14SF 1x230V           | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 40-14SF 3x380V           | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 40-18SF 1x230V           | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 40-18SF 3x380V           | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 40-4SF 1x230V            | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 40-6SF 1x230V            | —             | skip            | no safe match                                                                  |
| Shinhoo | BASIC PRO 50-12SF 1x230V           | —             | skip            | no safe match                                                                  |
+---------+------------------------------------+---------------+-----------------+--------------------------------------------------------------------------------+
+---------------+-------+
| metric        | count |
+---------------+-------+
| matched       | 2     |
| skipped       | 18    |
| images_saved  | 8     |
| specs_found   | 9     |
| content_found | 2     |
| errors        | 0     |
+---------------+-------+

```
