<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const IMAGE = 'banners/pellet-burner-clearance-desktop.svg';

    public function up(): void
    {
        DB::table('banners')->updateOrInsert(
            ['image' => self::IMAGE, 'position' => 'hero'],
            [
                'title' => 'Большая распродажа пеллетных горелок',
                'subtitle' => 'СКЛАДСКИЕ ОСТАТКИ · ОГРАНИЧЕННОЕ КОЛИЧЕСТВО',
                'description' => 'Ceramic PRO 100 кВт −10%, EVO 26 кВт −20% и комплекты HOTTA Ceramik 20/30 кВт от 4 300 BYN. Количество акционных товаров ограничено.',
                'image_mobile' => 'banners/pellet-burner-clearance-mobile.svg',
                'link' => '/akcii',
                'button_text' => 'Смотреть все предложения',
                'sort_order' => -100,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Cache::forget('home:data');
    }

    public function down(): void
    {
        DB::table('banners')
            ->where('image', self::IMAGE)
            ->where('position', 'hero')
            ->delete();

        Cache::forget('home:data');
    }
};
