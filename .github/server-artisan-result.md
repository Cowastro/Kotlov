# Server Artisan Result

- Time: 2026-08-26 18:06:54 UTC
- Task: `tail-log`
- Artisan args: ``
- Log file: `storage/logs/restore-remaining-2.log`
- Exit code: `0`

```text
No local changes to save
From https://github.com/Cowastro/Kotlov
   6860da5f..5b1b9f3f  main       -> origin/main
Updating 6860da5f..5b1b9f3f
Fast-forward
 .github/server-artisan-result.md         | 23 ++++++++++-------------
 .github/server-artisan-task.json         |  6 +++---
 public/assets/css/kotlov.css             | 24 ++++++++++++++++++++++++
 resources/views/pages/home-new.blade.php |  2 +-
 4 files changed, 38 insertions(+), 17 deletions(-)
[38/92] TA800L
[39/92] TA800LB
[40/92] TA800RB
[41/92] TA800
[42/92] TA800R
[43/92] TDN900P
[44/92] TDN900PB
[45/92] TDN700P
[46/92] TDN700PB
[47/92] TBN1600P
[48/92] TDN900B
[49/92] TBN1000T
[50/92] TDN900
[51/92] TBN800TB
[52/92] TBN800T
[53/92] TDN800RP
[54/92] TDNP1000R
[55/92] TDNP1000L
[56/92] TDN800RPB
[57/92] TDNP1000LB
[58/92] TDN800LP
[59/92] TDN800LPB
[60/92] TDNP1000RB
[61/92] TDN800L
[62/92] TDN800R
[63/92] TDN800P
[64/92] TBN1600PB
[65/92] TDN800LB
[66/92] TDN800RB
[67/92] TDN800РB
[68/92] TDN800B
[69/92] TDN1200
[70/92] TBN1000TB
[71/92] TDN800
[72/92] TDN1200B
[73/92] TA700-1K
[74/92] TA700-1KB
[75/92] TA1000R
[76/92] TA1000L
[77/92] TA1000LB
[78/92] TAN700-1B
[79/92] TAN700LB
[80/92] TAN700RB
[81/92] TA1000
[82/92] TAN700R
[83/92] TAN700L
[84/92] TAN700-1
[85/92] TA1000B
[86/92] TA1000RB
[87/92] TO700
[88/92] TP700
[89/92] TDN800V
[90/92] TDN800PV
[91/92] TDN800BV
[92/92] TDN800PВV
+-----------------+-------+
| metric          | count |
+-----------------+-------+
| created         | 6     |
| updated         | 86    |
| attributes      | 140   |
| images          | 36    |
| skipped_invicta | 0     |
| errors          | 0     |
+-----------------+-------+
Exit code: 0

=== Running: supplier:sync-ecokamin-stoves --apply ===
APPLY: database will be updated.
Supplier currency: RUB, rate to BYN: 0.039
  Раздел: https://ecokamin.ru/catalog/pechi_kaminy/bavariya/
  Раздел: https://ecokamin.ru/catalog/kaminy/
Found stoves: 35 (skipped Invicta: 0)
[1/35] PK004
[2/35] PK168M
[3/35] PK166M
[4/35] PK049
[5/35] PK123
[6/35] PK007
[7/35] PK165M
[8/35] PK147
[9/35] PK187
[10/35] PK145
[11/35] PK138
[12/35] PK179
[13/35] РК193
[14/35] PK189
[15/35] PK186
[16/35] K218
[17/35] K186B
[18/35] K186
[19/35] KM211B
[20/35] KM203
[21/35] KTS201
[22/35] KTS200
[23/35] KM212CB
[24/35] KM212C
[25/35] KPN202B
[26/35] KPN202
[27/35] K185
[28/35] KP197RB
[29/35] KP195LB
[30/35] KP196R
[31/35] KP194L
[32/35] KM210LB
[33/35] KM205L
[34/35] KRN800BK
[35/35] KRN800BG
+-----------------+-------+
| metric          | count |
+-----------------+-------+
| created         | 0     |
| updated         | 35    |
| attributes      | 0     |
| images          | 0     |
| skipped_invicta | 0     |
| errors          | 0     |
+-----------------+-------+
Exit code: 0

=== Running: supplier:sync-elicon-gas-meters --apply ===
APPLY: database will be updated.
Listing scrape failed: file_get_contents(https://elicon.by/product-category/bitovie_schetchiki_gaza/page/4/): Failed to open stream: HTTP request failed! HTTP/1.1 404 Not Found

Exit code: 1

=== Running: supplier:sync-gorodkotlov-vaillant --apply ===
APPLY: database will be updated.
Found 16 unique Vaillant products on gorodkotlov.by.
[1/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbotec-pro-vuw-242-5-3/
[2/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbotec-plus-vu-242-5-5-/
[3/16] https://gorodkotlov.by/catalog/gazovye-kotly/kondensatsionnyy-gazovyy-kotel-vaillant-ecotec-plus-vu-35-cs-1-5/
[4/16] https://gorodkotlov.by/catalog/gazovye-kotly/kondensatsionnyy-gazovyy-kotel-vaillant-ecotec-plus-vu-30-cs-1-5/
[5/16] https://gorodkotlov.by/catalog/gazovye-kotly/kondensatsionnyy-gazovyy-kotel-vaillant-ecotec-plus-vu-25-cs-1-5/
[6/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbotec-plus-vu-282-5-5/
[7/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbotec-plus-vu-362-5-5/
[8/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbofit-vuw-242-5-2/
[9/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbotec-plus-vuw-282-5-5/
[10/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbotec-plus-vuw-242-5-5/
[11/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-atmotec-plus-vuw-280-5-5/
[12/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-atmotec-plus-vuw-240-5-5/
[13/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-atmotec-plus-vu-240_5_5/
[14/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-atmotec-pro-vuw-280-5-3-/
[15/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy_kotel_vaillant_atmotec_pro_vuw_240_5_3_/
[16/16] https://gorodkotlov.by/catalog/gazovye-kotly/gazovyy-kotel-vaillant-turbotec-pro-vuw-282-5-3/
+-------------+-------+
| action      | count |
+-------------+-------+
| created     | 0     |
| updated     | 4     |
| no_change   | 12    |
| seo         | 0     |
| documents   | 28    |
| promo_flags | 32    |
| skipped     | 0     |
| errors      | 0     |
+-------------+-------+
Exit code: 0

Done restoring remaining supplier images.

```
