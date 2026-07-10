# Server Artisan Result

- Time: 2026-07-10 20:02:48 UTC
- Task: `artisan-apply`
- Artisan args: `brands:discover-teplodvor-logos --apply --limit=0`
- Log file: `storage/logs/brands-discover-teplodvor-logos.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   a58956f..c6d896e  main       -> origin/main
Updating a58956f..c6d896e
Fast-forward
 .github/server-artisan-result.md | 322 ++++-----------------------------------
 .github/server-artisan-task.json |   8 +-
 2 files changed, 30 insertions(+), 300 deletions(-)
APPLY: missing/broken brand logos will be downloaded.
Source: https://www.teplodvor.by/brands/
+------------------------+-------+
| metric                 | count |
+------------------------+-------+
| checked                | 200   |
| matched                | 129   |
| downloaded             | 129   |
| updated                | 129   |
| skipped_existing       | 0     |
| skipped_missing_source | 71    |
| errors                 | 0     |
+------------------------+-------+
+----------+------------------+----------+----------------------------------------------------------------------------+
| brand_id | brand            | old_logo | source                                                                     |
+----------+------------------+----------+----------------------------------------------------------------------------+
| 395      | AC Electric      | missing  | https://www.teplodvor.by/userfls/shop/small/14/137038_ac-electric.png      |
| 361      | AQUALINK         | missing  | https://www.teplodvor.by/userfls/shop/small/14/137412_aqualink.png         |
| 326      | Aquastic         | missing  | https://www.teplodvor.by/userfls/shop/small/10_aquastic.jpg                |
| 426      | AquaVerso        | missing  | https://www.teplodvor.by/userfls/shop/small/14/137563_aquaverso.png        |
| 337      | ARDERIA          | missing  | https://www.teplodvor.by/userfls/shop/small/5/48481_arderia.jpg            |
| 22       | Ariston          | broken   | https://www.teplodvor.by/userfls/shop/small/2797_ariston.png               |
| 404      | ASTON            | missing  | https://www.teplodvor.by/userfls/shop/small/11/106581_aston.png            |
| 287      | Auraton          | missing  | https://www.teplodvor.by/userfls/shop/small/6/54339_auraton.jpg            |
| 309      | AV Engineering   | broken   | https://www.teplodvor.by/userfls/shop/small/14/137566_av-engineering.png   |
| 297      | Ballu            | missing  | https://www.teplodvor.by/userfls/shop/small/14/137567_ballu.png            |
| 115      | BAXI             | broken   | https://www.teplodvor.by/userfls/shop/small/20_baxi.gif                    |
| 215      | BIAWAR           | broken   | https://www.teplodvor.by/userfls/shop/small/14/137898_biawar.png           |
| 311      | Blist            | missing  | https://www.teplodvor.by/userfls/shop/small/14/137900_blist.png            |
| 397      | Boneco           | missing  | https://www.teplodvor.by/userfls/shop/small/14/137901_boneco.png           |
| 36       | Bosch            | broken   | https://www.teplodvor.by/userfls/shop/small/3_bosch.jpg                    |
| 188      | Buderus          | broken   | https://www.teplodvor.by/userfls/shop/small/17_buderus-bosch-group.png     |
| 421      | Candy            | missing  | https://www.teplodvor.by/userfls/shop/small/14/138007_candy.png            |
| 418      | DAB              | missing  | https://www.teplodvor.by/userfls/shop/small/14/138078_dab.png              |
| 267      | Darco            | missing  | https://www.teplodvor.by/userfls/shop/small/14/138348_darco.png            |
| 89       | De Dietrich      | broken   | https://www.teplodvor.by/userfls/shop/small/14/138357_de-dietrich.png      |
| 203      | Dimplex          | broken   | https://www.teplodvor.by/userfls/shop/small/14/138362_dimplex.png          |
| 185      | DoorWood         | broken   | https://www.teplodvor.by/userfls/shop/small/14/138707_doorwood.png         |
| 378      | E.C.A.           | broken   | https://www.teplodvor.by/userfls/shop/small/9/89957_eca.png                |
| 425      | Edisson          | missing  | https://www.teplodvor.by/userfls/shop/small/14/139095_edisson.png          |
| 328      | Elboom           | missing  | https://www.teplodvor.by/userfls/shop/small/14/139097_elboom.png           |
| 58       | Electrolux       | broken   | https://www.teplodvor.by/userfls/shop/small/16_electrolux.gif              |
| 399      | Energolux        | missing  | https://www.teplodvor.by/userfls/shop/small/10/98586_energolux.png         |
| 206      | Euroster         | broken   | https://www.teplodvor.by/userfls/shop/small/12/119862_euroster.png         |
| 371      | Federica Bugatti | broken   | https://www.teplodvor.by/userfls/shop/small/13/124862_federica-bugatti.png |
| 336      | Ferguss          | missing  | https://www.teplodvor.by/userfls/shop/small/11/109383_ferguss.png          |
| 94       | Ferroli          | broken   | https://www.teplodvor.by/userfls/shop/small/7575_ferroli.jpg               |
| 259      | Ferrum           | broken   | https://www.teplodvor.by/userfls/shop/small/14/139181_ferrum.png           |
| 390      | Firelight        | missing  | https://www.teplodvor.by/userfls/shop/small/14/139182_firelight.png        |
| 299      | FireWay          | broken   | https://www.teplodvor.by/userfls/shop/small/10/95826_fireway.png           |
| 256      | Flamco           | broken   | https://www.teplodvor.by/userfls/shop/small/14/139183_flamco.png           |
| 102      | Fondital         | broken   | https://www.teplodvor.by/userfls/shop/small/14/139184_fondital.png         |
| 60       | Galmet           | broken   | https://www.teplodvor.by/userfls/shop/small/33_galmet.jpg                  |
| 424      | Garanterm        | missing  | https://www.teplodvor.by/userfls/shop/small/7504_garanterm-.png            |
| 408      | GARDANA          | missing  | https://www.teplodvor.by/userfls/shop/small/14/139233_gardana.png          |
| 199      | Giacomini        | broken   | https://www.teplodvor.by/userfls/shop/small/45_giacomini.jpg               |
+----------+------------------+----------+----------------------------------------------------------------------------+

```
