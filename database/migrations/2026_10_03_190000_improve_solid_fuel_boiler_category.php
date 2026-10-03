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
                'h1' => 'Твердотопливные котлы',
                'meta_title' => 'Твердотопливные котлы — купить в %city% | KOTLOV',
                'meta_description' => 'Твердотопливные котлы для дома: модели на дровах, угле и пеллетах. Актуальные цены, доставка по Беларуси, гарантия и помощь с подбором мощности.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', 'tverdotoplivnye')
            ->update([
                'h1' => 'Котлы твердотопливные',
                'meta_title' => 'Твердотопливные котлы - каталог, цены, описания. Доставка котлов отопления на твердом топливе в %city%, монтаж и настройка по всей РБ.',
                'meta_description' => 'Уважаемые посетители - рады предложить Вам большой каталог котлов на твердом топливе (газогенераторных). Вы можете выбрать и купить у нас котел отопления на твердом топливе по низким ценам с доставкой. Осуществляем доставку и подключение в %city% и других городах страны. ',
                'updated_at' => now(),
            ]);
    }
};
