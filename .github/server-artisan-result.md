# Server Artisan Result

- Time: 2026-07-09 16:58:37 UTC
- Task: `tail-log`
- Artisan args: ``
- Log file: `storage/logs/auto-maitek-sten-fill.log`
- Exit code: `0`

```text
APPLY: source enrichment will be written.
Products with source URLs: 3 (processing 3, offset 0, --force)
[1/3] #20754 zaglushka-probka-g1- СТЭН Заглушка свободного патрубка обратки G 1¼
  source: https://sten.ru/catalog/oborudovanie-dlya-kotlov-i-pechey/komplektuyshie/zaglushka-probka-g1-/
  ERROR: HTTP request returned status code 500:
<!DOCTYPE html>
<html xml:lang="ru" lang="ru" xmlns="http://www.w3.org/1999/xhtml" >
<head>
	        <title>Катало (truncated...)

[2/3] #20755 zaglushka-probka-g1-1-2 СТЭН Заглушка свободного патрубка обратки G 1½
  source: https://sten.ru/catalog/oborudovanie-dlya-kotlov-i-pechey/komplektuyshie/zaglushka-probka-g1-1-2/
  ERROR: HTTP request returned status code 500:
<!DOCTYPE html>
<html xml:lang="ru" lang="ru" xmlns="http://www.w3.org/1999/xhtml" >
<head>
	        <title>Катало (truncated...)

[3/3] #20798 elektricheskij-kotel-sten-evpm-12.html СТЭН Котел "СТЭН ЭВПМ 12" 380
  source: https://stenbel.by/katalog/kotlyi-elektricheskie/sten-evpm/elektricheskij-kotel-sten-evpm-12.html
  found: images=0 specs=0 service=0 docs=0 video=0 updated=documents,updated_at

+------------------+-------+
| metric           | count |
+------------------+-------+
| processed        | 3     |
| enriched         | 1     |
| images_found     | 0     |
| images_saved     | 0     |
| specs_found      | 0     |
| attributes_saved | 0     |
| ai_done          | 0     |
| skipped          | 0     |
| errors           | 2     |
+------------------+-------+

```
