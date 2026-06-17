<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $supplierId = (int) DB::table('suppliers')->where('code', 'bania')->value('id');
        if ($supplierId <= 0) {
            return;
        }

        $teplodarId = $this->ensureBrand('Теплодар', 'teplodar', $now);
        $nmkId = $this->ensureBrand('НМК', 'nmk', $now);

        $this->moveBaniaProductsToBrand($supplierId, $teplodarId, [
            'былина',
            'сиеста',
            'сибирский утес',
            'сибирский утёс',
        ]);

        $this->moveBaniaProductsToBrand($supplierId, $nmkId, [
            'сибирь',
        ]);
    }

    public function down(): void
    {
        // Data repair migration: intentionally not reversible.
    }

    private function moveBaniaProductsToBrand(int $supplierId, int $brandId, array $needles): void
    {
        $ids = DB::table('products as p')
            ->join('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->where('sp.supplier_id', $supplierId)
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
            'brand_id' => $brandId,
            'updated_at' => now(),
        ]);
    }

    private function ensureBrand(string $name, string $slug, $now): int
    {
        $brand = DB::table('brands')->where('slug', $slug)->orWhere('name', $name)->first();
        if ($brand) {
            DB::table('brands')->where('id', $brand->id)->update([
                'name' => $name,
                'slug' => $slug,
                'h1' => $brand->h1 ?: $name,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            return (int) $brand->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'h1' => $name,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
