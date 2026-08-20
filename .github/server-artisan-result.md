# Server Artisan Result

- Time: 2026-08-20 17:01:27 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-tm-tmarket --apply --replace-images --content --limit=20 --offset=120`
- Log file: `storage/logs/tm-tmarket-seo-4.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   2b3861d..15bceca  main       -> origin/main
Updating 2b3861d..15bceca
Fast-forward
 .github/server-artisan-task.json                          | 6 +++---
 app/Console/Commands/EnrichTmManagementTmarketCommand.php | 5 +++++
 2 files changed, 8 insertions(+), 3 deletions(-)
APPLY: products will be enriched from TMarket.
Products to check: 20
TMarket categories: 27
De Dietrich: candidate URLs 23
Shinhoo: candidate URLs 8
SFA: candidate URLs 35
Джилекс: candidate URLs 100
+---------+-----------------------------------------------+-----------------------+------------------+---------------------------------------------------------------------------------------------+
| brand   | site product                                  | tmarket match         | found            | url/status                                                                                  |
+---------+-----------------------------------------------+-----------------------+------------------+---------------------------------------------------------------------------------------------+
| Watrix  | Соединитель TBN 70/2 для коллектора C 60 2... | —                     | skip             | no safe match                                                                               |
| Watrix  | Соединитель TBN 70/3 для коллектора C 60 3... | —                     | skip             | no safe match                                                                               |
| Watrix  | Соединитель TBN 70/4 для коллектора C 60 4... | —                     | skip             | no safe match                                                                               |
| Джилекс | "УЖ" комплект для всасывания 25-7,5           | —                     | skip             | no safe match                                                                               |
| Джилекс | "УЖ" комплект для всасывания 32-7,5           | —                     | skip             | no safe match                                                                               |
| Джилекс | "УЖ" шланг 25-15                              | —                     | skip             | no safe match                                                                               |
| Джилекс | "УЖ" шланг 25-7,5                             | —                     | skip             | no safe match                                                                               |
| Джилекс | "УЖ" шланг 32-15                              | —                     | skip             | no safe match                                                                               |
| Джилекс | "УЖ" шланг 32-7,5                             | —                     | skip             | no safe match                                                                               |
| Джилекс | Адаптер колодезный "АК"                       | —                     | skip             | no safe match                                                                               |
| Джилекс | Базовое решение автоматизации «БРА»           | —                     | skip             | no safe match                                                                               |
| Джилекс | Блок автоматики                               | Блок автоматики       | 3 img / 11 specs | https://tmarket.by/product/oborudovanie-dzhileks/komplektuyushchie2/blok-avtomatiki/        |
| Джилекс | Водозаборный фильтр 1 МП                      | Водозаборный фильтр   | 4 img / 6 specs  | https://tmarket.by/product/oborudovanie-dzhileks/komplektuyushchie2/vodozabornyy-filtr/     |
| Джилекс | Выключатель поплавковый универсальный с пр... | —                     | skip             | no safe match                                                                               |
| Джилекс | Гидроаккумулятор В 100                        | Гидроаккумулятор В100 | 2 img / 10 specs | https://tmarket.by/product/oborudovanie-dzhileks/gidroakkumulyatory/gidroakkumulyator-v100/ |
| Джилекс | Гидроаккумулятор В 100 «ХИТ»                  | —                     | skip             | no safe match                                                                               |
| Джилекс | Гидроаккумулятор В 150                        | Гидроаккумулятор В150 | 2 img / 10 specs | https://tmarket.by/product/oborudovanie-dzhileks/gidroakkumulyatory/gidroakkumulyator-v150/ |
| Джилекс | Гидроаккумулятор В 200                        | Гидроаккумулятор В200 | 2 img / 10 specs | https://tmarket.by/product/oborudovanie-dzhileks/gidroakkumulyatory/gidroakkumulyator-v200/ |
| Джилекс | Гидроаккумулятор В 300                        | Гидроаккумулятор В300 | 2 img / 10 specs | https://tmarket.by/product/oborudovanie-dzhileks/gidroakkumulyatory/gidroakkumulyator-v300/ |
| Джилекс | Гидроаккумулятор В 50                         | —                     | skip             | no safe match                                                                               |
+---------+-----------------------------------------------+-----------------------+------------------+---------------------------------------------------------------------------------------------+
+---------------+-------+
| metric        | count |
+---------------+-------+
| matched       | 6     |
| skipped       | 14    |
| images_saved  | 6     |
| specs_found   | 57    |
| content_found | 6     |
| errors        | 0     |
+---------------+-------+

```
