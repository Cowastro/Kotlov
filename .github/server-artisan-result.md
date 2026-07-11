# Server Artisan Result

- Time: 2026-07-11 07:35:57 UTC
- Task: `artisan-dry-run`
- Artisan args: `catalog:audit-media --type=brands --only-with-products --missing-only --limit=120`
- Log file: `storage/logs/server-artisan-media-audit.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   27a8cbb..e29270f  main       -> origin/main
Updating 27a8cbb..e29270f
Fast-forward
 .github/server-artisan-result.md | 20 ++++++++++----------
 .github/server-artisan-task.json |  6 +++---
 2 files changed, 13 insertions(+), 13 deletions(-)
Brands
+----------+-------+
| metric   | count |
+----------+-------+
| checked  | 200   |
| missing  | 39    |
| broken   | 161   |
| fallback | 0     |
| ok       | 0     |
+----------+-------+
+-----+--------------------+------------------+----------+---------+---------------------------------------------+
| id  | slug               | name             | products | media   | path                                        |
+-----+--------------------+------------------+----------+---------+---------------------------------------------+
| 395 | ac-electric        | AC Electric      | 8        | broken  | img/brands/teplodvor/ac-electric.png        |
| 253 | antifrogen         | Antifrogen       | 2        | missing | -                                           |
| 361 | aqualink-          | AQUALINK         | 1        | broken  | img/brands/teplodvor/aqualink.png           |
| 326 | aquastic           | Aquastic         | 7        | broken  | img/brands/teplodvor/aquastic.jpg           |
| 426 | aquaverso          | AquaVerso        | 2        | broken  | img/brands/teplodvor/aquaverso.png          |
| 337 | arderia            | ARDERIA          | 19       | broken  | img/brands/teplodvor/arderia.jpg            |
| 22  | ariston            | Ariston          | 248      | broken  | img/brands/teplodvor/ariston.png            |
| 404 | aston              | ASTON            | 27       | broken  | img/brands/teplodvor/aston.png              |
| 287 | auraton            | Auraton          | 3        | broken  | img/brands/teplodvor/auraton.jpg            |
| 309 | av-engineering     | AV Engineering   | 6        | broken  | img/brands/teplodvor/av-engineering.png     |
| 297 | ballu              | Ballu            | 126      | broken  | img/brands/teplodvor/ballu.png              |
| 115 | baxi               | BAXI             | 74       | broken  | img/brands/teplodvor/baxi.gif               |
| 215 | biawar             | BIAWAR           | 11       | broken  | img/brands/teplodvor/biawar.png             |
| 311 | blist              | Blist            | 34       | broken  | img/brands/teplodvor/blist.png              |
| 397 | boneco             | Boneco           | 7        | broken  | img/brands/teplodvor/boneco.png             |
| 36  | bosch              | Bosch            | 99       | broken  | img/brands/teplodvor/bosch.jpg              |
| 219 | brv-modvlvs        | BRV-MODVLVS      | 18       | broken  | 683-brv_logo.jpg                            |
| 188 | buderus            | Buderus          | 69       | broken  | img/brands/teplodvor/buderus.png            |
| 421 | candy              | Candy            | 8        | broken  | img/brands/teplodvor/candy.png              |
| 418 | dab                | DAB              | 216      | broken  | img/brands/teplodvor/dab.png                |
| 267 | darco              | Darco            | 179      | broken  | img/brands/teplodvor/darco.png              |
| 89  | de-dietrich        | De Dietrich      | 5        | broken  | img/brands/teplodvor/de-dietrich.png        |
| 203 | dimplex            | Dimplex          | 30       | broken  | img/brands/teplodvor/dimplex.png            |
| 185 | doorwood           | DoorWood         | 37       | broken  | img/brands/teplodvor/doorwood.png           |
| 378 | e.c.a.             | E.C.A.           | 9        | broken  | img/brands/teplodvor/eca.png                |
| 425 | edisson            | Edisson          | 12       | broken  | img/brands/teplodvor/edisson.png            |
| 328 | elboom-2           | Elboom           | 8        | broken  | img/brands/teplodvor/elboom-2.png           |
| 58  | electrolux         | Electrolux       | 481      | broken  | img/brands/teplodvor/electrolux.gif         |
| 399 | energolux          | Energolux        | 3        | broken  | img/brands/teplodvor/energolux.png          |
| 210 | esbe               | ESBE             | 12       | broken  | 139-index2.jpg                              |
| 387 | ech                | ESH              | 5        | missing | -                                           |
| 427 | eurostar           | Eurostar         | 7        | missing | -                                           |
| 206 | euroster           | Euroster         | 11       | broken  | img/brands/teplodvor/euroster.png           |
| 198 | ewt                | EWT              | 4        | broken  | 533-ewt_logo.jpg                            |
| 371 | federica-bugatti-2 | Federica Bugatti | 13       | broken  | img/brands/teplodvor/federica-bugatti-2.png |
| 336 | ferguss            | Ferguss          | 1        | broken  | img/brands/teplodvor/ferguss.png            |
| 94  | ferroli            | Ferroli          | 211      | broken  | img/brands/teplodvor/ferroli.jpg            |
| 259 | ferrum             | Ferrum           | 191      | broken  | img/brands/teplodvor/ferrum.png             |
| 220 | fireblaze          | FireBlaze        | 2        | broken  | 583-logo-fireblaze.png                      |
| 390 | firelight          | Firelight        | 5        | broken  | img/brands/teplodvor/firelight.png          |
| 299 | Fireway            | FireWay          | 28       | broken  | img/brands/teplodvor/fireway.png            |
| 256 | flamco             | Flamco           | 9        | broken  | img/brands/teplodvor/flamco.png             |
| 102 | fondital           | Fondital         | 51       | broken  | img/brands/teplodvor/fondital.png           |
| 60  | galmet             | Galmet           | 30       | broken  | img/brands/teplodvor/galmet.jpg             |
| 424 | garanterm          | Garanterm        | 19       | broken  | img/brands/teplodvor/garanterm.png          |
| 408 | gardana            | GARDANA          | 43       | broken  | img/brands/teplodvor/gardana.png            |
| 354 | gas-spezialisten   | Gas Spezialisten | 1        | missing | -                                           |
| 199 | giacomini          | Giacomini        | 59       | broken  | img/brands/teplodvor/giacomini.jpg          |
| 416 | gkb                | GKB              | 7        | missing | -                                           |
| 367 | greolit            | Greolit          | 28       | broken  | img/brands/teplodvor/greolit.png            |
| 275 | grill'd            | Grill'D          | 1        | broken  | img/brands/teplodvor/grilld.png             |
| 30  | grundfos           | Grundfos         | 22       | broken  | img/brands/teplodvor/grundfos.png           |
| 360 | haier              | HAIER            | 197      | broken  | img/brands/teplodvor/haier.jpg              |
| 21  | harvia             | Harvia           | 3        | broken  | img/brands/teplodvor/harvia.jpg             |
| 225 | herz               | HERZ             | 21       | broken  | 767-herz.jpg                                |
| 396 | hommyn             | Hommyn           | 22       | broken  | img/brands/teplodvor/hommyn.png             |
| 315 | hotta              | Hotta            | 9        | missing | -                                           |
| 224 | hyundai            | Hyundai          | 33       | broken  | img/brands/teplodvor/hyundai.png            |
| 409 | imp-pumps          | IMP PUMPS        | 1        | broken  | img/brands/teplodvor/imp-pumps.png          |
| 151 | invicta            | Invicta          | 5        | broken  | img/brands/teplodvor/invicta.png            |
| 410 | jemix              | JEMIX            | 1        | broken  | img/brands/teplodvor/jemix.png              |
| 229 | jotul              | JOTUL            | 1        | broken  | 191-jotul.jpg                               |
| 172 | junkers            | Junkers          | 4        | broken  | 753-junkers.jpg                             |
| 242 | kan                | KAN              | 23       | broken  | 183-system-kan-therm-biale-tlo.png          |
| 338 | karina             | KARINA           | 2        | broken  | img/brands/teplodvor/karina.png             |
| 344 | kennet             | Kennet           | 1        | broken  | img/brands/teplodvor/kennet.png             |
| 303 | kenantsu           | Kentatsu         | 4        | broken  | img/brands/teplodvor/kenantsu.png           |
| 76  | kermi              | Kermi            | 456      | broken  | img/brands/teplodvor/kermi.jpg              |
| 419 | kiturami           | Kiturami         | 2        | broken  | img/brands/teplodvor/kiturami.png           |
| 75  | kospel             | Kospel           | 247      | broken  | img/brands/teplodvor/kospel.png             |
| 385 | kotlov             | KOTLOV           | 38       | missing | -                                           |
| 139 | kratki             | Kratki           | 115      | broken  | img/brands/teplodvor/kratki.png             |
| 170 | lamborghini        | Lamborghini      | 23       | broken  | img/brands/teplodvor/lamborghini.png        |
| 365 | lava               | Lava             | 6        | broken  | img/brands/teplodvor/lava.png               |
| 359 | lavoro-            | Lavoro           | 13       | broken  | img/brands/teplodvor/lavoro.png             |
| 349 | ltek               | LTEK             | 9        | missing | -                                           |
| 324 | maxima             | Maxima           | 7        | missing | -                                           |
| 356 | mbs                | MBS              | 6        | broken  | img/brands/teplodvor/mbs.png                |
| 212 | meibes             | Meibes           | 27       | broken  | img/brands/teplodvor/meibes.png             |
| 412 | meran              | MERAN            | 7        | missing | -                                           |
| 201 | merlin             | Merlin           | 4        | missing | -                                           |
| 329 | mr.-tektum-        | Mr. Tektum       | 2        | missing | -                                           |
| 276 | navien             | Navien           | 16       | broken  | img/brands/teplodvor/navien.png             |
| 307 | nordflam           | Nordflam         | 12       | broken  | img/brands/teplodvor/nordflam.png           |
| 113 | nova-florida       | Nova Florida     | 3        | broken  | 217-logonovaflorida.jpg                     |
| 369 | nova-florida-      | Nova Florida     | 6        | missing | -                                           |
| 363 | ole-pro            | Ole-pro          | 1        | missing | -                                           |
| 28  | opop               | OPOP             | 6        | broken  | 147-opop.png                                |
| 415 | panadero           | Panadero         | 7        | broken  | img/brands/teplodvor/panadero.png           |
| 300 | parkanex           | Parkanex         | 7        | broken  | parkanex.png                                |
| 321 | pegas              | Pegas            | 81       | broken  | img/brands/teplodvor/pegas.png              |
| 372 | poer               | Poer             | 1        | broken  | img/brands/teplodvor/poer.png               |
| 245 | prado              | PRADO            | 24       | broken  | img/brands/teplodvor/prado.png              |
| 136 | profline           | Profline         | 25       | broken  | img/brands/teplodvor/profline.png           |
| 317 | prometall          | Prometall        | 1        | broken  | img/brands/teplodvor/prometall.png          |
| 23  | protherm           | Protherm         | 43       | broken  | img/brands/teplodvor/protherm.jpg           |
| 411 | purity             | PURITY           | 1        | missing | -                                           |
| 204 | real-flame         | Real Flame       | 7        | broken  | img/brands/teplodvor/real-flame.png         |
| 189 | regulus            | Regulus          | 2        | broken  | img/brands/teplodvor/regulus.png            |
| 207 | rehau              | REHAU            | 9        | broken  | 435-rehau.jpg                               |
| 318 | rifar              | Rifar            | 21       | broken  | img/brands/teplodvor/rifar.jpg              |
| 182 | rihters            | Rihters          | 6        | broken  | 961-rihters-logo-small.jpg                  |
| 213 | rinnai             | Rinnai           | 9        | broken  | img/brands/teplodvor/rinnai.png             |
| 202 | royal-flame        | Royal Flame      | 57       | broken  | img/brands/teplodvor/royal-flame.png        |
| 144 | royal-thermo       | Royal Thermo     | 426      | broken  | img/brands/teplodvor/royal-thermo.png       |
| 155 | s-tank             | S-TANK           | 46       | broken  | img/brands/teplodvor/s-tank.png             |
| 334 | sakovich           | Sakovich         | 67       | broken  | img/brands/teplodvor/sakovich.png           |
| 104 | salus              | Salus            | 30       | broken  | img/brands/teplodvor/salus.jpg              |
| 301 | scamol             | Scamol           | 6        | missing | -                                           |
| 391 | shuft              | SHUFT            | 17       | broken  | img/brands/teplodvor/shuft.png              |
| 43  | sime               | Sime             | 3        | broken  | img/brands/teplodvor/sime.png               |
| 92  | solpi              | Solpi            | 30       | broken  | 453-solpi-m.jpg                             |
| 420 | superlux           | Superlux         | 9        | missing | -                                           |
| 241 | sven               | SVEN             | 1        | broken  | img/brands/teplodvor/sven.png               |
| 423 | tec-line           | TEC Line         | 15       | broken  | img/brands/teplodvor/tec-line.png           |
| 228 | tech               | TECH             | 10       | broken  | img/brands/teplodvor/tech.png               |
| 375 | teknix             | TEKNIX           | 19       | broken  | img/brands/teplodvor/teknix.png             |
| 279 | tenko              | Tenko            | 52       | broken  | img/brands/teplodvor/tenko.png              |
| 34  | teplocom           | Teplocom         | 1        | broken  | 817-teplocom-logo.png                       |
| 134 | termica            | Termica          | 8        | broken  | img/brands/teplodvor/termica.png            |
+-----+--------------------+------------------+----------+---------+---------------------------------------------+

```
