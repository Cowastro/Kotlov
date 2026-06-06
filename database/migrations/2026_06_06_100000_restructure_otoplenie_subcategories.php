<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Move all subcategories from old id=56 ("Для отопления") to id=301 (top-level Отопление)
        // and set sort_order for 3-column mega menu layout

        // Column 1: Радиаторы и трубы
        $col1 = [
            87  => 10,  // Радиаторы
            193 => 20,  // Трубы и фитинги
            109 => 30,  // Теплый пол
            281 => 40,  // Электрические конвекторы
        ];

        // Column 2: Котельное оборудование
        $col2 = [
            60  => 50,  // Циркуляционные насосы
            196 => 60,  // Группы быстрого монтажа котельных
            89  => 70,  // Мембранные баки
            91  => 80,  // Буферные емкости
            102 => 90,  // Аккумулирующие баки
            93  => 100, // Гребенки
            242 => 110, // Гидравлические разделители и коллекторы
        ];

        // Column 3: Автоматика и комплектующие
        $col3 = [
            58  => 120, // Автоматика и терморегуляторы
            59  => 130, // Стабилизаторы напряжения
            86  => 140, // Датчики температуры
            106 => 150, // Сигнализаторы загазованности
            96  => 160, // Счетчики газа
            195 => 170, // Комплектующие для отопления
            296 => 180, // Теплоносители (Антифриз)
            203 => 190, // Системы водоподготовки и фильтры
        ];

        // Hidden (too few products)
        $hide = [94, 200, 267, 299]; // Солнечные коллекторы, ИБП, Регуляторы газа, ТЭНы

        $allActive = $col1 + $col2 + $col3;

        foreach ($allActive as $id => $sort) {
            DB::table('categories')->where('id', $id)->update([
                'parent_id'  => 301,
                'sort_order' => $sort,
                'is_active'  => 1,
                'updated_at' => now(),
            ]);
        }

        // Move hidden ones to 301 but keep is_active=0
        $allHidden = array_merge($hide, [85, 202, 280, 285, 300]); // also already-hidden from id=56
        foreach ($allHidden as $id) {
            DB::table('categories')->where('id', $id)->update([
                'parent_id'  => 301,
                'is_active'  => 0,
                'updated_at' => now(),
            ]);
        }

        // Hide the old id=56 node itself (now empty)
        DB::table('categories')->where('id', 56)->update([
            'is_active'  => 0,
            'updated_at' => now(),
        ]);

        // Update navigation.php columns config: otoplenie → 3 columns
        // (handled in config/navigation.php separately)
    }

    public function down(): void
    {
        $allIds = [87,193,109,281,60,196,89,91,102,93,242,58,59,86,106,96,195,296,203,94,200,267,299,85,202,280,285,300];
        foreach ($allIds as $id) {
            DB::table('categories')->where('id', $id)->update([
                'parent_id'  => 56,
                'updated_at' => now(),
            ]);
        }
        DB::table('categories')->where('id', 56)->update(['is_active' => 1, 'updated_at' => now()]);
    }
};
