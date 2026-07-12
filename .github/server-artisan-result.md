# Server Artisan Result

- Time: 2026-07-12 03:29:11 UTC
- Task: `artisan-dry-run`
- Artisan args: `product:enrich-content --all --only=both --min-specs=0 --rewrite-thin=220 --source-context --require-source-context --min-source-context-chars=450 --skip-root-source-context --limit=20 --sleep=0 --dry-run`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   7f7d69f..6fbf70e  main       -> origin/main
Updating 7f7d69f..6fbf70e
Fast-forward
 .github/server-artisan-result.md | 225 ++-------------------------------------
 .github/server-artisan-task.json |   4 +-
 2 files changed, 11 insertions(+), 218 deletions(-)
Provider: deepseek-chat (api.deepseek.com)
Candidates: 238 | processing: 20 (offset=0)
[1/20] id=5701 Печь-камин Мета-Бел Сена 7 кВт (АОТ-7,0)
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[2/20] id=6589 Печь-каменка Мета-Бел ПБМ 16 (в модификации ПС)
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[3/20] id=11848 КПД ЧЕРНЫЙ Розета 0,7мм ф150
  source context: https://ligmet.by/
  source context skipped: cURL error 6: Could not resolve host: ligmet.by (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://ligmet.by/
  skipped: source URL points to a bare domain/home page
[4/20] id=11849 КПД ЧЕРНЫЙ Труба 250мм 2мм ф150
  source context: https://ligmet.by/
  source context skipped: cURL error 6: Could not resolve host: ligmet.by (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://ligmet.by/
  skipped: source URL points to a bare domain/home page
[5/20] id=12381 Печь-камин Мета-Бел Нарва 7М
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[6/20] id=12390 Очаг-гриль ОГ-01
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[7/20] id=12391 Очаг-гриль ОГ-02
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[8/20] id=12392 Труба 1 м
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[9/20] id=12393 Труба 0,5 м
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[10/20] id=12394 Фартук трубы
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[11/20] id=12395 Проходной элемент
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[12/20] id=12396 Фартук проходного элемента
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[13/20] id=12398 Мангал М-01 (3 мм.)
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[14/20] id=12399 Дверь печная ДП 308
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[15/20] id=12400 Дверь печная ДП-14
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: source context is too short (82 chars, min is 450)
[16/20] id=13760 Радиатор Royal Thermo PianoForte 300 Noir Sable VDR80 - 12 с
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source URL points to a bare domain/home page
[17/20] id=13892 Комплект плоских кронштейнов Royal Thermo с дюбелями 7,2х170
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source URL points to a bare domain/home page
[18/20] id=14590 Радиатор панельный Royal Thermo COMPACT C22-300-600 RAL9016
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source URL points to a bare domain/home page
[19/20] id=14632 Радиатор панельный Royal Thermo COMPACT C22-500-400 RAL9016
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source URL points to a bare domain/home page
[20/20] id=14634 Радиатор панельный Royal Thermo COMPACT C22-500-600 RAL9016
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source URL points to a bare domain/home page
+---------+-------+
| action  | count |
+---------+-------+
| updated | 0     |
| skipped | 20    |
| errors  | 0     |
+---------+-------+

218 more remain. Continue with --offset=20

```
