<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // id=69: Дровяные печи — добавить slug и активировать в меню
        DB::table('categories')->where('id', 69)->update([
            'slug'       => 'drovyanye-pechi-dlya-bani',
            'is_active'  => 1,
            'sort_order' => 10,
            'updated_at' => now(),
        ]);

        // id=70: Электрические печи → Электрокаменки — slug и активировать
        DB::table('categories')->where('id', 70)->update([
            'name'       => 'Электрокаменки',
            'slug'       => 'elektrokamenki',
            'is_active'  => 1,
            'sort_order' => 20,
            'updated_at' => now(),
        ]);

        // id=294: Аксессуары для бани — перенести из-под id=52 под id=73
        DB::table('categories')->where('id', 294)->update([
            'parent_id'  => 73,
            'updated_at' => now(),
        ]);

        // Скрыть пустые: Для сауны (216), Каменка (217), Категория 292
        DB::table('categories')->whereIn('id', [216, 217, 292])->update([
            'is_active'  => 0,
            'updated_at' => now(),
        ]);

        // id=291: уточнить название
        DB::table('categories')->where('id', 291)->update([
            'name'       => 'Дровницы и каминные принадлежности',
            'updated_at' => now(),
        ]);

        // Редиректы для старых URL электрических печей и дровяных
        DB::table('redirects')->insertOrIgnore([
            ['from_url' => '/pechi-dlya-bani/dlya-sauny',  'to_url' => '/pechi-dlya-bani', 'status_code' => 301, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['from_url' => '/pechi-dlya-bani/kamenka',     'to_url' => '/pechi-dlya-bani', 'status_code' => 301, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->where('id', 69)->update([
            'slug' => null, 'is_active' => 1, 'sort_order' => 0, 'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', 70)->update([
            'name' => 'Электрические печи', 'slug' => null, 'is_active' => 1, 'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', 294)->update([
            'parent_id' => 52, 'updated_at' => now(),
        ]);
        DB::table('categories')->whereIn('id', [216, 217, 292])->update([
            'is_active' => 1, 'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', 291)->update([
            'name' => 'Дровницы', 'updated_at' => now(),
        ]);
        DB::table('redirects')->whereIn('from_url', ['/pechi-dlya-bani/dlya-sauny', '/pechi-dlya-bani/kamenka'])->delete();
    }
};
