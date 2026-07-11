# Server Artisan Result

- Time: 2026-07-11 21:19:53 UTC
- Task: `artisan-dry-run`
- Artisan args: `product:enrich-content --all --only=both --source-context --require-source-context --skip-root-source-context --min-source-context-chars=80 --min-specs=2 --rewrite-thin=220 --limit=5 --sleep=300 --dry-run --debug-ai`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   81fc5fb..c42f101  main       -> origin/main
Updating 81fc5fb..c42f101
Fast-forward
 .github/server-artisan-result.md | 225 ++++++++++++++++++++++++++++++++++++---
 .github/server-artisan-task.json |   4 +-
 2 files changed, 212 insertions(+), 17 deletions(-)
Provider: deepseek-chat (api.deepseek.com)
Candidates: 120 | processing: 5 (offset=0)
[1/5] id=5701 Печь-камин Мета-Бел Сена 7 кВт (АОТ-7,0)
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: only 0 specs, min is 2
[2/5] id=6589 Печь-каменка Мета-Бел ПБМ 16 (в модификации ПС)
  source context: https://metabel.by/produktsiya (82 chars, 0 specs)
  skipped: only 0 specs, min is 2
[3/5] id=10790 Дверь каминная Kratki Zuzia 
  source context: https://ligmet.by/
  source context skipped: cURL error 6: Could not resolve host: ligmet.by (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://ligmet.by/
  skipped: source URL points to a bare domain/home page
[4/5] id=10807 Дверь каминная Kratki Maja 
  source context: https://ligmet.by/
  source context skipped: cURL error 6: Could not resolve host: ligmet.by (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://ligmet.by/
  skipped: source URL points to a bare domain/home page
[5/5] id=11848 КПД ЧЕРНЫЙ Розета 0,7мм ф150
  source context: https://ligmet.by/
  source context skipped: cURL error 6: Could not resolve host: ligmet.by (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://ligmet.by/
  skipped: source URL points to a bare domain/home page
+---------+-------+
| action  | count |
+---------+-------+
| updated | 0     |
| skipped | 5     |
| errors  | 0     |
+---------+-------+

115 more remain. Continue with --offset=5

```
