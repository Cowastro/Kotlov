<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $solidCategoryId = (int) DB::table('categories')->where('slug', 'tverdotoplivnye')->value('id');
        $pelletCategoryId = (int) DB::table('categories')->where('slug', 'kotly-na-pelletah')->value('id');

        if (! $solidCategoryId) {
            return;
        }

        if (! $pelletCategoryId) {
            $pelletCategoryId = DB::table('categories')->insertGetId([
                'parent_id' => $solidCategoryId,
                'name' => 'Котлы на пеллетах',
                'slug' => 'kotly-na-pelletah',
                'content' => '<p>Пеллетные котлы — автоматизированные твердотопливные котлы, работающие на древесных пеллетах. В разделе собраны модели для дома, коттеджа и котельных с резервным или основным отоплением.</p>',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('categories')->where('id', $pelletCategoryId)->update([
            'parent_id' => $solidCategoryId,
            'name' => 'Котлы на пеллетах',
            'slug' => 'kotly-na-pelletah',
            'content' => '<p>Пеллетные котлы — удобное решение для отопления на древесных пеллетах. В каталоге KOTLOV.BY собраны автоматические котлы для дома, коттеджа и объектов, где важны автономность, понятное обслуживание и стабильная работа зимой.</p>',
            'meta_title' => 'Пеллетные котлы купить в Беларуси | KOTLOV.BY',
            'meta_description' => 'Котлы на пеллетах для отопления дома и котельной. Подбор мощности, монтаж, дымоход, автоматика и доставка по Беларуси.',
            'meta_keywords' => 'пеллетные котлы, котлы на пеллетах, купить пеллетный котел, пеллетный котел беларусь',
            'sort_order' => 20,
            'is_active' => true,
            'updated_at' => $now,
        ]);

        DB::table('products')
            ->where('category_id', $solidCategoryId)
            ->where(function ($query) {
                $query->where('name', 'like', '%пеллетн%')
                    ->orWhere('name', 'like', '%PELLET%')
                    ->orWhere('name', 'like', '%EkoPELL%')
                    ->orWhere('slug', 'like', '%pellet%')
                    ->orWhere('slug', 'like', '%pelletny%');
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%котел%')
                    ->orWhere('name', 'like', '%котёл%')
                    ->orWhere('name', 'like', '%kotel%');
            })
            ->update([
                'category_id' => $pelletCategoryId,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        //
    }
};
