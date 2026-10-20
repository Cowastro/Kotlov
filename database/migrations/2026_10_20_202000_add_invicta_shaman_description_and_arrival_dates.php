<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SLUG = 'ecokamin-shaman-chugunnyy-kamin';
    private const ARTICLE_SLUG = 'ecokamin-shaman-analog-legendarnogo-kamina';
    private const BANNER_IMAGE = 'banners/invicta-shaman-launch.svg';

    public function up(): void
    {
        $productId = DB::table('products')->where('slug', self::PRODUCT_SLUG)->value('id');

        if ($productId) {
            DB::table('products')->where('id', $productId)->update([
                'short_description' => 'Чугунный камин ЭкоКамин ШАМАН мощностью 14 кВт — современный аналог легендарного Invicta Shaman. Для помещений до 170 м², с длительным горением, вторичным дожигом и системой очистки стекла.',
                'content' => $this->productContent(),
                'meta_description' => 'Чугунный камин ЭкоКамин ШАМАН 14 кВт — современный аналог легендарного Invicta Shaman. До 170 м², КПД 78,7%, длительное горение до 8 часов.',
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
            'excerpt' => 'Новый чугунный камин ЭкоКамин ШАМАН мощностью 14 кВт — современный аналог легендарного Invicta Shaman. Поступление первой партии ожидается 26–30 октября 2026 года.',
            'content' => DB::raw("REPLACE(content, 'на следующей неделе', '26–30 октября 2026 года')"),
            'meta_description' => 'Чугунный камин ЭкоКамин ШАМАН 14 кВт — современный аналог легендарного Invicta Shaman. До 170 м², КПД 78,7%, поступление 26–30 октября 2026 года.',
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
<p><strong>ЭкоКамин ШАМАН</strong> — чугунный камин российского производителя «ЭкоКамин». По формату, вертикальному силуэту и характеру модели это современный аналог легендарного <strong>Invicta Shaman</strong>, но не товар бренда Invicta.</p>

<p>Высокий вертикальный камин рассчитан на просторную гостиную, загородный дом или помещение с высоким потолком. Номинальная мощность 14 кВт подходит для отопления площади от 75 до 170 м².</p>

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
