<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CANONICAL_SKU = 'PS-008.471';

    private const DUPLICATE_SKU = 'PS-004.659';

    private const SOURCE_URL = 'https://aqualider.by/p/nasos-cirkulyac-upc-25-40-180-unipump-53843';

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $canonical = DB::table('products')->where('sku', self::CANONICAL_SKU)->first();
        if (! $canonical) {
            return;
        }

        DB::transaction(function () use ($canonical): void {
            $now = now();

            DB::table('products')->where('id', $canonical->id)->update([
                'name' => 'Насос циркуляционный UNIPUMP UPC 25-40 180',
                'price' => 176.92,
                'price_old' => null,
                'short_description' => 'Циркуляционный насос UNIPUMP UPC 25-40 180 с мокрым ротором для систем отопления: напор до 4,5 м, производительность до 3,5 м³/ч, монтажная длина 180 мм.',
                'content' => '<p><strong>UNIPUMP UPC 25-40 180</strong> — трёхскоростной циркуляционный насос с мокрым ротором для систем отопления с постоянным или слабо меняющимся расходом теплоносителя.</p><p>Модель развивает напор до 4,5 м и производительность до 3,5 м³/ч. Чугунный корпус рассчитан на давление до 10 бар, монтажная длина составляет 180 мм, температура рабочей жидкости — от +2 до +110 °C. Ответные соединения входят в комплект.</p>',
                'specs' => json_encode([
                    ['key' => 'Тип', 'value' => 'Циркуляционный насос с мокрым ротором'],
                    ['key' => 'Количество скоростей', 'value' => '3'],
                    ['key' => 'Максимальная мощность', 'value' => '72 Вт'],
                    ['key' => 'Максимальный напор', 'value' => '4,5 м'],
                    ['key' => 'Максимальная производительность', 'value' => '3,5 м³/ч (58,3 л/мин)'],
                    ['key' => 'Монтажная длина', 'value' => '180 мм'],
                    ['key' => 'Максимальное давление', 'value' => '10 бар'],
                    ['key' => 'Температура рабочей жидкости', 'value' => 'от +2 до +110 °C'],
                    ['key' => 'Материал корпуса', 'value' => 'Чугун'],
                    ['key' => 'Ответные соединения', 'value' => 'В комплекте'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'content_source_url' => self::SOURCE_URL,
                'meta_title' => 'Насос UNIPUMP UPC 25-40 180 — купить в Минске | KOTLOV',
                'meta_description' => 'Циркуляционный насос UNIPUMP UPC 25-40 180: напор 4,5 м, подача 3,5 м³/ч, монтажная длина 180 мм. Цена и доставка по Беларуси.',
                'is_active' => true,
                'is_archived' => false,
                'updated_at' => $now,
            ]);

            $duplicate = DB::table('products')->where('sku', self::DUPLICATE_SKU)->first();
            if (! $duplicate) {
                return;
            }

            DB::table('products')->where('id', $duplicate->id)->update([
                'is_active' => false,
                'is_archived' => true,
                'in_stock' => false,
                'availability_status' => 'out_of_stock',
                'stock_qty' => null,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('redirects')) {
                return;
            }

            $categorySlug = DB::table('categories')->where('id', $canonical->category_id)->value('slug');
            if (! $categorySlug || ! $duplicate->slug || ! $canonical->slug) {
                return;
            }

            DB::table('redirects')->updateOrInsert(
                ['from_url' => '/'.$categorySlug.'/'.$duplicate->slug],
                [
                    'to_url' => '/'.$categorySlug.'/'.$canonical->slug,
                    'status_code' => 301,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        DB::table('products')->where('sku', self::DUPLICATE_SKU)->update([
            'is_active' => true,
            'is_archived' => false,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('redirects')) {
            DB::table('redirects')
                ->where('from_url', '/tsirkulyatsionnyie/nasos-unipump-upc-25-40-180')
                ->delete();
        }
    }
};
