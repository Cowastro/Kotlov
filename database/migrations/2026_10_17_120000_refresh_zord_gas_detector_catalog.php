<?php

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, mixed>> */
    private const PRODUCTS = [
        'PS-008.168' => [
            'name' => 'Сигнализатор загазованности ЗОРД УГС-03 (природный и сжиженный газ)',
            'price' => 85.00,
            'display_specs' => [
                ['key' => 'Контролируемые газы', 'value' => 'Природный и сжиженный газ'],
                ['key' => 'Питание', 'value' => '230 В, 50 Гц'],
                ['key' => 'Габариты', 'value' => '100×100×30 мм'],
                ['key' => 'Гарантия производителя', 'value' => '24 месяца'],
                ['key' => 'Производитель', 'value' => 'ООО «ЗОРД Электротеплоприбор»'],
                ['key' => 'Страна производства', 'value' => 'Беларусь'],
            ],
            'specs' => [
                ['key' => 'Тип', 'value' => 'Сигнализатор загазованности'],
                ['key' => 'Контролируемые газы', 'value' => 'Природный и сжиженный газ'],
                ['key' => 'Режим работы', 'value' => 'Круглосуточный, непрерывный'],
                ['key' => 'Сигнализация', 'value' => 'Световая и звуковая'],
                ['key' => 'Уровень звукового сигнала', 'value' => '75 дБ'],
                ['key' => 'Питание', 'value' => '230 В, 50 Гц'],
                ['key' => 'Допустимое напряжение', 'value' => '140–245 В'],
                ['key' => 'Рабочая температура', 'value' => 'от −10 до +55 °C'],
                ['key' => 'Габариты', 'value' => '100×100×30 мм'],
                ['key' => 'Масса', 'value' => '0,3 кг'],
                ['key' => 'Длина кабеля питания', 'value' => '1,4 м'],
                ['key' => 'Гарантия производителя', 'value' => '24 месяца'],
                ['key' => 'Производитель', 'value' => 'ООО «ЗОРД Электротеплоприбор»'],
                ['key' => 'Страна производства', 'value' => 'Беларусь'],
            ],
        ],
        'PS-011.094' => [
            'name' => 'Сигнализатор загазованности ЗОРД УГС-02 (CO+CH) комбинированный',
            'price' => 105.00,
            'display_specs' => [
                ['key' => 'Контролируемые газы', 'value' => 'Угарный газ CO и горючие газы'],
                ['key' => 'Питание', 'value' => '230 В, 50 Гц'],
                ['key' => 'Габариты', 'value' => '110×70×40 мм'],
                ['key' => 'Гарантия производителя', 'value' => '24 месяца'],
                ['key' => 'Производитель', 'value' => 'ООО «ЗОРД Электротеплоприбор»'],
                ['key' => 'Страна производства', 'value' => 'Беларусь'],
            ],
            'specs' => [
                ['key' => 'Тип', 'value' => 'Комбинированный сигнализатор загазованности'],
                ['key' => 'Контролируемые газы', 'value' => 'Угарный газ CO и горючие газы'],
                ['key' => 'Режим работы', 'value' => 'Круглосуточный, непрерывный'],
                ['key' => 'Сигнализация', 'value' => 'Световая и звуковая'],
                ['key' => 'Питание', 'value' => '230 В, 50 Гц'],
                ['key' => 'Допустимое напряжение', 'value' => '155–265 В'],
                ['key' => 'Габариты', 'value' => '110×70×40 мм'],
                ['key' => 'Масса', 'value' => '0,3 кг'],
                ['key' => 'Длина кабеля питания', 'value' => '1,4 м'],
                ['key' => 'Гарантия производителя', 'value' => '24 месяца'],
                ['key' => 'Производитель', 'value' => 'ООО «ЗОРД Электротеплоприбор»'],
                ['key' => 'Страна производства', 'value' => 'Беларусь'],
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('attributes')
            || ! Schema::hasTable('product_attribute_values')) {
            return;
        }

        $enricher = app(ProductSourceEnricher::class);

        foreach (self::PRODUCTS as $sku => $data) {
            $product = Product::query()->where('sku', $sku)->first();
            if (! $product) {
                continue;
            }

            $name = (string) $data['name'];
            $product->forceFill([
                'name' => $name,
                'h1' => $name,
                'price' => (float) $data['price'],
                'specs' => $data['display_specs'],
                'content_source_url' => 'https://gazby.by/shop/',
                'meta_title' => $name.' — купить в Беларуси | KOTLOV',
                'meta_description' => 'Купить '.$name.' в KOTLOV. Характеристики, актуальная цена и наличие, доставка по Беларуси.',
            ])->save();

            // These cards previously inherited importer/manufacturer fields
            // from an unrelated Chinese product. Rebuild the public attributes
            // from the verified Belarusian detector specification.
            $product->allAttributeValues()->delete();
            $enricher->syncSpecsToAttributeValues($product, $data['specs']);
        }
    }

    public function down(): void
    {
        // Verified current prices and manufacturer data must not be restored to
        // the known-stale values during rollback.
    }
};
