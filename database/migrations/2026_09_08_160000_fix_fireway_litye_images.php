<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Product;

/**
 * Fix product images for Fireway литьё/прочее batch:
 *  - DS-K духовые шкафы: реальные фото с fireway.pro
 *  - K313, K411: реальные фото с fireway.pro
 *  - Облик: исправлен неверный URL (oblique, не oblik)
 *  - Master Flash / Втулка / Набор крёпежных К4: убрать неправильный
 *    плейсхолдер A101 (оранжевый фитинг), оставить пустой массив
 */
return new class extends Migration
{
    private const FIXES = [
        // slug => новый массив images (пустой = нет фото)

        // ── Духовые шкафы DS-K — реальные фото ─────────────────────────
        'fireway-shkaf-duhovoj-ds-k201'  => ['https://fireway.pro/assets/media/products/231/small/duhovka-201.jpg'],
        'fireway-shkaf-duhovoj-ds-k211'  => ['https://fireway.pro/assets/media/products/232/small/duhovka-211.jpg'],
        'fireway-shkaf-duhovoj-ds-k202'  => ['https://fireway.pro/assets/media/products/233/small/duhovka-202.jpg'],
        'fireway-shkaf-duhovoj-ds-k212'  => ['https://fireway.pro/assets/media/products/234/small/duhovka-212.jpg'],

        // ── Дверцы без стекла — реальные фото ──────────────────────────
        'fireway-dverza-k313-topochnaya' => ['https://fireway.pro/assets/media/products/138/bhb-k313-250x280.jpg'],
        'fireway-dverza-k411-topochnaya' => ['https://fireway.pro/assets/media/products/143/bhb-k411-250x210.jpg'],

        // ── Светильник Облик — исправлен URL ────────────────────────────
        'fireway-svetilnik-oblik'        => ['https://fireway.pro/assets/media/products/44/fireway-svetilnik-oblique.jpg'],

        // ── Убрать неправильный плейсхолдер A101 (оранжевый фитинг) ────
        'fireway-nabor-krepezha-k4'               => [],
        'fireway-master-flash-1'                  => [],
        'fireway-master-flash-2'                  => [],
        'fireway-master-flash-3'                  => [],
        'fireway-master-flash-8-pryamoj'          => [],
        'fireway-vtulka-izolyator-keramicheskaya' => [],
    ];

    public function up(): void
    {
        foreach (self::FIXES as $slug => $images) {
            Product::where('slug', $slug)->update(['images' => $images]);
        }
    }

    public function down(): void {}
};
