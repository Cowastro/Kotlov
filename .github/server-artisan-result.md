# Server Artisan Result

- Time: 2026-07-10 16:07:31 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --brand=Candy --active-only --not-archived --rewrite-seo --limit=0 --sleep=500`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
[1/8] #20859 KOTLOV-006025 Candy водонагреватель CS15V-EM2(R)
[2/8] #20860 KOTLOV-006026 Candy водонагреватель CR30V-B2SL(R)
[3/8] #20861 KOTLOV-006027 Candy водонагреватель CR50V-B2SL(R)
[4/8] #20862 KOTLOV-006028 Candy водонагреватель CR80V-B2SL(R)
[5/8] #20863 KOTLOV-006029 Candy водонагреватель CF50V-P3(R)
[6/8] #20864 KOTLOV-006030 Candy водонагреватель CF80V-P3(R)
[7/8] #20865 KOTLOV-006031 Candy водонагреватель CF50V-P5(R)
[8/8] #20866 KOTLOV-006032 Candy водонагреватель CF80V-P5(R)
APPLY: sanitized content was written.
+---------------------+-------+
| metric              | count |
+---------------------+-------+
| checked             | 8     |
| changed             | 8     |
| written             | 8     |
| images_removed      | 0     |
| styles_removed      | 0     |
| bad_blocks_removed  | 0     |
| videos_extracted    | 0     |
| documents_extracted | 0     |
| seo_rewritten       | 8     |
+---------------------+-------+
+-------+---------------+-------+-------------------------------------+
| ID    | SKU           | Brand | Product                             |
+-------+---------------+-------+-------------------------------------+
| 20859 | KOTLOV-006025 | Candy | Candy водонагреватель CS15V-EM2(R)  |
| 20860 | KOTLOV-006026 | Candy | Candy водонагреватель CR30V-B2SL(R) |
| 20861 | KOTLOV-006027 | Candy | Candy водонагреватель CR50V-B2SL(R) |
| 20862 | KOTLOV-006028 | Candy | Candy водонагреватель CR80V-B2SL(R) |
| 20863 | KOTLOV-006029 | Candy | Candy водонагреватель CF50V-P3(R)   |
| 20864 | KOTLOV-006030 | Candy | Candy водонагреватель CF80V-P3(R)   |
| 20865 | KOTLOV-006031 | Candy | Candy водонагреватель CF50V-P5(R)   |
| 20866 | KOTLOV-006032 | Candy | Candy водонагреватель CF80V-P5(R)   |
+-------+---------------+-------+-------------------------------------+

```
