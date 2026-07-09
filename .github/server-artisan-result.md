# Server Artisan Result

- Time: 2026-07-09 17:26:18 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --brand=Greolit --active-only --not-archived --limit=0`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
APPLY: sanitized content was written.
+--------------------+-------+
| metric             | count |
+--------------------+-------+
| checked            | 29    |
| changed            | 11    |
| written            | 11    |
| images_removed     | 18    |
| styles_removed     | 257   |
| bad_blocks_removed | 1     |
+--------------------+-------+
+-------+------------+---------+---------------------------------------------------+
| ID    | SKU        | Brand   | Product                                           |
+-------+------------+---------+---------------------------------------------------+
| 11962 | PS-011.962 | Greolit | Уличный твердотопливный котел GREOLIT STREET 95H  |
| 11963 | PS-011.963 | Greolit | Твердотопливный котел Greolit DEEP (15 кВт)       |
| 11964 | PS-011.964 | Greolit | Твердотопливный котел Greolit DEEP (20 кВт)       |
| 11965 | PS-011.965 | Greolit | Твердотопливный котел Greolit DEEP (25 кВт)       |
| 11966 | PS-011.966 | Greolit | Твердотопливный котел Greolit MASTER (20 кВт)     |
| 11967 | PS-011.967 | Greolit | Твердотопливный котел Greolit MASTER (25 кВт)     |
| 11968 | PS-011.968 | Greolit | Твердотопливный котел Greolit PROFI (50 кВт) NEW! |
| 11969 | PS-011.969 | Greolit | Твердотопливный котел Greolit PROFI (70 кВт) NEW! |
| 11970 | PS-011.970 | Greolit | Твердотопливный котел Greolit PROFI (80 кВт) NEW! |
| 11971 | PS-011.971 | Greolit | Твердотопливный котел Greolit PROFI (95 кВт) NEW! |
| 11972 | PS-011.972 | Greolit | Твердотопливный котел Greolit PROFI (99 кВт) NEW! |
+-------+------------+---------+---------------------------------------------------+

```
