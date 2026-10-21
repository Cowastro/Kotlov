<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accessoriesId = DB::table('categories')
            ->where('slug', 'komplektuyushhie-dlya-otopleniya')
            ->value('id');

        if (! $accessoriesId) {
            return;
        }

        DB::table('categories')
            ->where('slug', 'smesitelnye-klapany-i-uzly')
            ->update([
                'parent_id' => $accessoriesId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Не возвращаем категорию в ошибочный раздел дренажных насосов.
    }
};
