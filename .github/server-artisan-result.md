# Server Artisan Result

- Time: 2026-07-09 20:34:50 UTC
- Task: `artisan-apply`
- Artisan args: `supplier:enrich-source-products --apply --supplier=akvatermex --domain=teplodvor.by --products=21205,21244,21272,21301,21306 --force --replace-specs --min-specs-to-replace=4 --overwrite-images --skip-documents --limit=0`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
APPLY: source enrichment will be written.
Products with source URLs: 5 (processing 5, offset 0, --force)
[1/5] #21205 6971170590315 THERMEX IF 100 (smart)
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-thermex-if-100-v-smart
  found: images=4 specs=30 service=5 docs=2 video=1 updated=images,specs,service_info,video_url,content,short_description,meta_description,updated_at
[2/5] #21244 4607084195453 Edisson ER 80
  source: https://www.teplodvor.by/shop/vodonagrevateli/elekricheskie/vodonagrevatel-edisson-er-80-v
  found: images=4 specs=20 service=5 docs=1 video=1 updated=images,specs,service_info,video_url,content,short_description,meta_description,updated_at
[3/5] #21272 4670033310597 THERMEX Frame 1500E
  source: https://www.teplodvor.by/shop/raditory/konvektory/elektrokonvektor-thermex-frame-1500e
  found: images=4 specs=18 service=5 docs=2 video=1 updated=images,specs,service_info,video_url,content,short_description,meta_description,updated_at
[4/5] #21301 ЭдЭБ01237 EUROSTAR E 906
  source: https://www.teplodvor.by/shop/kotly/elektricheskie/elektricheskiy-kotel-thermex-eurostar-e-906
  found: images=4 specs=17 service=5 docs=2 video=1 updated=images,specs,service_info,video_url,content,short_description,meta_description,updated_at
[5/5] #21306 4670033317398 THERMEX Stern 9
  source: https://www.teplodvor.by/shop/kotly/elektricheskie/elektricheskiy-kotel-thermex-stern-4-12-tip-b-9-kv
  found: images=4 specs=18 service=5 docs=1 video=1 updated=images,specs,service_info,video_url,content,short_description,meta_description,updated_at

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 5     |
| enriched         | 5     |
| images_found     | 20    |
| images_saved     | 15    |
| specs_found      | 103   |
| attributes_saved | 103   |
| ai_done          | 5     |
| skipped          | 0     |
| errors           | 0     |
+------------------+-------+

```
