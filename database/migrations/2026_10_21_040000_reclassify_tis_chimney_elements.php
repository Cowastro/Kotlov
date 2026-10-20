<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return;
        }

        $categories = DB::table('categories')
            ->whereIn('slug', $this->categorySlugs())
            ->pluck('id', 'slug');

        $productIds = DB::table('products')
            ->where('sku', 'like', 'TIS30-%')
            ->orWhere('sku', 'like', 'TIS50-%')
            ->pluck('id');

        if (Schema::hasTable('supplier_products')) {
            $productIds = $productIds->merge(
                DB::table('supplier_products')
                    ->whereNotNull('product_id')
                    ->where(function ($query): void {
                        $query->where('supplier_article', 'like', 'TIS30-%')
                            ->orWhere('supplier_article', 'like', 'TIS50-%');
                    })
                    ->pluck('product_id'),
            );
        }

        DB::table('products')
            ->whereIn('id', $productIds->filter()->unique()->values())
            ->orderBy('id')
            ->select(['id', 'name', 'category_id'])
            ->chunkById(200, function ($products) use ($categories): void {
                foreach ($products as $product) {
                    $slug = $this->categorySlug((string) $product->name);
                    $categoryId = (int) ($categories[$slug] ?? 0);

                    if ($categoryId && (int) $product->category_id !== $categoryId) {
                        DB::table('products')->where('id', $product->id)->update([
                            'category_id' => $categoryId,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }, 'id');

        Cache::forget('home:data');
        Cache::forget('home:data:v3');
    }

    public function down(): void
    {
        // This repairs incorrect catalogue classification and must stay applied.
    }

    private function categorySlug(string $name): string
    {
        $lower = mb_strtolower($name);
        $thermo = str_contains($lower, 'термо');

        if (str_contains($lower, 'шибер') || str_contains($lower, 'задвиж')) {
            return 'shibery-dymohod';
        }
        if (str_contains($lower, 'конденсат') || str_contains($lower, 'заглушка ревизии')) {
            return 'kondensatootvody';
        }
        if (str_contains($lower, 'зонт') || str_contains($lower, 'дефлектор')) {
            return 'zonty-deflektory';
        }
        if (str_contains($lower, 'адаптер') || str_contains($lower, 'переход') || str_contains($lower, 'конус')) {
            return 'perehody-adaptery-dymohod';
        }
        if (str_contains($lower, 'тройник') || str_contains($lower, 'четверик')) {
            return $thermo ? 'troyniki-sendvich' : 'troyniki-mono';
        }
        if (str_contains($lower, 'отвод') || str_contains($lower, 'колено')) {
            return $thermo ? 'kolena-sendvich' : 'kolena-mono';
        }
        if (str_contains($lower, 'труба')) {
            return $thermo ? 'truby-sendvich' : 'truby-mono';
        }
        if (str_contains($lower, 'площадка') || str_contains($lower, 'хомут')
            || str_contains($lower, 'креплен') || str_contains($lower, 'кронштейн')
            || str_contains($lower, 'опора')) {
            return 'krepleniya-dymohod';
        }

        return 'prochie-dymohod';
    }

    private function categorySlugs(): array
    {
        return [
            'truby-mono', 'troyniki-mono', 'kolena-mono',
            'truby-sendvich', 'troyniki-sendvich', 'kolena-sendvich',
            'shibery-dymohod', 'kondensatootvody', 'krepleniya-dymohod',
            'zonty-deflektory', 'perehody-adaptery-dymohod', 'prochie-dymohod',
        ];
    }
};
