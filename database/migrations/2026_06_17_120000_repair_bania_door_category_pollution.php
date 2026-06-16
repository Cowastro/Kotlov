<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplierId = (int) DB::table('suppliers')->where('code', 'bania')->value('id');
        if ($supplierId <= 0) {
            return;
        }

        $doorCategoryIds = DB::table('categories')
            ->whereIn('slug', ['dveri-dlya-ban-i-saun', 'dveri-dlya-bani-i-sauny'])
            ->pluck('id')
            ->all();

        if ($doorCategoryIds === []) {
            return;
        }

        $this->moveFromDoorCategory($supplierId, $doorCategoryIds, 'drovyanye-pechi-dlya-bani', [
            'печь',
            'печ бан',
            'былина',
            'сибирь',
        ]);

        $this->moveFromDoorCategory($supplierId, $doorCategoryIds, 'pechnoe-i-kaminnoe-lite', [
            'дверца',
            'дверка',
        ]);
    }

    public function down(): void
    {
        // Data repair migration: intentionally not reversible.
    }

    private function moveFromDoorCategory(int $supplierId, array $doorCategoryIds, string $targetSlug, array $needles): void
    {
        $targetId = (int) DB::table('categories')->where('slug', $targetSlug)->value('id');
        if ($targetId <= 0) {
            return;
        }

        $ids = DB::table('products as p')
            ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->where('sp.supplier_id', $supplierId)
            ->whereIn('p.category_id', $doorCategoryIds)
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
            'category_id' => $targetId,
            'updated_at' => now(),
        ]);
    }
};
