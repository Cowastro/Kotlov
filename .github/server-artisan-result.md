# Server Artisan Result

- Time: 2026-07-09 20:19:25 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-source-products --apply --supplier=akvatermex --domain=teplodvor.by --product=21168 --force --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=1`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
APPLY: source enrichment will be written.
Products with source URLs: 1 (processing 1, offset 0, --force)
[1/1] #21168 4670007719562 THERMEX N 15 U
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-n-15-u-pro
  found: images=4 specs=20 service=5 docs=1 video=1 updated=images,specs,service_info,video_url,content,short_description,meta_description,updated_at

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 1     |
| enriched         | 1     |
| images_found     | 4     |
| images_saved     | 4     |
| specs_found      | 20    |
| attributes_saved | 20    |
| ai_done          | 1     |
| skipped          | 0     |
| errors           | 0     |
+------------------+-------+

```
