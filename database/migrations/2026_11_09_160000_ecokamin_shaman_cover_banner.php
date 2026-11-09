<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The ЭкоКамин ШАМАН hero slide in the magazine-cover style of the ПРАЙМ slide: the sharp studio photo with the fire
 * set in (public/img/banners/ecokamin-shaman/shaman-cover.webp) and the copy of a model that is in stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('banners')->where('image', 'banners/invicta-shaman-launch.svg')->where('position', 'hero')->update([
            'subtitle' => 'Чугунный камин',
            'description' => 'На складе и готов к заказу. Высокий камин в духе легендарного Invicta Shaman: панорамная дверца, длительное горение, обогрев до 170 м².',
            'button_text' => 'Заказать ШАМАН',
            'updated_at' => now(),
        ]);
        Cache::forget('home:data');
    }

    public function down(): void
    {
    }
};
