<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;

/**
 * Fix Ariston (Thermostudio) products stuck on a stale/opt price.
 *
 * Root cause: the Thermostudio Google Sheet has a legacy "Ariston ЭВН" tab
 * that duplicates several supplier articles from the current "Ariston" tab
 * with garbled column values. supplier:sync-thermostudio-pricelist treats
 * duplicate articles as ambiguous and skips them, so these products never
 * get their retail price refreshed and stayed on old/wrong values (in a
 * few cases pre-dating the opt/retail column fix entirely).
 *
 * Prices below are the "Розница" (RRC) column from the 17.09.26 Термостудия
 * price list, matched to products by sku. Idempotent (down() is a no-op).
 */
return new class extends Migration
{
    private const FIXES = [
        'PS-000.17242' => 355.0, // Водонагреватель Ariston PRO1 R ABS 40 V Slim EXTRA 1,8 (was 1401.8)
        'PS-000.17243' => 1290.0, // Водонагреватель электрический Ariston ABSE VLS PRO PW 100 (was 1030.0)
        'PS-000.17258' => 1125.0, // Водонагреватель электрический Ariston ABS VLS PRO INOX R 80 (was 900.0)
        'PS-000.17278' => 1135.0, // Водонагреватель электрический Ariston ABSE VLS PRO PW 80 (was 908.0)
        'PS-000.17289' => 713.0, // Водонагреватель Ariston Lydos R ABS 80 V (was 585.0)
        'PS-000.17306' => 810.0, // Водонагреватель Ariston LYDOS ECO ABS PW 80 V (was 652.7)
        'PS-000.17310' => 780.0, // Водонагреватель Ariston Lydos R ABS 100 V (was 640.0)
        'PS-000.17381' => 430.0, // Водонагреватель Ariston PRO1 R 50 V PL (was 345.0)
        'PS-000.17382' => 435.0, // Водонагреватель электрический Ariston PRO1 R ABS 30 V Slim (was 350.0)
        'PS-000.17383' => 575.0, // Водонагреватель Ariston PRO1 R 100 V PL (was 1100.0)
        'PS-000.17392' => 585.0, // Водонагреватель Ariston PRO1 R ABS 80 V Slim (was 465.0)
        'PS-000.17394' => 1288.0, // Водонагреватель электрический Ariston ABSE VLS PRO INOX PW 80 (was 1025.0)
        'PS-000.17397' => 1950.0, // Водонагреватель электрический Ariston ARI 200 VERT 513 THER MO SF (was 200513.0)
        'PS-000.17398' => 1455.0, // Водонагреватель электрический Ariston ABSE VLS PRO INOX PW 100 (was 1160.0)
        'PS-000.17407' => 2285.0, // Водонагреватель Ariston ARI 200 STAB 570 THER MO VS EU (was 200570.0)
        'PS-000.17408' => 935.0, // Водонагреватель электрический Ariston ABSE VLS PRO PW 50 (was 745.0)
        'PS-000.17413' => 505.0, // Водонагреватель Ariston PRO1 R 80 V PL (was 405.0)
        'PS-000.17417' => 430.0, // Водонагреватель Ariston PRO1 R ABS 65 V Slim (was 340.0)
        'PS-000.17418' => 1280.0, // Водонагреватель электрический Ariston ABS VLS PRO INOX R 100 (was 1025.0)
        'PS-000.17421' => 495.0, // Водонагреватель Ariston PRO1 R ABS 50 V Slim (was 395.0)
        'PS-000.17485' => 475.0, // Водонагреватель электрический Ariston ANDRIS2 B 30 O (was 390.0)
        'PS-000.17500' => 873.0, // Водонагреватель электрический Ariston LYDOS ECO ABS PW 100 V (was 715.0)
        'PS-000.17520' => 455.0, // Водонагреватель электрический Ariston ANDRIS R 30 (was 365.0)
        'KOTLOV-006003' => 349.0, // Ariston ANDRIS R 10 (Установка над мойкой) (was 280.0)
        'KOTLOV-006004' => 349.0, // Ariston ANDRIS R 10 U (Установка под мойкой) (was 280.0)
        'KOTLOV-006005' => 390.0, // Ariston ANDRIS R 15 U (Установка под мойкой) (was 315.0)
        'KOTLOV-006011' => 860.0, // Ariston PRO1 R ABS 150 V (was 1150.0)
        'KOTLOV-006013' => 765.0, // Ariston ABS VLS PRO R 50 (was 610.0)
        'KOTLOV-006014' => 930.0, // Ariston ABS VLS PRO R 80 (was 745.0)
        'KOTLOV-006015' => 1055.0, // Ariston ABS VLS PRO R 100 (was 845.0)
    ];

    public function up(): void
    {
        foreach (self::FIXES as $sku => $price) {
            Product::where('sku', $sku)->update(['price' => $price]);
        }
    }

    public function down(): void {}
};
