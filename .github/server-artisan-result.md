# Server Artisan Result

- Time: 2026-07-11 09:07:31 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:sanitize-content-html --slug-like=teplov-i-suhov --not-archived --extract-media --restore-teplov-suhov-media --missing-media-only --show-samples=5 --limit=20`
- Log file: `storage/logs/server-artisan-teplov-suhov-media-dry-run.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   1522b70..0524402  main       -> origin/main
Updating 1522b70..0524402
Fast-forward
 .github/server-artisan-result.md                   | 32 +++++-----
 .github/server-artisan-task.json                   |  8 +--
 .../Commands/SanitizeProductContentHtmlCommand.php | 55 ++++++++++++++++-
 public/assets/css/kotlov.css                       | 72 ++++++++++++++++++++--
 resources/views/pages/product.blade.php            |  8 ++-
 5 files changed, 148 insertions(+), 27 deletions(-)
DRY RUN: database will not be changed.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 0     |
| changed             | 0     |
| written             | 0     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 0     |
+---------------------+-------+

```
