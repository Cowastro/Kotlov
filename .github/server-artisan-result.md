# Server Artisan Result

- Time: 2026-07-11 13:12:52 UTC
- Task: `artisan-dry-run`
- Artisan args: `product:enrich-content --all --only=content --offset=1 --limit=1 --min-specs=3 --openai --ai-model=gpt-4.1 --debug-ai --dry-run`
- Log file: `storage/logs/server-artisan-openai-content-preview-v4.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   71f66b5..6a6568e  main       -> origin/main
Updating 71f66b5..6a6568e
Fast-forward
 .github/server-artisan-result.md                   | 25 ++++++++-------
 .github/server-artisan-task.json                   |  6 ++--
 .../Commands/EnrichProductContentCommand.php       |  9 ++++++
 app/Services/AiContentEnricher.php                 | 36 +++++++++++++++++++++-
 4 files changed, 59 insertions(+), 17 deletions(-)
Provider: gpt-4.1 (api.openai.com)
Candidates: 1252 | processing: 1 (offset=1)
[1/1] id=5586 Водонагреватель электрический Гродторгмаш ЭВАД-10 / 1.6
  specs available: 14
  AI returned empty response, skipped.
  AI debug error: HTTP 403
  AI raw: {"error":{"code":"unsupported_country_region_territory","message":"Country, region, or territory not supported","param":null,"type":"request_forbidden"}}
+---------+-------+
| action  | count |
+---------+-------+
| updated | 0     |
| skipped | 1     |
| errors  | 0     |
+---------+-------+

1250 more remain. Continue with --offset=2

```
