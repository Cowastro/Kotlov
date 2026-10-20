<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRODUCT_SLUG = 'ecokamin-shaman-chugunnyy-kamin';
    private const ARTICLE_SLUG = 'ecokamin-shaman-analog-legendarnogo-kamina';

    public function up(): void
    {
        $productId = DB::table('products')->where('slug', self::PRODUCT_SLUG)->value('id');

        if ($productId) {
            DB::table('supplier_products')->where('product_id', $productId)->update([
                'stock_status' => 'preorder',
                'stock_text' => 'Доступен к заказу',
                'updated_at' => now(),
            ]);
        }

        $this->replaceContent('products', self::PRODUCT_SLUG, [
            '<p>Первая партия ожидается на складе поставщика в Москве <strong>26–30 октября 2026 года</strong>. Перед заказом специалисты KOTLOV.BY проверят расчётную тепловую нагрузку, параметры дымохода, основание и безопасные расстояния до горючих конструкций.</p>'
                => '<p><strong>Камин доступен к заказу.</strong></p>',
        ]);

        $this->replaceContent('blog_posts', self::ARTICLE_SLUG, [
            '<h2>Цена, поступление и заказ в Беларуси</h2>'
                => '<h2>Цена и заказ в Беларуси</h2>',
            '<p class="text text-body-1">Розничная цена на 20 октября 2026 года — <strong>5 414,64 BYN</strong>. Первая партия ожидается на складе поставщика в Москве <strong>26–30 октября 2026 года</strong>. Заказ можно оформить заранее; срок доставки в Беларусь менеджер подтвердит после резервирования.</p>'
                => '<p class="text text-body-1"><strong>ЭкоКамин ШАМАН доступен к заказу.</strong> Актуальная розничная цена указана в карточке товара.</p>',
        ]);

        Cache::forget('home:data');
    }

    public function down(): void
    {
        // Public availability wording is intentionally durable.
    }

    private function replaceContent(string $table, string $slug, array $replacements): void
    {
        $record = DB::table($table)->where('slug', $slug)->first();

        if (! $record) {
            return;
        }

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            (string) $record->content,
        );

        if ($content === (string) $record->content) {
            return;
        }

        DB::table($table)->where('id', $record->id)->update([
            'content' => $content,
            'updated_at' => now(),
        ]);
    }
};
