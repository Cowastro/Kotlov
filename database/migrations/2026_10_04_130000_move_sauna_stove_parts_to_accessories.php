<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $stoveCategoryId = DB::table('categories')
            ->whereIn('slug', ['drovyanye-pechi-dlya-bani', 'drovianye-peci-bannye'])
            ->value('id');
        $accessoriesId = DB::table('categories')
            ->where('slug', 'aksessuary-dlya-bani')
            ->value('id');

        if (! $stoveCategoryId || ! $accessoriesId) {
            return;
        }

        $names = [
            'Чугунная нижняя труба для шибера ИСКАНДЕР 115/500',
            'ПУ для электрических банных печей TMF',
            'Дверка БЫЛИНА с обрамлением 16"',
            'Ермак Короб для камней ERMAK CUBE 16',
            'Ермак Портал ERMAK CUBE 16 Comfort L160',
            'Ермак Портал ERMAK CUBE 16 Comfort L250',
            'Ермак Экономайзер ERMAK BLACK L500 (INOX-304)',
            'Ермак Экономайзер ERMAK CHROM L500 (INOX-430)',
        ];

        DB::table('products')
            ->where('category_id', $stoveCategoryId)
            ->whereIn('name', $names)
            ->update([
                'category_id' => $accessoriesId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Catalog repair: intentionally not reversed.
    }
};
