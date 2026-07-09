# Server Artisan Result

- Time: 2026-07-09 19:54:45 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:sync-akvatermex --available-only --only-linked --prefer-teplodvor-source --brand=Thermex,Edisson,Eurostar --limit=80`
- Log file: `storage/logs/server-artisan.log`
- Exit code: `0`

```text
DRY RUN: Akvatermex price list will preview only.
Downloading Akvatermex Google Sheet: https://docs.google.com/spreadsheets/d/19G0Mei9zkr8iFzTJeYKFHd4IYeIfJwH_7dDAYhTp5jk/export?format=xlsx
+-----------------+-------+
| metric          | count |
+-----------------+-------+
| parsed rows     | 80    |
| in stock        | 80    |
| with source url | 80    |
| with EAN        | 75    |
+-----------------+-------+
Actions:
+---------------+-------+
| action        | count |
+---------------+-------+
| matched       | 63    |
| skip_unlinked | 17    |
+---------------+-------+
Brands:
+---------+-------+
| brand   | count |
+---------+-------+
| Thermex | 80    |
+---------+-------+
Match confidence:
+------------------+-------+
| confidence       | count |
+------------------+-------+
| supplier_article | 63    |
+------------------+-------+
Examples:
+------------------------+-----+---------+---------------+---------------+----------------------+--------+--------+----------+--------+---------------+-------+
| sheet                  | row | brand   | category      | article       | name                 | opt    | retail | stock    | source | action        | match |
+------------------------+-----+---------+---------------+---------------+----------------------+--------+--------+----------+--------+---------------+-------+
| Full_pricelist ОСНОВНО | 6   | Thermex | Электрические | 4670007715830 | THERMEX IC 10 U      | 244.8  | 306    | in_stock | yes    | matched       | 17697 |
| Full_pricelist ОСНОВНО | 7   | Thermex | Электрические | 4670007715847 | THERMEX IC 10 O      | 244.8  | 306    | in_stock | yes    | skip_unlinked | -     |
| Full_pricelist ОСНОВНО | 8   | Thermex | Электрические | 4670007715854 | THERMEX IC 15 O      | 268.8  | 336    | in_stock | yes    | matched       | 17698 |
| Full_pricelist ОСНОВНО | 9   | Thermex | Электрические | 4670007715861 | THERMEX IC 15 U      | 268.8  | 336    | in_stock | yes    | matched       | 17696 |
| Full_pricelist ОСНОВНО | 11  | Thermex | Электрические | 4670007712006 | Thermex IBL 10 O     | 288    | 360    | in_stock | yes    | matched       | 17865 |
| Full_pricelist ОСНОВНО | 12  | Thermex | Электрические | 4670007718084 | Thermex IBL 10 U     | 288    | 360    | in_stock | yes    | matched       | 17862 |
| Full_pricelist ОСНОВНО | 13  | Thermex | Электрические | 4670007718091 | Thermex IBL 15 U     | 307.2  | 384    | in_stock | yes    | matched       | 17864 |
| Full_pricelist ОСНОВНО | 16  | Thermex | Электрические | 4670033315875 | Thermex Н 5 O (pro)  | 217.92 | 272.4  | in_stock | yes    | skip_unlinked | -     |
| Full_pricelist ОСНОВНО | 17  | Thermex | Электрические | 4670033315882 | Thermex Н 5 U (pro)  | 217.92 | 272.4  | in_stock | yes    | skip_unlinked | -     |
| Full_pricelist ОСНОВНО | 18  | Thermex | Электрические | 4670007714680 | Thermex Н 10 O (pro) | 230.4  | 288    | in_stock | yes    | skip_unlinked | -     |
| Full_pricelist ОСНОВНО | 19  | Thermex | Электрические | 4670007714697 | Thermex Н 10 U (pro) | 230.4  | 288    | in_stock | yes    | skip_unlinked | -     |
| Full_pricelist ОСНОВНО | 20  | Thermex | Электрические | 4670007714703 | Thermex Н 15 O (pro) | 259.2  | 324    | in_stock | yes    | skip_unlinked | -     |
| Full_pricelist ОСНОВНО | 21  | Thermex | Электрические | 4670007714710 | Thermex Н 15 U (pro) | 259.2  | 324    | in_stock | yes    | skip_unlinked | -     |
| Full_pricelist ОСНОВНО | 22  | Thermex | Электрические | 4670007714727 | Thermex Н 30 O (pro) | 345.6  | 432    | in_stock | yes    | matched       | 17749 |
| Full_pricelist ОСНОВНО | 25  | Thermex | Электрические | 4670007717674 | THERMEX N 10 O       | 230.4  | 288    | in_stock | yes    | matched       | 21165 |
| Full_pricelist ОСНОВНО | 26  | Thermex | Электрические | 4670007719555 | THERMEX N 10 U       | 230.4  | 288    | in_stock | yes    | matched       | 21166 |
| Full_pricelist ОСНОВНО | 27  | Thermex | Электрические | 4670007717681 | THERMEX N 15 O       | 259.2  | 324    | in_stock | yes    | matched       | 21167 |
| Full_pricelist ОСНОВНО | 28  | Thermex | Электрические | 4670007719562 | THERMEX N 15 U       | 259.2  | 324    | in_stock | yes    | matched       | 21168 |
| Full_pricelist ОСНОВНО | 30  | Thermex | Электрические | 151 241       | THERMEX Mera 7 O     | 240    | 300    | in_stock | yes    | matched       | 17714 |
| Full_pricelist ОСНОВНО | 31  | Thermex | Электрические | 151 242       | THERMEX Mera 7 U     | 240    | 300    | in_stock | yes    | matched       | 17884 |
| Full_pricelist ОСНОВНО | 32  | Thermex | Электрические | 151 243       | THERMEX Mera 10 O    | 259.2  | 324    | in_stock | yes    | matched       | 17719 |
| Full_pricelist ОСНОВНО | 33  | Thermex | Электрические | 151 244       | THERMEX Mera 10 U    | 259.2  | 324    | in_stock | yes    | matched       | 17718 |
| Full_pricelist ОСНОВНО | 34  | Thermex | Электрические | 151 245       | THERMEX Mera 15 O    | 278.4  | 348    | in_stock | yes    | matched       | 17717 |
| Full_pricelist ОСНОВНО | 37  | Thermex | Электрические | 4670033316032 | THERMEX Day 7 O      | 220.8  | 276    | in_stock | yes    | matched       | 21169 |
+------------------------+-----+---------+---------------+---------------+----------------------+--------+--------+----------+--------+---------------+-------+
Next: run with --apply to update matched rows. Add --create-new only after reviewing create_candidate rows.

```
