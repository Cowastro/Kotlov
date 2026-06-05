<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // attr id=434 "Тип элемента" (select, in_filter=1) был привязан к cat=78
    // (старая категория "Дымоходы", скрытая, parent_id=303).
    // 890 дымоходных товаров имеют значения этого атрибута, но фильтр
    // не появлялся на /dymohody т.к. контроллер ищет атрибуты по category_id.
    // Перепривязываем на cat=303 (/dymohody) — корневую группу дымоходов.

    public function up(): void
    {
        DB::table('attributes')
            ->where('id', 434)
            ->update(['category_id' => 303, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('id', 434)
            ->update(['category_id' => 78, 'updated_at' => now()]);
    }
};
