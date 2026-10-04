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

        DB::table('products')
            ->where('category_id', $stoveCategoryId)
            ->whereIn('slug', [
                'setka-na-trubu-fireway-kovka',
                'setka-na-trubu-fireway-kolchuga',
            ])
            ->update([
                'category_id' => $accessoriesId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Catalog repair: intentionally not reversed to avoid moving products
        // back into an incorrect category.
    }
};
