<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const IMAGE = 'banners/pellet-burner-clearance-desktop.svg';

    public function up(): void
    {
        DB::table('banners')
            ->where('image', self::IMAGE)
            ->update([
                'description' => 'Скидки до 20%. Ceramic PRO 100 кВт — 12 960 BYN, EVO 26 кВт — 5 120 BYN, HOTTA Ceramik с интернет‑управлением — от 4 300 BYN.',
                'button_text' => 'Выбрать горелку со скидкой',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('banners')
            ->where('image', self::IMAGE)
            ->update([
                'description' => 'Ceramic PRO 100 кВт −10%, EVO 26 кВт −20% и комплекты HOTTA Ceramik 20/30 кВт от 4 300 BYN. Количество акционных товаров ограничено.',
                'button_text' => 'Смотреть все предложения',
                'updated_at' => now(),
            ]);
    }
};
