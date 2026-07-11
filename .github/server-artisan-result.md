# Server Artisan Result

- Time: 2026-07-11 09:11:09 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:sanitize-content-html --slug-like=teplov-i-suhov --not-archived --extract-media --restore-teplov-suhov-media --show-samples=5 --limit=20`
- Log file: `storage/logs/server-artisan-teplov-suhov-media-dry-run.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   0524402..0bf13d0  main       -> origin/main
Updating 0524402..0bf13d0
Fast-forward
 .github/server-artisan-result.md | 49 +++++++++++++++++++++-------------------
 .github/server-artisan-task.json |  4 ++--
 2 files changed, 28 insertions(+), 25 deletions(-)
DRY RUN: database will not be changed.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 20    |
| changed             | 12    |
| written             | 0     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 0     |
+---------------------+-------+
+------+------------+----------------+------------------------------------------------------------+
| ID   | SKU        | Brand          | Product                                                    |
+------+------------+----------------+------------------------------------------------------------+
| 8985 | PS-008.985 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 120       |
| 8986 | PS-008.986 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 150       |
| 8988 | PS-008.988 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 200       |
| 8989 | PS-008.989 | Теплов и Сухов | Адаптер котла Теплов и Сухов моно М-М 430-0.8, Ø 250       |
| 8990 | PS-008.990 | Теплов и Сухов | Адаптер-переход Теплов и Сухов Моно М-М 430-0.8, Ø 100/110 |
| 8991 | PS-008.991 | Теплов и Сухов | Адаптер-переход Теплов и Сухов Моно М-М 430-0.8, Ø 110/120 |
| 8993 | PS-008.993 | Теплов и Сухов | Адаптер-переход Теплов и Сухов Моно М-М 430-0.8, Ø 120/130 |
| 8995 | PS-008.995 | Теплов и Сухов | Адаптер-переход Теплов и Сухов Моно М-М 430-0.8, Ø 150/160 |
| 8996 | PS-008.996 | Теплов и Сухов | Адаптер-переход Теплов и Сухов Моно М-М 430-0.8, Ø 160/180 |
| 8999 | PS-008.999 | Теплов и Сухов | Дефлектор Теплов и Сухов моно ДМ-Р 430, 0.5, Ø 200         |
| 9001 | PS-009.001 | Теплов и Сухов | Заглушка ревизии моно 430 0,5 мм Ø 120                     |
| 9003 | PS-009.003 | Теплов и Сухов | Заглушка ревизии моно 430 0,5 мм Ø 180                     |
+------+------------+----------------+------------------------------------------------------------+
+------+------------+----------------------------------------------------+-----------------------------------------------------------------------------------+----------------------------------------------------------+-----------+
| ID   | SKU        | Slug                                               | Video                                                                             | Documents                                                | Raw links |
+------+------------+----------------------------------------------------+-----------------------------------------------------------------------------------+----------------------------------------------------------+-----------+
| 8985 | PS-008.985 | adapter-kotla-teplov-i-suhov-mono-akm-r-430-08-120 | https://www.youtube.com/embed/ctxgc0yoy8o?list=PLAhMCOyBsBHom4fUsCZqCIEQ9zqZLy2gn | https://admin.kotlov.by/downloads/catalogue_TIS_2025.pdf | -         |
| 8986 | PS-008.986 | adapter-kotla-teplov-i-suhov-mono-akm-r-430-08-150 | https://www.youtube.com/embed/ctxgc0yoy8o?list=PLAhMCOyBsBHom4fUsCZqCIEQ9zqZLy2gn | https://admin.kotlov.by/downloads/catalogue_TIS_2025.pdf | -         |
| 8987 | PS-008.987 | adapter-kotla-teplov-i-suhov-mono-akm-r-430-08-180 | https://www.youtube.com/embed/ctxgc0yoy8o?list=PLAhMCOyBsBHom4fUsCZqCIEQ9zqZLy2gn | https://admin.kotlov.by/downloads/catalogue_TIS_2025.pdf | -         |
| 8988 | PS-008.988 | adapter-kotla-teplov-i-suhov-mono-akm-r-430-08-200 | https://www.youtube.com/embed/ctxgc0yoy8o?list=PLAhMCOyBsBHom4fUsCZqCIEQ9zqZLy2gn | https://admin.kotlov.by/downloads/catalogue_TIS_2025.pdf | -         |
| 8989 | PS-008.989 | adapter-kotla-teplov-i-suhov-mono-akm-r-430-08-250 | https://www.youtube.com/embed/ctxgc0yoy8o?list=PLAhMCOyBsBHom4fUsCZqCIEQ9zqZLy2gn | https://admin.kotlov.by/downloads/catalogue_TIS_2025.pdf | -         |
+------+------------+----------------------------------------------------+-----------------------------------------------------------------------------------+----------------------------------------------------------+-----------+

```
