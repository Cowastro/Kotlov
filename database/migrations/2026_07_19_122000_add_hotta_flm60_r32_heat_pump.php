<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'teplovoy-nasos-hotta-flm60-r32-23-kvt';

    public function up(): void
    {
        $now = now();

        $brandId = DB::table('brands')->where('slug', 'hotta')->value('id');

        if (! $brandId) {
            $brandId = DB::table('brands')->insertGetId([
                'name' => 'Hotta',
                'slug' => 'hotta',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('categories')->where('slug', 'teplovyie-nasosyi')->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => 'Тепловые насосы',
                'slug' => 'teplovyie-nasosyi',
                'parent_id' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existingSku = DB::table('products')->where('slug', self::SLUG)->value('sku');
        $sku = $existingSku ?: $this->nextSku();

        DB::table('products')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'supplier_id' => null,
                'name' => 'Тепловой насос Hotta Flamingo FLM60-R32 23 кВт',
                'h1' => 'Тепловой насос Hotta Flamingo FLM60-R32 23 кВт',
                'sku' => $sku,
                'price' => 16091.00,
                'price_old' => null,
                'currency' => 'BYN',
                'content' => $this->content(),
                'short_description' => 'Тепловой насос воздух-вода Hotta Flamingo FLM60-R32 мощностью 23 кВт для отопления, охлаждения и горячего водоснабжения частного дома.',
                'images' => json_encode([
                    'img/products/hotta/hotta-flm60-r32-main.jpg',
                    'img/products/hotta/hotta-flm60-r32-schema.jpg',
                    'img/products/hotta/hotta-flm60-r32-params.jpg',
                ], JSON_UNESCAPED_UNICODE),
                'specs' => json_encode($this->specs(), JSON_UNESCAPED_UNICODE),
                'service_info' => json_encode([
                    ['key' => 'Подбор', 'value' => 'Поможем проверить теплопотери дома, систему отопления и нужную мощность.'],
                    ['key' => 'Комплектация', 'value' => 'Подберём буферную ёмкость, автоматику, ГВС и резервный источник тепла при необходимости.'],
                    ['key' => 'Монтаж', 'value' => 'Возможна поставка и монтаж через специалистов KOTLOV.BY.'],
                ], JSON_UNESCAPED_UNICODE),
                'documents' => json_encode([]),
                'promo_flags' => json_encode([]),
                'video_url' => null,
                'weight' => null,
                'unit' => 'шт',
                'warranty' => 'Уточняйте у менеджера',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => false,
                'availability_status' => 'check',
                'stock_qty' => null,
                'is_featured' => false,
                'is_new' => true,
                'is_sale' => false,
                'sort_order' => 0,
                'meta_title' => 'Тепловой насос Hotta Flamingo FLM60-R32 23 кВт купить в Беларуси',
                'meta_keywords' => 'тепловой насос Hotta FLM60-R32, Hotta Flamingo 23 кВт, тепловой насос воздух вода, тепловой насос R32',
                'meta_description' => 'Тепловой насос воздух-вода Hotta Flamingo FLM60-R32 23 кВт: отопление, охлаждение и ГВС, фреон R32, питание 220 В, температура воды до 60°C, работа до -25°C.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('products')->where('slug', self::SLUG)->delete();
    }

    private function nextSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return 'KOTLOV-'.($max + 1);
    }

    private function specs(): array
    {
        return [
            ['key' => 'Бренд', 'value' => 'Hotta'],
            ['key' => 'Серия', 'value' => 'Flamingo'],
            ['key' => 'Модель', 'value' => 'FLM60-R32'],
            ['key' => 'Тип', 'value' => 'воздух-вода'],
            ['key' => 'Назначение', 'value' => 'отопление, охлаждение, горячее водоснабжение'],
            ['key' => 'Тепловая мощность', 'value' => '23 кВт'],
            ['key' => 'Хладагент', 'value' => 'R32'],
            ['key' => 'Питание', 'value' => '220 В'],
            ['key' => 'Максимальная температура воды', 'value' => 'до 60°C'],
            ['key' => 'Работа при наружной температуре', 'value' => 'до -25°C'],
            ['key' => 'Компрессор', 'value' => 'инверторный'],
            ['key' => 'Управление', 'value' => 'электронное, с автоматикой теплового насоса'],
        ];
    }

    private function content(): string
    {
        return <<<'HTML'
<p>Hotta Flamingo FLM60-R32 — тепловой насос воздух-вода мощностью 23 кВт для отопления, охлаждения и подготовки горячей воды. Модель подойдёт для частных домов и объектов, где нужна серьёзная мощность, но питание 220 В удобнее, чем трёхфазное подключение.</p>

<p>Насос работает на хладагенте R32, поддерживает температуру воды до 60°C и рассчитан на эксплуатацию при наружной температуре до -25°C. Инверторное управление помогает системе плавно подстраиваться под фактическую нагрузку дома.</p>

<h2>Где уместна модель 23 кВт</h2>

<p>FLM60-R32 можно рассматривать для хорошо утеплённых домов большой площади, систем с тёплыми полами, фанкойлами, низкотемпературными радиаторами, а также для объектов, где тепловой насос работает в связке с буферной ёмкостью или резервным источником тепла.</p>

<h2>Подбор лучше делать по расчёту</h2>

<p>Для теплового насоса важно учитывать не только паспортную мощность. Нужны теплопотери здания, требуемая температура подачи, объём горячей воды, электрическая мощность объекта и схема отопления. Специалисты KOTLOV.BY помогут проверить эти параметры и подобрать комплектацию под ваш дом.</p>
HTML;
    }
};
