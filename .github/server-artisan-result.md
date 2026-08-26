# Server Artisan Result

- Time: 2026-08-26 17:31:57 UTC
- Task: `tail-log`
- Artisan args: ``
- Log file: `storage/logs/restore-brand-logos.log`
- Exit code: `0`

```text
No local changes to save
From https://github.com/Cowastro/Kotlov
   fb85e6ec..082463b9  main       -> origin/main
Updating fb85e6ec..082463b9
Fast-forward
 .github/server-artisan-task.json | 8 ++++----
 1 file changed, 4 insertions(+), 4 deletions(-)
APPLY: missing/broken brand logos will be downloaded.
Source: https://www.teplodvor.by/brands/
+------------------------+-------+
| metric                 | count |
+------------------------+-------+
| checked                | 205   |
| matched                | 132   |
| downloaded             | 132   |
| updated                | 132   |
| skipped_existing       | 0     |
| skipped_missing_source | 73    |
| errors                 | 0     |
+------------------------+-------+
+----------+------------------+----------+----------------------------------------------------------------------------+
| brand_id | brand            | old_logo | source                                                                     |
+----------+------------------+----------+----------------------------------------------------------------------------+
| 395      | AC Electric      | broken   | https://www.teplodvor.by/userfls/shop/small/14/137038_ac-electric.png      |
| 361      | AQUALINK         | broken   | https://www.teplodvor.by/userfls/shop/small/14/137412_aqualink.png         |
| 326      | Aquastic         | broken   | https://www.teplodvor.by/userfls/shop/small/10_aquastic.jpg                |
| 426      | AquaVerso        | broken   | https://www.teplodvor.by/userfls/shop/small/14/137563_aquaverso.png        |
| 337      | ARDERIA          | broken   | https://www.teplodvor.by/userfls/shop/small/5/48481_arderia.jpg            |
| 22       | Ariston          | broken   | https://www.teplodvor.by/userfls/shop/small/2797_ariston.png               |
| 404      | ASTON            | broken   | https://www.teplodvor.by/userfls/shop/small/11/106581_aston.png            |
| 287      | Auraton          | broken   | https://www.teplodvor.by/userfls/shop/small/6/54339_auraton.jpg            |
| 309      | AV Engineering   | broken   | https://www.teplodvor.by/userfls/shop/small/14/137566_av-engineering.png   |
| 297      | Ballu            | broken   | https://www.teplodvor.by/userfls/shop/small/14/137567_ballu.png            |
| 115      | BAXI             | broken   | https://www.teplodvor.by/userfls/shop/small/20_baxi.gif                    |
| 215      | BIAWAR           | broken   | https://www.teplodvor.by/userfls/shop/small/14/137898_biawar.png           |
| 311      | Blist            | broken   | https://www.teplodvor.by/userfls/shop/small/14/137900_blist.png            |
| 397      | Boneco           | broken   | https://www.teplodvor.by/userfls/shop/small/14/137901_boneco.png           |
| 36       | Bosch            | broken   | https://www.teplodvor.by/userfls/shop/small/3_bosch.jpg                    |
| 219      | BRV-MODVLVS      | broken   | https://www.teplodvor.by/userfls/shop/small/14/138003_brv.png              |
| 188      | Buderus          | broken   | https://www.teplodvor.by/userfls/shop/small/17_buderus-bosch-group.png     |
| 421      | Candy            | broken   | https://www.teplodvor.by/userfls/shop/small/14/138007_candy.png            |
| 325      | ComfortProm      | missing  | https://www.teplodvor.by/userfls/shop/small/14/138075_comfortprom.png      |
| 418      | DAB              | broken   | https://www.teplodvor.by/userfls/shop/small/14/138078_dab.png              |
| 267      | Darco            | broken   | https://www.teplodvor.by/userfls/shop/small/14/138348_darco.png            |
| 89       | De Dietrich      | broken   | https://www.teplodvor.by/userfls/shop/small/14/138357_de-dietrich.png      |
| 203      | Dimplex          | broken   | https://www.teplodvor.by/userfls/shop/small/14/138362_dimplex.png          |
| 185      | DoorWood         | broken   | https://www.teplodvor.by/userfls/shop/small/14/138707_doorwood.png         |
| 378      | E.C.A.           | broken   | https://www.teplodvor.by/userfls/shop/small/9/89957_eca.png                |
| 425      | Edisson          | broken   | https://www.teplodvor.by/userfls/shop/small/14/139095_edisson.png          |
| 328      | Elboom           | broken   | https://www.teplodvor.by/userfls/shop/small/14/139097_elboom.png           |
| 58       | Electrolux       | broken   | https://www.teplodvor.by/userfls/shop/small/16_electrolux.gif              |
| 399      | Energolux        | broken   | https://www.teplodvor.by/userfls/shop/small/10/98586_energolux.png         |
| 206      | Euroster         | broken   | https://www.teplodvor.by/userfls/shop/small/12/119862_euroster.png         |
| 371      | Federica Bugatti | broken   | https://www.teplodvor.by/userfls/shop/small/13/124862_federica-bugatti.png |
| 336      | Ferguss          | broken   | https://www.teplodvor.by/userfls/shop/small/11/109383_ferguss.png          |
| 94       | Ferroli          | broken   | https://www.teplodvor.by/userfls/shop/small/7575_ferroli.jpg               |
| 259      | Ferrum           | broken   | https://www.teplodvor.by/userfls/shop/small/14/139181_ferrum.png           |
| 390      | Firelight        | broken   | https://www.teplodvor.by/userfls/shop/small/14/139182_firelight.png        |
| 299      | FireWay          | broken   | https://www.teplodvor.by/userfls/shop/small/10/95826_fireway.png           |
| 256      | Flamco           | broken   | https://www.teplodvor.by/userfls/shop/small/14/139183_flamco.png           |
| 102      | Fondital         | broken   | https://www.teplodvor.by/userfls/shop/small/14/139184_fondital.png         |
| 60       | Galmet           | broken   | https://www.teplodvor.by/userfls/shop/small/33_galmet.jpg                  |
| 424      | Garanterm        | broken   | https://www.teplodvor.by/userfls/shop/small/7504_garanterm-.png            |
+----------+------------------+----------+----------------------------------------------------------------------------+

```
