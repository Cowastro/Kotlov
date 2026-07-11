# Server Artisan Result

- Time: 2026-07-11 10:10:58 UTC
- Task: `artisan-apply`
- Artisan args: `products:sanitize-content-html --apply --brand=royal-thermo --not-archived --extract-media --show-samples=5 --limit=0`
- Log file: `storage/logs/server-artisan-royal-thermo-content-apply.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   c376789..bcedf46  main       -> origin/main
Updating c376789..bcedf46
Fast-forward
 .github/server-artisan-result.md | 306 +++++++++++++++------------------------
 .github/server-artisan-task.json |   8 +-
 2 files changed, 119 insertions(+), 195 deletions(-)
APPLY: sanitized content was written.
+------------------------------+-------+
| metric                       | count |
+------------------------------+-------+
| checked                      | 428   |
| changed                      | 353   |
| written                      | 353   |
| images_removed               | 0     |
| styles_removed               | 0     |
| bad_blocks_removed           | 0     |
| legacy_buy_templates_removed | 706   |
| videos_extracted             | 0     |
| documents_extracted          | 0     |
| seo_rewritten                | 0     |
+------------------------------+-------+
+-------+---------------+--------------+------------------------------------------------------------------------+
| ID    | SKU           | Brand        | Product                                                                |
+-------+---------------+--------------+------------------------------------------------------------------------+
| 2546  | PS-002.546    | Royal Thermo | Радиатор Royal Thermo Revolution 350                                   |
| 2551  | PS-002.551    | Royal Thermo | Радиатор Royal Thermo BiLiner Inox 500                                 |
| 2552  | PS-002.552    | Royal Thermo | Радиатор Royal Thermo DreamLiner 500                                   |
| 2554  | PS-002.554    | Royal Thermo | Радиатор Royal Thermo Revolution Bimetall 500                          |
| 2555  | PS-002.555    | Royal Thermo | Радиатор Royal Thermo Revolution Bimetall 350                          |
| 3818  | PS-003.818    | Royal Thermo | Присоединительный набор Royal Thermo (для радиатора) 1/2               |
| 3819  | PS-003.819    | Royal Thermo | Крепление для радиатора (кронштейн угловой)                            |
| 4106  | PS-004.106    | Royal Thermo | Присоединительный набор Royal Thermo (для радиатора) 3/4               |
| 4612  | PS-004.612    | Royal Thermo | Крепление для радиатора с дюбелем (пара)                               |
| 7049  | PS-007.049    | Royal Thermo | Радиатор Royal Thermo PianoForte 500 Silver Satin                      |
| 9181  | PS-009.181    | Royal Thermo | Радиатор Royal Thermo Piano Forte Tower 500 (18 секций)                |
| 9182  | PS-009.182    | Royal Thermo | Радиатор Royal Thermo Piano Forte Tower 500 (22 секции)                |
| 9183  | PS-009.183    | Royal Thermo | Радиатор Royal Thermo Indigo 500                                       |
| 9865  | PS-009.865    | Royal Thermo | Радиатор Royal Thermo Monoblock B 100 500                              |
| 10220 | PS-010.220    | Royal Thermo | Бойлер косвенного нагрева Royal Thermo AQUATEC INOX-F 80 литров        |
| 10221 | PS-010.221    | Royal Thermo | Бойлер косвенного нагрева Royal Thermo AQUATEC INOX-F 100 настенный    |
| 10831 | PS-010.831    | Royal Thermo | Электрокамин Royal Flame Astra 50 RF                                   |
| 10889 | PS-010.889    | Royal Thermo | Электрокамин Royal Flame Vision 30 EF LED FX                           |
| 12273 | PS-012.273    | Royal Thermo | Бойлер косвенного нагрева Royal Thermo AQUATEC INOX RTWX-F 100.1 на... |
| 12274 | PS-012.274    | Royal Thermo | Бойлер косвенного нагрева Royal Thermo AQUATEC INOX RTWX-F 100.1 GR... |
| 12275 | PS-012.275    | Royal Thermo | Бойлер косвенного нагрева Royal Thermo AQUATEC INOX RTWX 100 напольный |
| 12729 | KOTLOV-000451 | Royal Thermo | Royal Thermo RTSI-07HN8                                                |
| 12732 | KOTLOV-000454 | Royal Thermo | Royal Thermo RTSI-18HN8                                                |
| 12754 | KOTLOV-000476 | Royal Thermo | Royal Thermo RTB-24HN1_V2                                              |
| 13041 | KOTLOV-000763 | Royal Thermo | Royal Thermo RCHC/M-500                                                |
| 13045 | KOTLOV-000767 | Royal Thermo | Royal Thermo RCHC/M-1500                                               |
| 13046 | KOTLOV-000768 | Royal Thermo | Royal Thermo RCHC/M-1002                                               |
| 13047 | KOTLOV-000769 | Royal Thermo | Royal Thermo RCHC/M-1000                                               |
| 13051 | KOTLOV-000773 | Royal Thermo | Royal Thermo RCHC/E-2000                                               |
| 13052 | KOTLOV-000774 | Royal Thermo | Royal Thermo RCHC/E-1502                                               |
| 13053 | KOTLOV-000775 | Royal Thermo | Royal Thermo RCHC/E-1500                                               |
| 13108 | KOTLOV-000830 | Royal Thermo | Радиатор биметаллический Royal Thermo Indigo B 500 - 4 секц.           |
| 13110 | KOTLOV-000832 | Royal Thermo | Радиатор биметаллический Royal Thermo Indigo B 500 - 8 секц.           |
| 13111 | KOTLOV-000833 | Royal Thermo | Радиатор биметаллический Royal Thermo Indigo B 500 - 10 секц.          |
| 13337 | KOTLOV-001059 | Royal Thermo | Royal Thermo Allira RTFP/W-AL40LS                                      |
| 13345 | KOTLOV-001067 | Royal Thermo | Очаг паровой электрический Royal Thermo RTFP/P1000M 3D Cassette Mys... |
| 13673 | KOTLOV-001395 | Royal Thermo | Радиатор алюминиевый Royal Thermo MONOBLOCK A 500 – 4 секц.            |
| 13676 | KOTLOV-001398 | Royal Thermo | Радиатор алюминиевый Royal Thermo MONOBLOCK A 500 – 10 секц.           |
| 13686 | KOTLOV-001408 | Royal Thermo | Радиатор алюминиевый Royal Thermo Indigo A 500 - 10 секц.              |
| 13696 | KOTLOV-001418 | Royal Thermo | Радиатор биметаллический Royal Thermo MONOBLOCK B 2.0 500 - 10 секц.   |
| 13699 | KOTLOV-001421 | Royal Thermo | Радиатор биметаллический Royal Thermo MONOBLOCK B 500 - 6 секц.        |
| 13701 | KOTLOV-001423 | Royal Thermo | Радиатор биметаллический Royal Thermo MONOBLOCK B 500 - 10 секц.       |
| 13706 | KOTLOV-001428 | Royal Thermo | Радиатор Royal Thermo BiLiner 500 /Bianco Traffico - 10 секц.          |
| 13707 | KOTLOV-001429 | Royal Thermo | Радиатор Royal Thermo BiLiner 500 /Bianco Traffico - 12 секц.          |
| 13712 | KOTLOV-001434 | Royal Thermo | Радиатор Royal Thermo BiLiner 500 /Silver Satin - 12 секц.             |
| 13713 | KOTLOV-001435 | Royal Thermo | Радиатор Royal Thermo BiLiner 500 /Noir Sable - 4 секц.                |
| 13719 | KOTLOV-001441 | Royal Thermo | Радиатор биметаллический Royal Thermo PianoForte 500 Белый - 6 секц.   |
| 13720 | KOTLOV-001442 | Royal Thermo | Радиатор биметаллический Royal Thermo PianoForte 500 Белый - 8 секц.   |
| 13722 | KOTLOV-001444 | Royal Thermo | Радиатор биметаллический Royal Thermo PianoForte 500 Белый - 12 секц.  |
| 13724 | KOTLOV-001446 | Royal Thermo | Радиатор биметаллический Royal Thermo PianoForte 500 Чёрный - 6 секц.  |
| 13728 | KOTLOV-001450 | Royal Thermo | Радиатор биметаллический Royal Thermo PianoForte 500 Серебристый - ... |
| 13730 | KOTLOV-001452 | Royal Thermo | Радиатор биметаллический Royal Thermo PianoForte 500 Серебристый - ... |
| 13731 | KOTLOV-001453 | Royal Thermo | Радиатор биметаллический Royal Thermo PianoForte 500 Серебристый - ... |
| 13737 | KOTLOV-001459 | Royal Thermo | Радиатор Royal Thermo PianoForte 500 Bianco Traffico VDR80 - 12 секц.  |
| 13755 | KOTLOV-001477 | Royal Thermo | Радиатор Royal Thermo PianoForte 300 Silver Satin VDR80 - 12 секц.     |
| 13760 | KOTLOV-001482 | Royal Thermo | Радиатор Royal Thermo PianoForte 300 Noir Sable VDR80 - 12 секц.       |
| 13763 | KOTLOV-001485 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 200 /Bianco Traffico - 18 секц. |
| 13764 | KOTLOV-001486 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 200 /Bianco Traffico - 22 секц. |
| 13765 | KOTLOV-001487 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 200 /Noir Sable - 18 секц.      |
| 13766 | KOTLOV-001488 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 200 /Noir Sable - 22 секц.      |
| 13767 | KOTLOV-001489 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 200 /Silver Satin - 18 секц.    |
| 13768 | KOTLOV-001490 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 200 /Silver Satin - 22 секц.    |
| 13769 | KOTLOV-001491 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 300 /Bianco Traffico - 18 секц. |
| 13771 | KOTLOV-001493 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 300 /Noir Sable - 18 секц.      |
| 13772 | KOTLOV-001494 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 300 /Noir Sable - 22 секц.      |
| 13773 | KOTLOV-001495 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 300 /Silver Satin - 18 секц.    |
| 13774 | KOTLOV-001496 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 300 /Silver Satin - 22 секц.    |
| 13775 | KOTLOV-001497 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 500 new/Bianco Traffico - 18... |
| 13777 | KOTLOV-001499 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 500 new/Silver Satin - 18 секц. |
| 13779 | KOTLOV-001501 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 500 new/Noir Sable - 22 секц.   |
| 13780 | KOTLOV-001502 | Royal Thermo | Радиатор Royal Thermo PianoForte Tower 500 new/Silver Satin - 22 секц. |
| 13781 | KOTLOV-001503 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 500 Bianco Traffico ... |
| 13785 | KOTLOV-001507 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 500 Bianco Traffico ... |
| 13789 | KOTLOV-001511 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 500 Noir Sable - 10 ... |
| 13791 | KOTLOV-001513 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 500 Silver Satin - 4... |
| 13792 | KOTLOV-001514 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 500 Silver Satin - 6... |
| 13795 | KOTLOV-001517 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 500 Silver Satin - 1... |
| 13798 | KOTLOV-001520 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 300 Bianco Traffico ... |
| 13799 | KOTLOV-001521 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 300 Bianco Traffico ... |
| 13801 | KOTLOV-001523 | Royal Thermo | Радиатор биметаллический Royal Thermo Infinity 300 Noir Sable - 6 с... |
+-------+---------------+--------------+------------------------------------------------------------------------+

```
