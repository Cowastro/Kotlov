<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->where('slug', 'stabilizatory-napryazheniya')->update([
            'h1' => 'Стабилизаторы напряжения для котла и дома',
            'meta_title' => 'Стабилизаторы напряжения — купить в %city%, цены | KOTLOV',
            'meta_description' => 'Стабилизаторы напряжения для котла и дома: цены, мощность, число фаз и установка. Сравните модели и наличие. Подбор и доставка по Беларуси.',
            'meta_keywords' => null,
            'content' => '<p>Стабилизаторы напряжения для газового котла, бытовой техники и электроснабжения дома. Сравните мощность в ВА и Вт, число фаз, диапазон входного напряжения и способ установки.</p>',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep corrected editorial content rather than restoring inaccurate claims.
    }
};
