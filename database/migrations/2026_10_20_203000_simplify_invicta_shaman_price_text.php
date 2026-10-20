<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'ecokamin-shaman-analog-legendarnogo-kamina';

    public function up(): void
    {
        DB::table('blog_posts')
            ->where('slug', self::ARTICLE_SLUG)
            ->update([
                'content' => DB::raw("REPLACE(content, 'Розничная цена в карточке товара пересчитывается в белорусские рубли по актуальному курсу. Поскольку первая партия ещё в пути, наличие и срок доставки менеджер подтверждает при оформлении заказа.', 'Актуальная розничная цена указана в карточке товара. Поступление первой партии ожидается 26–30 октября 2026 года.')"),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Editorial correction is intentionally durable.
    }
};
