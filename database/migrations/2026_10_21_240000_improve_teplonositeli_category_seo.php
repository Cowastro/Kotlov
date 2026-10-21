<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->where('slug', 'teplonositeli')->update([
            'h1' => 'Теплоносители (антифриз) для систем отопления',
            'meta_description' => 'Теплоносители для отопления в %city%: этиленгликоль и пропиленгликоль, −30 и −65 °C, концентраты 10 и 20 кг. Как выбрать и рассчитать объём. Доставка по Беларуси.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Content-only change; the previous texts are not restored.
    }
};
