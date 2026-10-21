<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLUG = 'kotlov-ge-nl-flm80-250ii-r290-25-2-kvt';

    private const SUPPLIER_ARTICLE = 'KOTLOV-GE-FLM80-250-R290';

    public function up(): void
    {
        $now = now();
        $categoryId = DB::table('categories')->where('slug', 'teplovyie-nasosyi')->value('id');
        $brandId = DB::table('brands')->where('slug', 'kotlov-ge')->value('id');
        $supplier = DB::table('suppliers')
            ->where(fn ($query) => $query
                ->where('code', 'sanbusinessgroup')
                ->orWhere('name', 'like', '%СанБизнесГруп%'))
            ->orderByRaw("CASE WHEN code = 'sanbusinessgroup' THEN 0 ELSE 1 END")
            ->first();

        if (! $categoryId || ! $brandId || ! $supplier) {
            throw new RuntimeException('Не найдены категория тепловых насосов, бренд KOTLOV GE или поставщик СанБизнесГруп.');
        }

        $existing = DB::table('products')->where('slug', self::SLUG)->first();
        $productPayload = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => 'Тепловой насос KOTLOV GE NL-FLM80-250II/R290 25,2 кВт',
            'h1' => 'Тепловой насос KOTLOV GE NL-FLM80-250II/R290 25,2 кВт',
            'sku' => $existing?->sku ?: $this->nextSku(),
            'price' => 21300.00,
            'price_old' => null,
            'currency' => 'BYN',
            'content' => $this->content(),
            'short_description' => 'Высокотемпературный инверторный тепловой насос воздух-вода KOTLOV GE FLM80-250 на R290: 25,2 кВт, отопление, охлаждение и ГВС, вода до 80 °C, питание 380 В.',
            'images' => json_encode([
                'img/products/kotlov-ge/flm80-250-r290/kotlov-ge-flm80-250-r290-angle.webp',
                'img/products/kotlov-ge/flm80-250-r290/kotlov-ge-flm80-250-r290-front.webp',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'specs' => json_encode($this->specs(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'service_info' => json_encode([
                ['key' => 'Инженерный подбор', 'value' => 'Проверим теплопотери, температурный график, электропитание и схему ГВС.'],
                ['key' => 'Комплектация', 'value' => 'Подберём буферную ёмкость, бойлер, насосные группы, автоматику и резервный источник.'],
                ['key' => 'Монтаж', 'value' => 'Выполняем монтаж и пусконаладку тепловых насосов по Беларуси.'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'documents' => json_encode([], JSON_UNESCAPED_UNICODE),
            'promo_flags' => json_encode([], JSON_UNESCAPED_UNICODE),
            'video_url' => null,
            'weight' => 175,
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
            'meta_title' => 'Тепловой насос KOTLOV GE FLM80-250 R290 25,2 кВт купить в Беларуси',
            'meta_keywords' => 'KOTLOV GE FLM80-250, тепловой насос 25 кВт, тепловой насос R290, высокотемпературный тепловой насос, воздух-вода 380 В',
            'meta_description' => 'KOTLOV GE NL-FLM80-250II/R290 25,2 кВт: отопление, охлаждение и ГВС, вода до 80 °C, работа до -25 °C, 380 В. Цена 21 300 BYN.',
            'updated_at' => $now,
            'created_at' => $existing?->created_at ?: $now,
        ];

        if (Schema::hasColumn('products', 'requires_price_confirmation')) {
            $productPayload['requires_price_confirmation'] = false;
        }
        if (Schema::hasColumn('products', 'content_source_url')) {
            $productPayload['content_source_url'] = null;
        }

        DB::table('products')->updateOrInsert(['slug' => self::SLUG], $productPayload);
        $product = DB::table('products')->where('slug', self::SLUG)->first();

        DB::table('brand_supplier')->updateOrInsert(
            ['brand_id' => $brandId, 'supplier_id' => $supplier->id],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $supplierPayload = [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article_normalized' => self::SUPPLIER_ARTICLE,
            'supplier_article_compact' => 'KOTLOVGEFLM80250R290',
            'supplier_name' => $product->name,
            'price' => null,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => null,
            'in_stock' => false,
            'stock_quantity' => null,
            'stock_status' => 'preorder',
            'stock_text' => 'Доступно к заказу · наличие и срок уточняет менеджер',
            'warehouse_name' => 'ООО «СанБизнесГруп»',
            'match_status' => 'matched',
            'match_confidence' => 'manual_verified',
            'raw' => json_encode([
                'brand' => 'KOTLOV GE',
                'model' => 'NL-FLM80-250II/R290',
                'supplier' => 'ООО «СанБизнесГруп»',
                'retail_price_byn' => 21300,
                'source' => 'Nulite Heat Pump Catalog 2026 and supplied factory photos',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_synced_at' => $now,
            'updated_at' => $now,
            'created_at' => $now,
        ];

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplier->id, 'supplier_article' => self::SUPPLIER_ARTICLE],
            $supplierPayload,
        );

        DB::table('suppliers')->where('id', $supplier->id)->update(['is_active' => true, 'updated_at' => $now]);
    }

    public function down(): void
    {
        $productId = DB::table('products')->where('slug', self::SLUG)->value('id');
        if ($productId) {
            DB::table('supplier_products')->where('product_id', $productId)->delete();
            DB::table('products')->where('id', $productId)->delete();
        }
    }

    private function nextSku(): string
    {
        $next = ((int) DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $matches) ? (int) $matches[1] : 0)
            ->max()) + 1;

        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function specs(): array
    {
        return [
            ['key' => 'Бренд', 'value' => 'KOTLOV GE'],
            ['key' => 'Модель', 'value' => 'NL-FLM80-250II/R290'],
            ['key' => 'Тип', 'value' => 'моноблочный тепловой насос воздух-вода'],
            ['key' => 'Назначение', 'value' => 'отопление, охлаждение, горячее водоснабжение'],
            ['key' => 'Тепловая мощность A7/W35', 'value' => '25,2 кВт'],
            ['key' => 'Потребляемая мощность A7/W35', 'value' => '5,85 кВт'],
            ['key' => 'Мощность в режиме ГВС A7/W55', 'value' => '23,3 кВт'],
            ['key' => 'Потребляемая мощность в режиме ГВС A7/W55', 'value' => '7,58 кВт'],
            ['key' => 'Мощность охлаждения A35/W18', 'value' => '25,8 кВт'],
            ['key' => 'Потребляемая мощность при охлаждении A35/W18', 'value' => '7,06 кВт'],
            ['key' => 'Хладагент', 'value' => 'R290'],
            ['key' => 'Питание', 'value' => '380–415 В, 3 фазы'],
            ['key' => 'Температура воды', 'value' => 'ГВС 55 °C, отопление 45 °C, охлаждение 12 °C'],
            ['key' => 'Максимальная температура воды', 'value' => '75–80 °C'],
            ['key' => 'Рабочая температура воздуха', 'value' => 'от −25 до +43 °C'],
            ['key' => 'Расход воды', 'value' => '4,3 м³/ч'],
            ['key' => 'Компрессор', 'value' => 'Panasonic, полноинверторный EVI, двухроторный'],
            ['key' => 'Количество компрессоров', 'value' => '1'],
            ['key' => 'Вентиляторы', 'value' => '2, DC-инверторные'],
            ['key' => 'Уровень шума', 'value' => 'не более 58 дБ(А)'],
            ['key' => 'Встроенный электронагреватель', 'value' => '3 кВт'],
            ['key' => 'Циркуляционный насос', 'value' => 'встроенный инверторный'],
            ['key' => 'Расширительный бак', 'value' => '8 л'],
            ['key' => 'Подключение воды', 'value' => 'DN40'],
            ['key' => 'Степень защиты', 'value' => 'IPX4'],
            ['key' => 'Габариты', 'value' => '1080 × 499 × 1663 мм'],
            ['key' => 'Вес', 'value' => '175 кг'],
            ['key' => 'Класс энергоэффективности', 'value' => 'A+++ при 35 °C / A++ при 55 °C'],
            ['key' => 'Управление', 'value' => 'контроллер, Wi‑Fi, RS485'],
        ];
    }

    private function content(): string
    {
        return <<<'HTML'
<p><strong>KOTLOV GE NL-FLM80-250II/R290</strong> — высокотемпературный инверторный тепловой насос воздух-вода теплопроизводительностью 25,2 кВт. Моноблок рассчитан на отопление, охлаждение и приготовление горячей воды в больших частных домах и коммерческих объектах.</p>

<h2>Высокая температура для радиаторов и ГВС</h2>
<p>Хладагент R290 и EVI-инверторный компрессор позволяют нагревать воду до 75–80 °C. Поэтому модель подходит не только для тёплого пола, но и для радиаторного отопления, реконструкции существующей котельной и систем с повышенными требованиями к горячему водоснабжению.</p>

<h2>Основные возможности FLM80-250</h2>
<ul>
    <li>тепловая мощность 25,2 кВт в режиме A7/W35;</li>
    <li>отопление, охлаждение и горячее водоснабжение одним агрегатом;</li>
    <li>полноинверторный EVI-компрессор Panasonic;</li>
    <li>работа при наружной температуре до −25 °C;</li>
    <li>встроенные циркуляционный насос, расширительный бак 8 л и электронагреватель 3 кВт;</li>
    <li>управление через контроллер, Wi‑Fi и интерфейс RS485;</li>
    <li>два тихоходных DC-вентилятора и уровень шума не более 58 дБ(А).</li>
</ul>

<h2>Для каких объектов подходит</h2>
<p>Модель рассчитана на объекты с серьёзной тепловой нагрузкой: большие коттеджи, гостиницы, офисы, производственные и торговые помещения. Точная применимость определяется расчётными теплопотерями, требуемой температурой подачи, доступной электрической мощностью и режимом горячего водоснабжения.</p>

<h2>Подбор и монтаж KOTLOV.BY</h2>
<p>Перед заказом специалисты KOTLOV проверят теплопотери здания, гидравлическую схему, электропитание 380 В, объём бойлера и необходимость буферной ёмкости или резервного котла. По результатам подготовим спецификацию и предложение на <a href="/montazh-teplovyh-nasosov">монтаж теплового насоса под ключ</a>.</p>

<blockquote>Мощность 25,2 кВт указана для режима A7/W35. Фактическая производительность и энергопотребление зависят от температуры наружного воздуха и температуры воды в системе.</blockquote>
HTML;
    }
};
