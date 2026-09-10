<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'teplovyie-nasosyi';

    public function up(): void
    {
        DB::table('categories')->where('slug', self::SLUG)->update([
            'h1' => 'Тепловые насосы воздух-вода',
            'content' => '<p>Тепловые насосы воздух-вода KOTLOV GE для отопления, охлаждения и горячего водоснабжения. Подберём модель R32 или R290 по теплопотерям дома, температуре подачи, тёплому полу или радиаторам.</p>',
            'meta_title' => 'Тепловые насосы воздух-вода в %city% — цены | KOTLOV',
            'meta_description' => 'Тепловые насосы KOTLOV GE R32 и R290 для дома. Цены, характеристики, подбор по теплопотерям, доставка и монтаж в %city% и по Беларуси.',
            'meta_keywords' => 'тепловые насосы воздух-вода, тепловой насос для дома, тепловой насос R32, тепловой насос R290, монтаж теплового насоса',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', self::SLUG)->update([
            'h1' => 'Тепловые насосы воздух-вода',
            'meta_title' => 'Тепловые насосы для отопления в %city% - каталог, цены. Тепловые насосы по цене производителя - Kotlov.by',
            'meta_description' => 'Тепловые насосы для отопления дома от производителя Hotta. Проконсультируем по выбору теплового насоса для отопления дома, подберем специалиста по монтажу, доставим тепловой насос в %city%. Звоните!',
            'meta_keywords' => 'тепловой насос, тепловой насос для отопления, тепловой насос для отопления дома, тепловой насос вода-воздух',
            'updated_at' => now(),
        ]);
    }
};
