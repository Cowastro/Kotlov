<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Ликвидируем дубль "Радиаторы отопления" (id=252).
    // Оставляем дерево id=87 (/radiatory) как каноническое.
    // Маппинг подкатегорий:
    //   253 Алюминиевые  → 233 /alyuminievye-radiatory
    //   254 Стальные     → 235 /stalnye-radiatory
    //   255 Биметалл.    → 236 /bimetallicheskie-radiatory
    //   256 Чугунные     → 234 /chugunnye-radiatory

    private array $map = [
        253 => 233,
        254 => 235,
        255 => 236,
        256 => 234,
    ];

    public function up(): void
    {
        // 1. Перенести товары в канонические подкатегории
        foreach ($this->map as $from => $to) {
            DB::table('products')
                ->where('category_id', $from)
                ->update(['category_id' => $to]);
        }

        // 2. Удалить подкатегории дубля (257 — Панельные, пустая)
        DB::table('categories')->whereIn('id', [253, 254, 255, 256, 257])->delete();

        // 3. Удалить саму дубль-категорию id=252
        DB::table('categories')->where('id', 252)->delete();

        // 4. Добавить редирект /radiatoryi-otopleniya → /radiatory
        DB::table('redirects')->insertOrIgnore([
            'from_url'    => '/radiatoryi-otopleniya',
            'to_url'      => '/radiatory',
            'status_code' => 301,
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('redirects')->where('from_url', '/radiatoryi-otoplennika')->delete();

        DB::table('categories')->insert([
            ['id' => 252, 'parent_id' => 56, 'name' => 'Радиаторы отопления', 'slug' => 'radiatoryi-otopleniya', 'is_active' => 1, 'sort_order' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 253, 'parent_id' => 252, 'name' => 'Алюминиевые',        'slug' => 'alyuminievyie',        'is_active' => 1, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 254, 'parent_id' => 252, 'name' => 'Стальные',            'slug' => 'stalnyie',             'is_active' => 1, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 255, 'parent_id' => 252, 'name' => 'Биметаллические',     'slug' => 'bimetallicheskie',     'is_active' => 1, 'sort_order' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 256, 'parent_id' => 252, 'name' => 'Чугунные',            'slug' => 'chugunnyie',           'is_active' => 1, 'sort_order' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 257, 'parent_id' => 252, 'name' => 'Панельные радиаторы', 'slug' => 'panelnyie-radiatoryi', 'is_active' => 0, 'sort_order' => 50, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
