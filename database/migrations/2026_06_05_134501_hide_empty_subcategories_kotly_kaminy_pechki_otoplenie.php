<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Скрываем пустые подкатегории (0 товаров) — без удаления из БД.
    // Категории с товарами (267, 109, 299) не трогаем.

    private array $toHide = [
        // /kotly/gazovye — 9 пустых подкатегорий
        103, 206, 207, 208, 209, 221, 222, 223, 224,
        // /kotly/tverdotoplivnye — 7 пустых
        204, 205, 210, 211, 212, 213, 214,
        // /otoplenie — только id=280 (остальные уже скрыты)
        280,
        // /pechki — пустые подкатегории id=63
        64, 225, 226,
    ];

    public function up(): void
    {
        DB::table('categories')
            ->whereIn('id', $this->toHide)
            ->update(['is_active' => 0, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->whereIn('id', $this->toHide)
            ->update(['is_active' => 1, 'updated_at' => now()]);
    }
};
