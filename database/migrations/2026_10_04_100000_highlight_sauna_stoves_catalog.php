<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('slug', 'bani-i-sauny')
            ->update([
                'h1' => 'Бани и сауны',
                'meta_title' => 'Печи и оборудование для бани — купить в %city% | KOTLOV',
                'meta_description' => 'Банные печи, электрокаменки, баки, двери и аксессуары для парной. Цены, наличие, доставка по Беларуси и помощь с подбором.',
                'updated_at' => now(),
            ]);

        DB::table('categories')
            ->where('slug', 'pechi-dlya-bani')
            ->update([
                'name' => 'Аксессуары для парной',
                'sort_order' => 80,
                'updated_at' => now(),
            ]);

        DB::table('categories')
            ->whereIn('slug', ['drovyanye-pechi-dlya-bani', 'drovianye-peci-bannye'])
            ->update([
                'name' => 'Печи для бани',
                'h1' => 'Печи для бани на дровах',
                'sort_order' => 10,
                'meta_title' => 'Печи для бани на дровах — купить в %city% | KOTLOV',
                'meta_description' => 'Дровяные и чугунные печи для бани и сауны. Подбор по объёму парной, материалу, каменке и выносу топки. Доставка по Беларуси.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', 'bani-i-sauny')
            ->update([
                'h1' => null,
                'meta_title' => 'Бани и сауны — купить в %city% | KOTLOV',
                'meta_description' => 'Купить бани и сауны в %city% по выгодным ценам. Большой выбор в каталоге kotlov.by. Быстрая доставка по всей Беларуси, профессиональные консультации и гарантия качества.',
                'updated_at' => now(),
            ]);

        DB::table('categories')
            ->where('slug', 'pechi-dlya-bani')
            ->update([
                'name' => 'Для бани',
                'sort_order' => 10,
                'updated_at' => now(),
            ]);

        DB::table('categories')
            ->whereIn('slug', ['drovyanye-pechi-dlya-bani', 'drovianye-peci-bannye'])
            ->update([
                'name' => 'Дровяные печи',
                'h1' => 'Дровяные печи для бани',
                'sort_order' => 20,
                'meta_title' => 'Купить дровяные печи для бани в %city%. Печи для бани на дровах с доставкой по РБ - каталог с ценами на Kotlov.by. ',
                'meta_description' => 'Каталог дровяных печей для бань. Печи на дровах длительного горения для бани. Печи для русской бани, с закрытой топкой и теплооюменником. Специалисты нашего салона помогут вам выбрать и купить дровяную печь в баню, доставим  в %city% и в другие города РБ.',
                'updated_at' => now(),
            ]);
    }
};
