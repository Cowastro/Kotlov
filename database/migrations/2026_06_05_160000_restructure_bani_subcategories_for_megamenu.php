<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Поднимаем популярные подкатегории прямо под Бани и сауны (id=301)
    // чтобы они появились в мегаменю (шаблон берёт только прямых потомков)
    public function up(): void
    {
        // Дровяные печи для бани (669 тов): parent 52 → 301
        DB::table('categories')->where('id', 69)->update([
            'parent_id'  => 301,
            'sort_order' => 10,
            'updated_at' => now(),
        ]);

        // Электрокаменки (81 тов): parent 52 → 301
        DB::table('categories')->where('id', 70)->update([
            'parent_id'  => 301,
            'sort_order' => 20,
            'updated_at' => now(),
        ]);

        // Баки для воды (49 тов): parent 73 → 301
        DB::table('categories')->where('id', 74)->update([
            'parent_id'  => 301,
            'sort_order' => 40,
            'updated_at' => now(),
        ]);

        // Двери для бани и сауны (15 тов): parent 73 → 301
        DB::table('categories')->where('id', 72)->update([
            'parent_id'  => 301,
            'sort_order' => 50,
            'updated_at' => now(),
        ]);

        // Мангалы (27 тов): parent 73 → 301
        DB::table('categories')->where('id', 295)->update([
            'parent_id'  => 301,
            'sort_order' => 60,
            'updated_at' => now(),
        ]);

        // Аксессуары для бани (73) — catch-all, оставляем в конце
        DB::table('categories')->where('id', 73)->update([
            'sort_order' => 70,
            'updated_at' => now(),
        ]);

        // Печи для бани (52) — теперь только контейнер без своих товаров, скрываем
        DB::table('categories')->where('id', 52)->update([
            'is_active'  => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->where('id', 69)->update(['parent_id' => 52, 'sort_order' => 10, 'updated_at' => now()]);
        DB::table('categories')->where('id', 70)->update(['parent_id' => 52, 'sort_order' => 20, 'updated_at' => now()]);
        DB::table('categories')->where('id', 74)->update(['parent_id' => 73, 'updated_at' => now()]);
        DB::table('categories')->where('id', 72)->update(['parent_id' => 73, 'updated_at' => now()]);
        DB::table('categories')->where('id', 295)->update(['parent_id' => 73, 'updated_at' => now()]);
        DB::table('categories')->where('id', 52)->update(['is_active' => 1, 'updated_at' => now()]);
    }
};
