<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('slug', 'tverdotoplivnye')
            ->update([
                'meta_title' => 'Твердотопливные котлы: цены, купить в %city% | KOTLOV',
                'meta_description' => 'Твердотопливные котлы для дома: актуальные цены и наличие. Модели на дровах, угле и брикетах, подбор мощности, доставка и монтаж по Беларуси.',
                'content' => '<p>Твердотопливные котлы для отопления дома и котельной на дровах, угле и топливных брикетах. Сравните мощность, площадь обогрева, объём загрузочной камеры и уровень автоматизации.</p>',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', 'tverdotoplivnye')
            ->where('meta_description', 'Твердотопливные котлы для дома: актуальные цены и наличие. Модели на дровах, угле и брикетах, подбор мощности, доставка и монтаж по Беларуси.')
            ->update([
                'meta_title' => 'Твердотопливные котлы — купить в %city% | KOTLOV',
                'meta_description' => 'Твердотопливные котлы для дома: модели на дровах, угле и пеллетах. Актуальные цены, доставка по Беларуси, гарантия и помощь с подбором мощности.',
                'updated_at' => now(),
            ]);
    }
};
