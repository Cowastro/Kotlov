<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BRANDS = [
        ['name' => 'Firelight',   'slug' => 'firelight'],
        ['name' => 'SHUFT',       'slug' => 'shuft'],
        ['name' => 'Varmega',     'slug' => 'varmega'],
        ['name' => 'НЗС',         'slug' => 'nzs'],
        ['name' => 'Toshiba',     'slug' => 'toshiba'],
        ['name' => 'AC Electric', 'slug' => 'ac-electric'],
        ['name' => 'Hommyn',      'slug' => 'hommyn'],
        ['name' => 'Boneco',      'slug' => 'boneco'],
        ['name' => 'XOMMET',      'slug' => 'xommet'],
        ['name' => 'Energolux',   'slug' => 'energolux'],
        ['name' => 'ONE AIR',     'slug' => 'one-air'],
        ['name' => 'HUBERT',      'slug' => 'hubert'],
        ['name' => 'Zanussi',     'slug' => 'zanussi'],
        ['name' => 'Джилекс',     'slug' => 'dzhileks'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::BRANDS as $brand) {
            // Skip if a brand with this slug or name already exists
            $exists = DB::table('brands')
                ->where('slug', $brand['slug'])
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($brand['name'])])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('brands')->insert([
                'name'       => $brand['name'],
                'slug'       => $brand['slug'],
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('brands')
            ->whereIn('slug', array_column(self::BRANDS, 'slug'))
            ->delete();
    }
};
