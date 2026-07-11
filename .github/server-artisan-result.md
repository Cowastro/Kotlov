# Server Artisan Result

- Time: 2026-07-11 09:51:33 UTC
- Task: `artisan-dry-run`
- Artisan args: `products:sanitize-content-html --brand=HAIER --not-archived --extract-media --rewrite-seo --rewrite-seo-if-thin --show-samples=5 --limit=40 --sleep=300`
- Log file: `storage/logs/server-artisan-haier-content-dry-run.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   41b5a19..b1061e1  main       -> origin/main
Updating 41b5a19..b1061e1
Fast-forward
 .github/server-artisan-result.md                   | 243 ++++++++++++++++-----
 .github/server-artisan-task.json                   |   6 +-
 .../Commands/SanitizeProductContentHtmlCommand.php |  54 ++++-
 3 files changed, 244 insertions(+), 59 deletions(-)
[1/40] #11786 PS-011.786 Газовая колонка HAIER JSD 20 - 10C
[2/40] #11787 PS-011.787 Газовая колонка HAIER JSD 24 - 12C
[3/40] #11788 PS-011.788 Газовый котёл Haier L1PB20-18RC1
[4/40] #11790 PS-011.790 Газовый котёл Haier L1PB30-28RC1
[5/40] #11791 PS-011.791 Настенный газовый котел Haier ProLine 2.24 Ti
[6/40] #11793 PS-011.793 Электрический водонагреватель HAIER ES30V-A2
[7/40] #11794 PS-011.794 Электрический водонагреватель Haier ES50V-A2
[8/40] #11795 PS-011.795 Электрический водонагреватель Haier ES80V-A2
[9/40] #11796 PS-011.796 Электрический водонагреватель Haier ES100V-A2
[10/40] #11797 PS-011.797 Электрический водонагреватель Haier ES30V-B2 Slim
[11/40] #11798 PS-011.798 Электрический водонагреватель Haier ES50V-B2 Slim
[12/40] #11799 PS-011.799 Электрический водонагреватель Haier ES80V-B2 Slim
[13/40] #11800 PS-011.800 Электрический водонагреватель Haier ES50V-V1 R 
[14/40] #11801 PS-011.801 Электрический водонагреватель Haier ES80V-V1 R 
[15/40] #11802 PS-011.802 Электрический водонагреватель Haier ES100V-V1 R
[16/40] #11803 PS-011.803 Электрический водонагреватель Haier ES50V-F7
[17/40] #11804 PS-011.804 Электрический водонагреватель Haier ES50V-F1 R
[18/40] #11805 PS-011.805 Электрический водонагреватель Haier ES80V-F1(R)
[19/40] #11806 PS-011.806 Электрический водонагреватель Haier ES 100 V-F1 (R)
[20/40] #11807 PS-011.807 Электрический водонагреватель Haier ES50V-Color
[21/40] #11808 PS-011.808 Электрический водонагреватель Haier ES80V-Color
[22/40] #11809 PS-011.809 Электрический водонагреватель Haier FCD-JTHA30-III(ET)
[23/40] #11810 PS-011.810 Электрический водонагреватель Haier FCD-JTHA50-III(ET)
[24/40] #11811 PS-011.811 Электрический водонагреватель Haier FCD-JTHA80-III(ET)
[25/40] #11812 PS-011.812 Электрический водонагреватель Haier ES15V-Q2(R) под раковиной/над раковиной 
[26/40] #11813 PS-011.813 Электрический водонагреватель Haier ES10V-Q2(R) / Q1(R) под раковиной / над р...
[27/40] #11814 PS-011.814 Электрический водонагреватель Haier ES8V-Q2(R) / Q1(R) под раковиной/над рако...
[28/40] #12124 PS-012.124 Водонагреватель Haier ES80V-A1
[29/40] #18991 PS-000.18991 Сплит-система Haier Lightera HSU-12HNF303/R2-G/HSU-12HUN203/R2
[30/40] #18992 PS-000.18992 ТЭН для водонагревателей Haier HE-AB200/300 F
[31/40] #18993 PS-000.18993 Газовый котел Haier ProLine S 2.35 Ti
[32/40] #18994 PS-000.18994 Газовый котел Haier NeoSLIM 2.24 TI
[33/40] #18995 PS-000.18995 Газовый котел Haier NeoSLIM 2.18 TI
[34/40] #18996 PS-000.18996 Газовый котёл Haier ProLine S 2.32 Ti
[35/40] #18997 PS-000.18997 Газовый котёл Haier ProLine S 2.28 Ti
[36/40] #18998 PS-000.18998 Газовый котёл Haier ProLine S 2.18 Ti
[37/40] #18999 PS-000.18999 Газовый котёл Haier EvoLine 2.32 Ti
[38/40] #19000 PS-000.19000 Газовый котёл Haier ProLine 2.28 Ti
[39/40] #19001 PS-000.19001 Газовый котёл Haier TechLine 2.28 Ti
[40/40] #19002 PS-000.19002 Газовый котёл Haier TechLine 2.32 Ti
DRY RUN: database will not be changed.
+------------------------------+-------+
| metric                       | count |
+------------------------------+-------+
| checked                      | 40    |
| changed                      | 38    |
| written                      | 0     |
| images_removed               | 0     |
| styles_removed               | 0     |
| bad_blocks_removed           | 0     |
| legacy_buy_templates_removed | 76    |
| videos_extracted             | 0     |
| documents_extracted          | 0     |
| seo_rewritten                | 0     |
+------------------------------+-------+
+-------+--------------+-------+------------------------------------------------------------------------+
| ID    | SKU          | Brand | Product                                                                |
+-------+--------------+-------+------------------------------------------------------------------------+
| 11786 | PS-011.786   | HAIER | Газовая колонка HAIER JSD 20 - 10C                                     |
| 11787 | PS-011.787   | HAIER | Газовая колонка HAIER JSD 24 - 12C                                     |
| 11788 | PS-011.788   | HAIER | Газовый котёл Haier L1PB20-18RC1                                       |
| 11790 | PS-011.790   | HAIER | Газовый котёл Haier L1PB30-28RC1                                       |
| 11791 | PS-011.791   | HAIER | Настенный газовый котел Haier ProLine 2.24 Ti                          |
| 11793 | PS-011.793   | HAIER | Электрический водонагреватель HAIER ES30V-A2                           |
| 11794 | PS-011.794   | HAIER | Электрический водонагреватель Haier ES50V-A2                           |
| 11795 | PS-011.795   | HAIER | Электрический водонагреватель Haier ES80V-A2                           |
| 11796 | PS-011.796   | HAIER | Электрический водонагреватель Haier ES100V-A2                          |
| 11797 | PS-011.797   | HAIER | Электрический водонагреватель Haier ES30V-B2 Slim                      |
| 11798 | PS-011.798   | HAIER | Электрический водонагреватель Haier ES50V-B2 Slim                      |
| 11799 | PS-011.799   | HAIER | Электрический водонагреватель Haier ES80V-B2 Slim                      |
| 11800 | PS-011.800   | HAIER | Электрический водонагреватель Haier ES50V-V1 R                         |
| 11801 | PS-011.801   | HAIER | Электрический водонагреватель Haier ES80V-V1 R                         |
| 11802 | PS-011.802   | HAIER | Электрический водонагреватель Haier ES100V-V1 R                        |
| 11803 | PS-011.803   | HAIER | Электрический водонагреватель Haier ES50V-F7                           |
| 11804 | PS-011.804   | HAIER | Электрический водонагреватель Haier ES50V-F1 R                         |
| 11806 | PS-011.806   | HAIER | Электрический водонагреватель Haier ES 100 V-F1 (R)                    |
| 11807 | PS-011.807   | HAIER | Электрический водонагреватель Haier ES50V-Color                        |
| 11808 | PS-011.808   | HAIER | Электрический водонагреватель Haier ES80V-Color                        |
| 11809 | PS-011.809   | HAIER | Электрический водонагреватель Haier FCD-JTHA30-III(ET)                 |
| 11810 | PS-011.810   | HAIER | Электрический водонагреватель Haier FCD-JTHA50-III(ET)                 |
| 11811 | PS-011.811   | HAIER | Электрический водонагреватель Haier FCD-JTHA80-III(ET)                 |
| 11812 | PS-011.812   | HAIER | Электрический водонагреватель Haier ES15V-Q2(R) под раковиной/над р... |
| 11813 | PS-011.813   | HAIER | Электрический водонагреватель Haier ES10V-Q2(R) / Q1(R) под раковин... |
| 12124 | PS-012.124   | HAIER | Водонагреватель Haier ES80V-A1                                         |
| 18991 | PS-000.18991 | HAIER | Сплит-система Haier Lightera HSU-12HNF303/R2-G/HSU-12HUN203/R2         |
| 18992 | PS-000.18992 | HAIER | ТЭН для водонагревателей Haier HE-AB200/300 F                          |
| 18993 | PS-000.18993 | HAIER | Газовый котел Haier ProLine S 2.35 Ti                                  |
| 18994 | PS-000.18994 | HAIER | Газовый котел Haier NeoSLIM 2.24 TI                                    |
| 18995 | PS-000.18995 | HAIER | Газовый котел Haier NeoSLIM 2.18 TI                                    |
| 18996 | PS-000.18996 | HAIER | Газовый котёл Haier ProLine S 2.32 Ti                                  |
| 18997 | PS-000.18997 | HAIER | Газовый котёл Haier ProLine S 2.28 Ti                                  |
| 18998 | PS-000.18998 | HAIER | Газовый котёл Haier ProLine S 2.18 Ti                                  |
| 18999 | PS-000.18999 | HAIER | Газовый котёл Haier EvoLine 2.32 Ti                                    |
| 19000 | PS-000.19000 | HAIER | Газовый котёл Haier ProLine 2.28 Ti                                    |
| 19001 | PS-000.19001 | HAIER | Газовый котёл Haier TechLine 2.28 Ti                                   |
| 19002 | PS-000.19002 | HAIER | Газовый котёл Haier TechLine 2.32 Ti                                   |
+-------+--------------+-------+------------------------------------------------------------------------+

```
