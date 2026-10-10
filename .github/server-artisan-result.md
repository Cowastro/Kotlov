# Server Artisan Result

Historical raw diagnostics were removed because GitHub Actions must not retain HTTP cookies, log messages, command arguments, or customer data.

Future runs store only aggregate fields:

```text
task=not-run
status=0
output_lines=0
output_bytes=0
error_lines=0
warning_lines=0
detail_log=storage/logs/github-actions-not-run.log
```

Detailed diagnostics remain in the server-local log named by `detail_log` and are not copied into GitHub.
