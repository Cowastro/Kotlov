# Server Artisan Result

- Time: 2026-07-12 08:56:20 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:sync-rn-profi --dry-run --price-file=storage/app/supplier-cache/rn-profi-pricelist.xlsx --brand=Varmega --available-only --max-delivery-days=3 --varmega-official --varmega-refresh-index --varmega-deep-index --varmega-deep-pages=0 --varmega-probe-missing --varmega-probe-limit=0 --only-new-source-url-domain=varmega.ru --sync-retail-prices --limit=0`
- Log file: ``
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   8d9fb6e..f3df15b  main       -> origin/main
Updating 8d9fb6e..f3df15b
Fast-forward
 .github/server-artisan-result.md | 318 ++++++++++++++++++++-------------------
 .github/server-artisan-task.json |   4 +-
 2 files changed, 164 insertions(+), 158 deletions(-)
DRY RUN: database will not be changed.
Brand filter: 938 of 1941 rows selected only=varmega.
Availability filter: 938 of 938 rows selected max_delivery_days=3.
Official Varmega deep index progress: fetched=50, new_matches=1, still_missing=297.
Official Varmega deep index progress: fetched=100, new_matches=1, still_missing=297.
Official Varmega deep index progress: fetched=150, new_matches=1, still_missing=297.
Official Varmega deep index progress: fetched=200, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=250, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=300, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=350, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=400, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=450, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=500, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=550, new_matches=2, still_missing=296.
Official Varmega deep index progress: fetched=600, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=650, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=700, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=750, new_matches=6, still_missing=292.
Official Varmega deep index progress: fetched=800, new_matches=8, still_missing=290.
Official Varmega deep index progress: fetched=850, new_matches=14, still_missing=284.
Official Varmega deep index: fetched=861, new_matches=14, still_missing=284.
Official Varmega index: 7211 article URLs.
Official Varmega URL probes: checked=284 matched=7 skipped=0.
New source URL filter: 520 of 938 rows selected domain=varmega.ru.

Sheets / detected columns:
+---------------------------------+------------+-------------------------------------------+----------+
| sheet                           | header row | columns                                   | raw rows |
+---------------------------------+------------+-------------------------------------------+----------+
| Содержание                      | 11         | name                                      | 1008     |
| 1. Радиаторы секционные БП      | 6          | name, price, retail_price, stock          | 1006     |
| 2. Радиаторы секционные НП      | 2          | name, price, retail_price, article        | 104      |
| 3. Дизайн-радиаторы SHIFT, INSI | 6          | article, name, price, retail_price        | 205      |
| 4. Комплектующие                | 2          | price, retail_price, name, article        | 39       |
| 5. Сталь RT                     | 4          | name, stock, price, retail_price          | 297      |
| 6. Varmega повер.отопление      | 4          | article, name, qty, price, retail_price   | 282      |
| 7. Varmega Slide-fit            | 5          | article, name, qty, price, retail_price   | 292      |
| 8. VARMEGA Inox Press           | 3          | article, name, qty, price, retail_price   | 1028     |
| 9. VARMEGA Радиаторная арматура | 5          | article, name, qty, price, retail_price   | 1170     |
| 10. VARMEGA Арматура            | 5          | article, name, qty, price, retail_price   | 1061     |
| 11. VARMEGA Насосы              | 4          | article, name, qty, price, retail_price   | 1000     |
| 12. Котлы THERMEX               | 7          | name, price, retail_price, stock          | 160      |
| 13. Бойлеры ROYAL THERMO        | 5          | article, name, price, retail_price, stock | 1003     |
| Полотенцесушители               | 4          | name, price, retail_price                 | 1000     |
| Конвекторы ROYAL THERMO         | 3          | article, name, price, retail_price, stock | 13       |
| 14. Конвекторы НОВАТЕРМ         | 3          | brand, retail_price, name, stock          | 1215     |
| 15. Радиаторы ИТАЛИЯ NOVA FLORI | 3          | name, price, retail_price, stock          | 933      |
| Инструмент                      | 1          | brand, article, name, price, retail_price | 1186     |
| 16. Вентиляция                  | 2          | name, price, retail_price                 | 1006     |
+---------------------------------+------------+-------------------------------------------+----------+
RN-Profi audit:
+---------------------------+-------+
| metric                    | count |
+---------------------------+-------+
| parsed rows               | 520   |
| rows with wholesale price | 520   |
| rows with retail price    | 520   |
| matched existing products | 520   |
| new/unmatched candidates  | 0     |
| missing/unknown brands    | 0     |
| missing wholesale price   | 0     |
+---------------------------+-------+
Stock statuses:
+--------------+------+
| stock_status | rows |
+--------------+------+
| in_stock     | 520  |
+--------------+------+
Availability filter:
+-------------------+-------+
| metric            | count |
+-------------------+-------+
| before filter     | 938   |
| after filter      | 938   |
| max delivery days | 3     |
+-------------------+-------+
Official Varmega card matches:
+----------------------------+-------+
| metric                     | count |
+----------------------------+-------+
| matched card URLs          | 520   |
| missing card URLs          | 0     |
| matched by article sitemap | 513   |
+----------------------------+-------+
Official Varmega matches by sheet:
+----------------------------+---------+---------+------+
| sheet                      | matched | missing | rows |
+----------------------------+---------+---------+------+
| 6. Varmega повер.отопление | 173     | 0       | 173  |
| 7. Varmega Slide-fit       | 202     | 0       | 202  |
| 8. VARMEGA Inox Press      | 34      | 0       | 34   |
| 10. VARMEGA Арматура       | 104     | 0       | 104  |
| 11. VARMEGA Насосы         | 7       | 0       | 7    |
+----------------------------+---------+---------+------+
Actions by sheet:
+----------------------------+---------+-----------+---------------+---------------+------+
| sheet                      | matched | unmatched | brand_missing | price_missing | rows |
+----------------------------+---------+-----------+---------------+---------------+------+
| 6. Varmega повер.отопление | 173     | 0         | 0             | 0             | 173  |
| 7. Varmega Slide-fit       | 202     | 0         | 0             | 0             | 202  |
| 8. VARMEGA Inox Press      | 34      | 0         | 0             | 0             | 34   |
| 10. VARMEGA Арматура       | 104     | 0         | 0             | 0             | 104  |
| 11. VARMEGA Насосы         | 7       | 0         | 0             | 0             | 7    |
+----------------------------+---------+-----------+---------------+---------------+------+
Brands in price list:
+---------+------+------------+------------------+
| brand   | rows | in catalog | catalog products |
+---------+------+------------+------------------+
| Varmega | 520  | yes        | 951              |
+---------+------+------------+------------------+
Matched examples:
+------------------+-----+---------+---------+----------------+-----------+--------+----------+---------+---------------+------------------------+----------------------------------------------------------+----------------------------------+
| sheet            | row | article | brand   | name           | wholesale | retail | stock    | action  | matched_sku   | confidence             | varmega                                                  | vm_cat                           |
+------------------+-----+---------+---------+----------------+-----------+--------+----------+---------+---------------+------------------------+----------------------------------------------------------+----------------------------------+
| 6. Varmega повер | 6   | VM30201 | Varmega | 16x2.0         | 1.49      | 1.75   | in_stock | matched | KOTLOV-004935 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 7   | VM30241 | Varmega | 16x2.0         | 1.49      | 1.75   | in_stock | matched | KOTLOV-004936 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 8   | VM30261 | Varmega | 16x2.0         | 1.49      | 1.75   | in_stock | matched | KOTLOV-004937 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 9   | VM30202 | Varmega | 20x2.0         | 1.95      | 2.29   | in_stock | matched | KOTLOV-004938 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 11  | VM30301 | Varmega | 16x2.0         | 2.10      | 2.46   | in_stock | matched | KOTLOV-004939 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 12  | VM30341 | Varmega | 16x2.0         | 2.10      | 2.46   | in_stock | matched | KOTLOV-004940 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 13  | VM30361 | Varmega | 16x2.0         | 2.10      | 2.46   | in_stock | matched | KOTLOV-004941 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 14  | VM30302 | Varmega | 20x2.0         | 2.94      | 3.44   | in_stock | matched | KOTLOV-004942 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 16  | VM30601 | Varmega | 16x2.0         | 2.39      | 2.79   | in_stock | matched | KOTLOV-004943 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 17  | VM30641 | Varmega | 16x2.0         | 2.39      | 2.79   | in_stock | matched | KOTLOV-004944 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 18  | VM30661 | Varmega | 16x2.0         | 2.39      | 2.79   | in_stock | matched | KOTLOV-004945 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 19  | VM30602 | Varmega | 20x2.0         | 3.44      | 4.02   | in_stock | matched | KOTLOV-004946 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 21  | VM30701 | Varmega | 16x2.0         | 2.86      | 3.35   | in_stock | matched | KOTLOV-005453 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 22  | VM30751 | Varmega | 16x2.0         | 2.86      | 3.35   | in_stock | matched | KOTLOV-005454 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 24  | VM15102 | Varmega | 1", 2 x 3/4"EK | 102.63    | 120.07 | in_stock | matched | KOTLOV-005455 | exact_supplier_article | https://varmega.ru/product/kollektory-i-komplektuyushchi | kollektory-i-komplektuyushchie   |
+------------------+-----+---------+---------+----------------+-----------+--------+----------+---------+---------------+------------------------+----------------------------------------------------------+----------------------------------+
Official Varmega card examples:
+------------------+-----+---------+---------+----------------+-----------+--------+----------+---------+---------------+------------------------+----------------------------------------------------------+----------------------------------+
| sheet            | row | article | brand   | name           | wholesale | retail | stock    | action  | matched_sku   | confidence             | varmega                                                  | vm_cat                           |
+------------------+-----+---------+---------+----------------+-----------+--------+----------+---------+---------------+------------------------+----------------------------------------------------------+----------------------------------+
| 6. Varmega повер | 6   | VM30201 | Varmega | 16x2.0         | 1.49      | 1.75   | in_stock | matched | KOTLOV-004935 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 7   | VM30241 | Varmega | 16x2.0         | 1.49      | 1.75   | in_stock | matched | KOTLOV-004936 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 8   | VM30261 | Varmega | 16x2.0         | 1.49      | 1.75   | in_stock | matched | KOTLOV-004937 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 9   | VM30202 | Varmega | 20x2.0         | 1.95      | 2.29   | in_stock | matched | KOTLOV-004938 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 11  | VM30301 | Varmega | 16x2.0         | 2.10      | 2.46   | in_stock | matched | KOTLOV-004939 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 12  | VM30341 | Varmega | 16x2.0         | 2.10      | 2.46   | in_stock | matched | KOTLOV-004940 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 13  | VM30361 | Varmega | 16x2.0         | 2.10      | 2.46   | in_stock | matched | KOTLOV-004941 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 14  | VM30302 | Varmega | 20x2.0         | 2.94      | 3.44   | in_stock | matched | KOTLOV-004942 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 16  | VM30601 | Varmega | 16x2.0         | 2.39      | 2.79   | in_stock | matched | KOTLOV-004943 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 17  | VM30641 | Varmega | 16x2.0         | 2.39      | 2.79   | in_stock | matched | KOTLOV-004944 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 18  | VM30661 | Varmega | 16x2.0         | 2.39      | 2.79   | in_stock | matched | KOTLOV-004945 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 19  | VM30602 | Varmega | 20x2.0         | 3.44      | 4.02   | in_stock | matched | KOTLOV-004946 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 21  | VM30701 | Varmega | 16x2.0         | 2.86      | 3.35   | in_stock | matched | KOTLOV-005453 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 22  | VM30751 | Varmega | 16x2.0         | 2.86      | 3.35   | in_stock | matched | KOTLOV-005454 | exact_supplier_article | https://varmega.ru/product/truby-i-fitingi/metalloplasti | truby-i-fitingi/metalloplastikov |
| 6. Varmega повер | 24  | VM15102 | Varmega | 1", 2 x 3/4"EK | 102.63    | 120.07 | in_stock | matched | KOTLOV-005455 | exact_supplier_article | https://varmega.ru/product/kollektory-i-komplektuyushchi | kollektory-i-komplektuyushchie   |
| 6. Varmega повер | 25  | VM15103 | Varmega | 1", 3 x 3/4"EK | 136.58    | 159.80 | in_stock | matched | KOTLOV-005456 | exact_supplier_article | https://varmega.ru/product/kollektory-i-komplektuyushchi | kollektory-i-komplektuyushchie   |
| 6. Varmega повер | 26  | VM15104 | Varmega | 1", 4 x 3/4"EK | 171.30    | 200.42 | in_stock | matched | KOTLOV-005457 | exact_supplier_article | https://varmega.ru/product/kollektory-i-komplektuyushchi | kollektory-i-komplektuyushchie   |
| 6. Varmega повер | 27  | VM15105 | Varmega | 1", 5 x 3/4"EK | 206.10    | 241.14 | in_stock | matched | KOTLOV-005458 | exact_supplier_article | https://varmega.ru/product/kollektory-i-komplektuyushchi | kollektory-i-komplektuyushchie   |
| 6. Varmega повер | 28  | VM15106 | Varmega | 1", 6 x 3/4"EK | 240.88    | 281.83 | in_stock | matched | KOTLOV-005459 | exact_supplier_article | https://varmega.ru/product/kollektory-i-komplektuyushchi | kollektory-i-komplektuyushchie   |
| 6. Varmega повер | 29  | VM15107 | Varmega | 1", 7 x 3/4"EK | 274.21    | 320.83 | in_stock | matched | KOTLOV-005460 | exact_supplier_article | https://varmega.ru/product/kollektory-i-komplektuyushchi | kollektory-i-komplektuyushchie   |
+------------------+-----+---------+---------+----------------+-----------+--------+----------+---------+---------------+------------------------+----------------------------------------------------------+----------------------------------+

Next: run with --apply only after checking detected columns and matches.

```
