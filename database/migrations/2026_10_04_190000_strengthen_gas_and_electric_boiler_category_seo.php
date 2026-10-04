<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->where('slug', 'gazovye')->update([
            'h1' => 'Газовые котлы',
            'meta_title' => 'Газовые котлы: цены, купить в %city% | KOTLOV',
            'meta_description' => 'Газовые котлы в наличии: одноконтурные, двухконтурные и конденсационные модели. Актуальные цены, доставка, монтаж и сервис по Беларуси.',
            'content' => '<p>Газовые котлы для отопления и горячего водоснабжения. Сравните мощность, число контуров, тип камеры сгорания и способ установки.</p>',
            'updated_at' => now(),
        ]);

        DB::table('categories')->where('slug', 'elektricheskie')->update([
            'h1' => 'Электрические котлы',
            'meta_title' => 'Электрические котлы: цены, купить в %city% | KOTLOV',
            'meta_description' => 'Электрические котлы для дома и резервного отопления. Актуальные цены и наличие, подбор мощности, доставка, монтаж и сервис по Беларуси.',
            'content' => '<p>Электрические котлы для основного и резервного отопления дома. Сравните мощность, площадь обогрева, число фаз и возможности автоматики.</p>',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', 'gazovye')
            ->where('meta_description', 'Газовые котлы в наличии: одноконтурные, двухконтурные и конденсационные модели. Актуальные цены, доставка, монтаж и сервис по Беларуси.')
            ->update(['h1' => 'Газовые котлы отопления', 'updated_at' => now()]);

        DB::table('categories')
            ->where('slug', 'elektricheskie')
            ->where('meta_description', 'Электрические котлы для дома и резервного отопления. Актуальные цены и наличие, подбор мощности, доставка, монтаж и сервис по Беларуси.')
            ->update(['h1' => 'Электрические отопительные котлы (электрокотлы, электротэны)', 'updated_at' => now()]);
    }
};
