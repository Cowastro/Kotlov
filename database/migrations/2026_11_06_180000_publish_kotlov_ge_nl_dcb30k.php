<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLUG = 'kotlov-ge-nl-dcb30k-90-kvt';

    private const SUPPLIER_ARTICLE = 'KOTLOV-GE-NL-DCB30K';

    public function up(): void
    {
        $now = now();
        $categoryId = DB::table('categories')->where('slug', 'teplovye-nasosy-vozduh-voda')->value('id')
            ?: DB::table('categories')->where('slug', 'teplovyie-nasosyi')->value('id');
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
            'name' => 'Тепловой насос KOTLOV GE NL-DCB30K 90 кВт',
            'h1' => 'Тепловой насос KOTLOV GE NL-DCB30K 90 кВт',
            'sku' => $existing?->sku ?: $this->nextSku(),
            'price' => 43800.00,
            'price_old' => null,
            'currency' => 'BYN',
            'content' => $this->content(),
            'short_description' => 'Коммерческий инверторный тепловой насос воздух-вода KOTLOV GE NL-DCB30K с EVI: 90 кВт при +7 °C, 60 кВт при −12 °C, отопление и охлаждение, работа до −35 °C, питание 380 В.',
            'images' => json_encode([
                'img/products/kotlov-ge/dcb30k/kotlov-ge-nl-dcb30k-angle.webp',
                'img/products/kotlov-ge/dcb30k/kotlov-ge-nl-dcb30k-front.webp',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'specs' => json_encode($this->specs(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'service_info' => json_encode([
                ['key' => 'Инженерный подбор', 'value' => 'Проверим теплопотери объекта, температурный график, электропитание 380 В и модульную схему.'],
                ['key' => 'Комплектация', 'value' => 'Подберём буферную ёмкость, насосы, гидравлический разделитель, автоматику и резервный источник.'],
                ['key' => 'Монтаж', 'value' => 'Выполняем монтаж и пусконаладку тепловых насосов по Беларуси.'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'documents' => json_encode([], JSON_UNESCAPED_UNICODE),
            'promo_flags' => json_encode([], JSON_UNESCAPED_UNICODE),
            'video_url' => null,
            'weight' => 450,
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
            'meta_title' => 'Тепловой насос KOTLOV GE NL-DCB30K 90 кВт купить в Беларуси',
            'meta_keywords' => 'KOTLOV GE NL-DCB30K, тепловой насос 90 кВт, коммерческий тепловой насос, тепловой насос EVI, воздух-вода 380 В',
            'meta_description' => 'KOTLOV GE NL-DCB30K 90 кВт: коммерческий инверторный тепловой насос с EVI, отопление и охлаждение, работа до −35 °C, 380 В. Цена 43 800 BYN.',
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
            'supplier_article_compact' => 'KOTLOVGENLDCB30K',
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
                'model' => 'NL-DCB30K',
                'supplier' => 'ООО «СанБизнесГруп»',
                'retail_price_byn' => 43800,
                'source' => 'Nulite Heat Pump Catalog 2026, Commercial DC Inverter Heat Pump',
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
            ['key' => 'Модель', 'value' => 'NL-DCB30K'],
            ['key' => 'Производственная платформа', 'value' => 'NULITE'],
            ['key' => 'Тип', 'value' => 'коммерческий инверторный тепловой насос воздух-вода, моноблок'],
            ['key' => 'Назначение', 'value' => 'отопление и охлаждение'],
            ['key' => 'Тепловая мощность A7/W45', 'value' => '90 кВт'],
            ['key' => 'COP A7/W45', 'value' => '3,55'],
            ['key' => 'Потребляемая мощность при +7 °C', 'value' => '25,35 кВт'],
            ['key' => 'Тепловая мощность A−12/W41', 'value' => '60 кВт'],
            ['key' => 'COP A−12/W41', 'value' => '2,53'],
            ['key' => 'Потребляемая мощность A−12/W41', 'value' => '23,75 кВт'],
            ['key' => 'Мощность охлаждения A35/W7', 'value' => '67,5 кВт'],
            ['key' => 'EER A35/W7', 'value' => '2,82'],
            ['key' => 'Потребляемая мощность при охлаждении A35/W7', 'value' => '23,94 кВт'],
            ['key' => 'Рабочая температура воздуха', 'value' => 'от −35 до +45 °C'],
            ['key' => 'Питание', 'value' => '380 В, 3 фазы, 50 Гц'],
            ['key' => 'Хладагент', 'value' => 'R32 / R410A'],
            ['key' => 'Максимальная потребляемая мощность', 'value' => '35 кВт'],
            ['key' => 'Максимальный ток', 'value' => '54 А'],
            ['key' => 'Уровень шума', 'value' => 'не более 68 дБ(А)'],
            ['key' => 'Номинальный расход воды', 'value' => '15,5 м³/ч'],
            ['key' => 'Гидравлическое сопротивление', 'value' => '60 кПа'],
            ['key' => 'Подключение воды', 'value' => 'DN65, фланец'],
            ['key' => 'Компрессор', 'value' => 'Panasonic, инверторный с EVI'],
            ['key' => 'Теплообменник', 'value' => 'пластинчатый Danfoss'],
            ['key' => 'Четырёхходовой клапан', 'value' => 'Saginomiya / Sanhua'],
            ['key' => 'Электронный расширительный вентиль', 'value' => 'Danfoss'],
            ['key' => 'Датчики давления', 'value' => 'Saginomiya'],
            ['key' => 'Габариты', 'value' => '1606 × 718 × 2208 мм'],
            ['key' => 'Вес', 'value' => '450 кг'],
            ['key' => 'Каскад', 'value' => 'модульное объединение нескольких агрегатов'],
        ];
    }

    private function content(): string
    {
        return <<<'HTML'
<p><strong>KOTLOV GE NL-DCB30K</strong> — коммерческий инверторный тепловой насос воздух-вода с технологией EVI. Тепловая мощность 90 кВт при +7 °C и 60 кВт при −12 °C позволяет отапливать и охлаждать крупные объекты: гостиницы, офисы, магазины, производственные и складские помещения.</p>

<h2>Работа в сильный мороз</h2>
<p>Компрессор Panasonic с впрыском пара (EVI) и полный инвертор сохраняют производительность при низких температурах: агрегат работает при наружном воздухе до −35 °C, а интеллектуальная оттайка включается только тогда, когда она действительно нужна.</p>

<h2>Основные возможности NL-DCB30K</h2>
<ul>
    <li>90 кВт тепла при A7/W45, COP 3,55;</li>
    <li>60 кВт тепла при A−12/W41, COP 2,53;</li>
    <li>охлаждение 67,5 кВт при A35/W7;</li>
    <li>пластинчатый теплообменник и электронный расширительный вентиль Danfoss;</li>
    <li>подключение DN65 на фланце, расход воды 15,5 м³/ч;</li>
    <li>модульное объединение нескольких агрегатов в каскад для большой нагрузки.</li>
</ul>

<h2>Подбор и монтаж KOTLOV.BY</h2>
<p>Перед заказом специалисты KOTLOV проверят теплопотери здания, температурный график, доступную электрическую мощность (до 35 кВт, 380 В) и гидравлическую схему с буферной ёмкостью. По результатам подготовим коммерческое предложение на оборудование и <a href="/montazh-teplovyh-nasosov">монтаж теплового насоса под ключ</a>.</p>

<blockquote>Мощность указана по данным производителя для режимов A7/W45 и A−12/W41. Фактическая производительность зависит от температуры наружного воздуха и температуры воды в системе.</blockquote>
HTML;
    }
};
