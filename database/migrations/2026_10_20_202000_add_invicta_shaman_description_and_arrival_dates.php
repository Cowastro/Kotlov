<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SLUG = 'invicta-shaman-chugunnyy-kamin';
    private const ARTICLE_SLUG = 'invicta-shaman-novinka-dostupen-k-zakazu';
    private const BANNER_IMAGE = 'banners/invicta-shaman-launch.svg';

    public function up(): void
    {
        $productId = DB::table('products')->where('slug', self::PRODUCT_SLUG)->value('id');

        if ($productId) {
            DB::table('products')->where('id', $productId)->update([
                'short_description' => 'Вертикальный чугунный камин Invicta Shaman мощностью 14 кВт для отопления помещений площадью до 170 м². Длительное горение до 8 часов, вторичный дожиг и система очистки стекла.',
                'content' => $this->productContent(),
                'meta_description' => 'Чугунный камин Invicta Shaman 14 кВт для помещений до 170 м². КПД 78,7%, длительное горение до 8 часов. Поступление 26–30 октября 2026 года.',
                'updated_at' => now(),
            ]);

            DB::table('supplier_products')->where('product_id', $productId)->update([
                'stock_text' => 'Доступен к заказу. Поступление первой партии ожидается 26–30 октября 2026 года',
                'updated_at' => now(),
            ]);
        }

        DB::table('banners')
            ->where('image', self::BANNER_IMAGE)
            ->where('position', 'hero')
            ->update([
                'description' => 'Чугунный камин мощностью 14 кВт. Поступление первой партии ожидается 26–30 октября 2026 года.',
                'updated_at' => now(),
            ]);

        DB::table('blog_posts')->where('slug', self::ARTICLE_SLUG)->update([
            'excerpt' => 'Новый чугунный камин Invicta Shaman мощностью 14 кВт доступен к заказу в Беларуси. Поступление первой партии ожидается 26–30 октября 2026 года.',
            'content' => DB::raw("REPLACE(content, 'на следующей неделе', '26–30 октября 2026 года')"),
            'meta_description' => 'Чугунный камин Invicta Shaman 14 кВт уже доступен к заказу в Беларуси. Обогрев до 170 м², КПД 78,7%, длительное горение до 8 часов. Поступление 26–30 октября 2026 года.',
            'updated_at' => now(),
        ]);

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // Text correction is intentionally durable.
    }

    private function productContent(): string
    {
        return <<<'HTML'
<p><strong>Invicta Shaman</strong> — высокий вертикальный камин из чугуна для просторной гостиной, загородного дома или помещения с высоким потолком. Номинальная мощность 14 кВт рассчитана на отопление площади от 75 до 170 м².</p>

<p>Диапазон мощности от 9,5 до 21 кВт позволяет регулировать интенсивность обогрева. КПД модели составляет 78,7%. Корпус и футеровка выполнены из чугуна, а система вторичного дожига помогает эффективнее использовать топливо.</p>

<h3>Особенности модели</h3>
<ul>
    <li>номинальная мощность — 14 кВт;</li>
    <li>площадь обогрева — от 75 до 170 м²;</li>
    <li>КПД — 78,7%;</li>
    <li>длительное горение — до 8 часов;</li>
    <li>система вторичного дожига и подача воздуха на стекло;</li>
    <li>максимальная длина полена — 44 см;</li>
    <li>диаметр дымохода — 180 мм;</li>
    <li>габариты — 560 × 1200 × 578 мм;</li>
    <li>масса — 160 кг.</li>
</ul>

<p>Перед заказом специалисты KOTLOV.BY помогут проверить параметры дымохода, безопасные расстояния и необходимую мощность для конкретного дома. Поступление первой партии ожидается <strong>26–30 октября 2026 года</strong>.</p>
HTML;
    }
};
