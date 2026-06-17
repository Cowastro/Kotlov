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

        $this->attachBathChildrenToRoot();
        $this->moveAstonProducts($supplierId);
    }

    public function down(): void
    {
        // Data repair migration: intentionally not reversible.
    }

    private function attachBathChildrenToRoot(): void
    {
        $baniId = DB::table('categories')->where('slug', 'bani-i-sauny')->value('id');
        if (! $baniId) {
            return;
        }

        foreach ([
            'dveri-dlya-ban-i-saun' => 60,
            'dveri-dlya-bani-i-sauny' => 60,
        ] as $slug => $sortOrder) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update([
                    'parent_id' => $baniId,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }
    }

    private function moveAstonProducts(int $supplierId): void
    {
        $brandId = (int) DB::table('brands')
            ->where('slug', 'aston')
            ->orWhere('name', 'ASTON')
            ->value('id');

        if ($brandId <= 0) {
            return;
        }

        $fireplaceId = (int) DB::table('categories')->where('slug', 'pechi-kaminy')->value('id');
        $saunaStoveId = (int) DB::table('categories')->where('slug', 'drovyanye-pechi-dlya-bani')->value('id');
        $accessoriesId = (int) DB::table('categories')->where('slug', 'aksessuary-dlya-bani')->value('id');

        if ($accessoriesId <= 0) {
            return;
        }

        if ($fireplaceId > 0) {
            $ids = $this->astonIds($supplierId, $brandId, $accessoriesId)
                ->where(function ($q) {
                    $q->where('p.name', 'like', '%Печь-Камин%')
                        ->orWhere('p.name', 'like', '%Печь Камин%')
                        ->orWhere('sp.supplier_name', 'like', '%Печь-Камин%')
                        ->orWhere('sp.supplier_name', 'like', '%Печь Камин%');
                })
                ->pluck('p.id')
                ->all();

            if ($ids !== []) {
                DB::table('products')->whereIn('id', $ids)->update([
                    'category_id' => $fireplaceId,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($saunaStoveId > 0) {
            $ids = $this->astonIds($supplierId, $brandId, $accessoriesId)
                ->where(function ($q) {
                    $q->where('p.name', 'like', '%Печь для бани%')
                        ->orWhere('sp.supplier_name', 'like', '%Печь для бани%');
                })
                ->pluck('p.id')
                ->all();

            if ($ids !== []) {
                DB::table('products')->whereIn('id', $ids)->update([
                    'category_id' => $saunaStoveId,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function astonIds(int $supplierId, int $brandId, int $accessoriesId)
    {
        return DB::table('products as p')
            ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.brand_id', $brandId)
            ->where('p.category_id', $accessoriesId);
    }
};
