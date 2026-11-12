<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The historical slug «pechi-dlya-bani» holds steam room accessories (ladles and the like), but its heading,
 * meta and text still spoke of bath stoves. The slug stays (old links), everything shown now says accessories.
 * Wood-fired bath stoves live in drovianye-peci-bannye / drovyanye-pechi-dlya-bani.
 */
return new class extends Migration
{
    public function up(): void
    {
        $name = 'Аксессуары для парной';
        DB::table('categories')->where('slug', 'pechi-dlya-bani')->update([
            'name' => $name,
            'h1' => $name,
            'meta_title' => 'Аксессуары для парной — купить в %city% | KOTLOV',
            'meta_description' => 'Аксессуары для парной: ковши, черпаки и другие банные мелочи. Купить в %city% с доставкой по Беларуси.',
            'content' => '<p>Аксессуары для парной — ковши, черпаки и другие мелочи, без которых баня не бывает удобной. '
                .'Длинный ковш позволяет подать воду на камни, не подходя близко к горячей печи.</p>'
                .'<p>Ищете саму печь? Смотрите <a href="/drovianye-peci-bannye">дровяные печи для бани</a> '
                .'и <a href="/elektrokamenki">электрокаменки</a> — там есть подбор по объёму парной.</p>',
            'updated_at' => now(),
        ]);
        Cache::flush();
    }

    public function down(): void {}
};
