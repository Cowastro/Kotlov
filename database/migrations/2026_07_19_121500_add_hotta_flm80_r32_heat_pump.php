<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SLUG = 'teplovoy-nasos-hotta-flm80-r32-30-kvt';

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
                'name' => 'Тепловой насос Hotta Flamingo FLM80-R32 30 кВт',
                'h1' => 'Тепловой насос Hotta Flamingo FLM80-R32 30 кВт',
                'sku' => $sku,
                'price' => 23831.00,
                'price_old' => null,
                'currency' => 'BYN',
                'content' => $this->content(),
                'short_description' => 'Высокопроизводительный тепловой насос воздух-вода Hotta Flamingo FLM80-R32 мощностью 30 кВт для отопления, охлаждения и горячего водоснабжения частных домов и коммерческих объектов.',
                'images' => json_encode([
                    'img/products/hotta/hotta-flm80-r32-main.jpg',
                    'img/products/hotta/hotta-flm80-r32-schema.jpg',
                    'img/products/hotta/hotta-flm80-r32-params.jpg',
                ], JSON_UNESCAPED_UNICODE),
                'specs' => json_encode($this->specs(), JSON_UNESCAPED_UNICODE),
                'service_info' => json_encode([
                    ['key' => 'Подбор', 'value' => 'Поможем проверить теплопотери, систему отопления и требуемую мощность под объект.'],
                    ['key' => 'Монтаж', 'value' => 'Возможна комплектация и монтаж через специалистов KOTLOV.BY.'],
                    ['key' => 'Запуск', 'value' => 'Рекомендуется профессиональный пуск и настройка автоматики.'],
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
                'meta_title' => 'Тепловой насос Hotta Flamingo FLM80-R32 30 кВт купить в Беларуси',
                'meta_keywords' => 'тепловой насос Hotta FLM80-R32, Hotta Flamingo 30 кВт, тепловой насос воздух вода, тепловой насос R32',
                'meta_description' => 'Тепловой насос воздух-вода Hotta Flamingo FLM80-R32 30 кВт: отопление, охлаждение и ГВС, фреон R32, питание 380 В, температура воды до 60°C, работа до -25°C.',
                'updated_at' => $now,
                'created_at' => $now,
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
            ['key' => 'Модель', 'value' => 'FLM80-R32'],
            ['key' => 'Тип', 'value' => 'воздух-вода'],
            ['key' => 'Назначение', 'value' => 'отопление, охлаждение, горячее водоснабжение'],
            ['key' => 'Тепловая мощность', 'value' => '30 кВт'],
            ['key' => 'Хладагент', 'value' => 'R32'],
            ['key' => 'Питание', 'value' => '380 В'],
            ['key' => 'Максимальная температура воды', 'value' => 'до 60°C'],
            ['key' => 'Работа при наружной температуре', 'value' => 'до -25°C'],
            ['key' => 'Компрессор', 'value' => 'инверторный'],
            ['key' => 'Управление', 'value' => 'электронное, с автоматикой теплового насоса'],
        ];
    }

    private function content(): string
    {
        return <<<'HTML'
<p>Hotta Flamingo FLM80-R32 — мощный тепловой насос воздух-вода для отопления, охлаждения и подготовки горячей воды. Модель рассчитана на объекты, где нужна высокая производительность: большие частные дома, коттеджи, коммерческие помещения, здания с тёплыми полами, фанкойлами или низкотемпературными радиаторами.</p>

<p>Тепловой насос работает на современном хладагенте R32, питается от сети 380 В и способен выдавать воду температурой до 60°C. Инверторное управление помогает плавно менять производительность под текущую нагрузку, а не постоянно включаться и выключаться на полной мощности.</p>

<h2>Кому подойдёт Hotta FLM80-R32</h2>

<p>Эта модель интересна там, где обычного бытового теплового насоса уже мало по мощности. FLM80-R32 можно рассматривать для домов большой площади, объектов с повышенным расходом горячей воды, модернизации котельной или комбинированной системы, где тепловой насос работает вместе с резервным источником тепла.</p>

<h2>Что важно учесть при подборе</h2>

<p>Для теплового насоса 30 кВт особенно важен правильный расчёт. Нужно учитывать теплопотери здания, температуру подачи в систему отопления, наличие буферной ёмкости, схему ГВС, электрическую мощность объекта и режим работы зимой. Специалисты KOTLOV.BY помогут проверить эти параметры и подобрать комплектацию под конкретный проект.</p>

<p>Если вы выбираете тепловой насос для большого дома или коммерческого объекта, не стоит ориентироваться только на мощность в названии. Важно смотреть на реальный температурный график системы, качество утепления, площадь тёплых полов или радиаторов и наличие резервного источника тепла.</p>
HTML;
    }
};
