# Server Artisan Result

- Time: 2026-07-09 20:00:24 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:audit-content-health --supplier=akvatermex --active-only --not-archived --with-source-only --max-attrs=4 --limit=80`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
Products with content-health issues: 45
Showing rows: 45 (limit 80)

By issue
+------------+----------+
| Issue      | Products |
+------------+----------+
| no_photo   | 45       |
| no_content | 0        |
| no_short   | 0        |
| low_attrs  | 45       |
| no_source  | 0        |
+------------+----------+
By supplier
+------------+----------+----------+------------+-----------+
| Name       | Products | No photo | No content | Low attrs |
+------------+----------+----------+------------+-----------+
| akvatermex | 45       | 45       | 0          | 45        |
+------------+----------+----------+------------+-----------+
By brand
+----------+----------+----------+------------+-----------+
| Name     | Products | No photo | No content | Low attrs |
+----------+----------+----------+------------+-----------+
| Thermex  | 40       | 40       | 0          | 40        |
| Edisson  | 4        | 4        | 0          | 4         |
| Eurostar | 1        | 1        | 0          | 1         |
+----------+----------+----------+------------+-----------+
By category
+---------------+----------+----------+------------+-----------+
| Name          | Products | No photo | No content | Low attrs |
+---------------+----------+----------+------------+-----------+
| Электрические | 45       | 45       | 0          | 45        |
+---------------+----------+----------+------------+-----------+

+-------+---------------+----------+---------------+------------+-------+--------------------+------------------+----------------------------------+
| ID    | SKU           | Brand    | Category      | Suppliers  | Attrs | Issues             | Source domains   | Product                          |
+-------+---------------+----------+---------------+------------+-------+--------------------+------------------+----------------------------------+
| 21244 | KOTLOV-006410 | Edisson  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Edisson ER 80                    |
| 21290 | KOTLOV-006456 | Edisson  | Электрические | akvatermex | 0     | no_photo,low_attrs | www.teplodvor.by | EDISSON H 20 D                   |
| 21295 | KOTLOV-006461 | Edisson  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | EDISSON E 20 GD (Каннская ветвь) |
| 21297 | KOTLOV-006463 | Edisson  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | EDISSON E 20 GD (Подсолнухи)     |
| 21301 | KOTLOV-006467 | Eurostar | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | EUROSTAR E 906                   |
| 21165 | KOTLOV-006331 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | www.teplodvor.by | THERMEX N 10 O                   |
| 21166 | KOTLOV-006332 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | www.teplodvor.by | THERMEX N 10 U                   |
| 21167 | KOTLOV-006333 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | www.teplodvor.by | THERMEX N 15 O                   |
| 21168 | KOTLOV-006334 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | www.teplodvor.by | THERMEX N 15 U                   |
| 21179 | KOTLOV-006345 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Thermex ER 50 V                  |
| 21180 | KOTLOV-006346 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Thermex ER 80 V                  |
| 21181 | KOTLOV-006347 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Thermex ER 100 V                 |
| 21182 | KOTLOV-006348 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Thermex Thermo 30V Slim          |
| 21183 | KOTLOV-006349 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Thermex Thermo 50V Slim          |
| 21205 | KOTLOV-006371 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX IF 100 (smart)           |
| 21247 | KOTLOV-006413 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Amber 3000               |
| 21248 | KOTLOV-006414 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Nix 3000                 |
| 21249 | KOTLOV-006415 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Thermex Runa 3000                |
| 21255 | KOTLOV-006421 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Vetro 6500 combi         |
| 21256 | KOTLOV-006422 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Rind 5500 combi          |
| 21257 | KOTLOV-006423 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | hermex.by        | THERMEX Rind 6500 combi          |
| 21272 | KOTLOV-006438 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Frame 1500E              |
| 21283 | KOTLOV-006449 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Greta 1200               |
| 21288 | KOTLOV-006454 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX V 1.5                    |
| 21306 | KOTLOV-006472 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Stern 9                  |
| 21307 | KOTLOV-006473 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Libert 9                 |
| 21312 | KOTLOV-006478 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Термос THERMEX 2Go 480I          |
| 21313 | KOTLOV-006479 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Herz                     |
| 21316 | KOTLOV-006482 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Youth                    |
| 21321 | KOTLOV-006487 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Mira                     |
| 21322 | KOTLOV-006488 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Фильтр THERMEX ION SL 5"         |
| 21323 | KOTLOV-006489 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | Фильтр THERMEX ION SL 10"        |
| 21324 | KOTLOV-006490 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Parma 12                 |
| 21325 | KOTLOV-006491 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Parma 18                 |
| 21326 | KOTLOV-006492 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Parma 24                 |
| 21327 | KOTLOV-006493 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Finch 9                  |
| 21328 | KOTLOV-006494 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Finch 12                 |
| 21329 | KOTLOV-006495 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Finch 18                 |
| 21330 | KOTLOV-006496 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Garda 7                  |
| 21331 | KOTLOV-006497 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Garda 9                  |
| 21332 | KOTLOV-006498 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Garda 12                 |
| 21333 | KOTLOV-006499 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Sesto 7                  |
| 21334 | KOTLOV-006500 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Sesto 9                  |
| 21335 | KOTLOV-006501 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Sesto 12                 |
| 21336 | KOTLOV-006502 | Thermex  | Электрические | akvatermex | 0     | no_photo,low_attrs | thermex.by       | THERMEX Sesto 18                 |
+-------+---------------+----------+---------------+------------+-------+--------------------+------------------+----------------------------------+

```
