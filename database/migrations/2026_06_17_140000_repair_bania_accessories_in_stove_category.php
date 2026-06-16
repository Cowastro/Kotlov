<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplierId = (int) DB::table('suppliers')->where('code', 'bania')->value('id');
        $stoveCategoryId = (int) DB::table('categories')->where('slug', 'drovyanye-pechi-dlya-bani')->value('id');
        $accessoriesId = (int) DB::table('categories')->where('slug', 'aksessuary-dlya-bani')->value('id');

        if ($supplierId <= 0 || $stoveCategoryId <= 0 || $accessoriesId <= 0) {
            return;
        }

        $needles = [
            'ведро',
            'система регулировки обручей',
            'моющее средство',
            'средство для бани',
            'коврик',
            'мочалка',
            'шапка для бани',
            'шапка для сауны',
        ];

        $ids = DB::table('products as p')
            ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.category_id', $stoveCategoryId)
            ->where(function ($query) use ($needles) {
                foreach ($needles as $needle) {
                    $like = '%' . $needle . '%';
                    $query->orWhereRaw('LOWER(p.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(sp.supplier_name) LIKE ?', [$like]);
                }
            })
            ->pluck('p.id')
            ->all();

        if ($ids === []) {
            return;
        }

        DB::table('products')->whereIn('id', $ids)->update([
            'category_id' => $accessoriesId,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data repair migration: intentionally not reversible.
    }
};
