<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('slug', 'bufernye-emkosti')
            ->update([
                'h1' => 'Буферные ёмкости и теплоаккумуляторы',
                'meta_title' => 'Буферные ёмкости — купить в %city% | KOTLOV',
                'meta_description' => 'Буферные ёмкости и теплоаккумуляторы для котлов и систем отопления. Цены, доставка по Беларуси, гарантия и помощь с подбором объёма.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', 'bufernye-emkosti')
            ->update([
                'h1' => 'Буферные емкости (теплоаккумуляторы)',
                'meta_title' => 'Буферные емкости (теплоаккумуляторы) для систем отопления и твердотопливных котлов. Цены в Минске и регионах, доставка',
                'meta_description' => 'Продажа теплоаккумуляторов для систем отопления дома. Цены на буферные емкости для твердотопливных котлов, отзывы. Доставка по Минску и всей РБ. ',
                'updated_at' => now(),
            ]);
    }
};
