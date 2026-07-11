# Server Artisan Result

- Time: 2026-07-11 08:21:34 UTC
- Task: `artisan-dry-run`
- Artisan args: `catalog:audit-media --type=categories --only-with-products --missing-only --limit=120`
- Log file: `storage/logs/server-artisan-category-media.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   db32bf3..126af4c  main       -> origin/main
Updating db32bf3..126af4c
Fast-forward
 .github/server-artisan-result.md |  27 +++++----
 .github/server-artisan-task.json |   6 +-
 app/Models/Category.php          | 122 +++++++++++++++++++++++++++++++++++++++
 3 files changed, 138 insertions(+), 17 deletions(-)
Categories
+----------+-------+
| metric   | count |
+----------+-------+
| checked  | 108   |
| missing  | 9     |
| broken   | 0     |
| fallback | 99    |
| ok       | 0     |
+----------+-------+
+-----+--------+----------------------------------+---------------------------------+----------+---------+------+
| id  | parent | slug                             | name                            | products | media   | path |
+-----+--------+----------------------------------+---------------------------------+----------+---------+------+
| 63  | 113    | peci-drovianye-otopitelnye       | Печи дровяные (отопительные)    | 55       | missing | -    |
| 129 | 128    | kaminnye-reshyotki               | Каминные решётки                | 92       | missing | -    |
| 325 | 193    | vodyanoy-teplyy-pol              | Водяной теплый пол              | 23       | missing | -    |
| 326 | 301    | krany-i-zapornaya-armatura       | Краны и запорная арматура       | 37       | missing | -    |
| 327 | 301    | smesitelnye-klapany-i-uzly       | Смесительные клапаны и узлы     | 29       | missing | -    |
| 328 | 301    | gruppy-bezopasnosti              | Группы безопасности             | 18       | missing | -    |
| 329 | 301    | germetiki-i-montazhnye-materialy | Герметики и монтажные материалы | 5        | missing | -    |
| 202 | 304    | obogrevateli                     | Обогреватели                    | 80       | missing | -    |
| 69  | 305    | drovianye-peci-bannye            | Дровяные печи (банные)          | 298      | missing | -    |
+-----+--------+----------------------------------+---------------------------------+----------+---------+------+

```
