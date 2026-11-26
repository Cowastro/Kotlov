<?php

use App\Models\Category;
use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $parent = Category::where('slug', 'pelletnye-gorelki')->first();
            if (! $parent) {
                return;
            }

            $parts = Category::updateOrCreate(['slug' => 'zapchasti-dlya-pelletnyh-gorelok'], [
                'parent_id' => $parent->id,
                'name' => 'Запчасти и комплектующие к пеллетным горелкам',
                'type' => 'child',
                'is_active' => true,
            ]);

            $products = DB::table('products')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->whereIn('products.sku', ['KOTLOV-034535', 'KOTLOV-023119', 'KOTLOV-022713'])
                ->where('products.category_id', '!=', $parts->id)
                ->get(['products.id', 'products.slug', 'categories.slug as old_category']);

            foreach ($products as $product) {
                Redirect::updateOrCreate(['from_url' => "/{$product->old_category}/{$product->slug}"], [
                    'to_url' => "/{$parts->slug}/{$product->slug}",
                    'status_code' => 301,
                    'is_active' => true,
                ]);
                DB::table('products')->where('id', $product->id)->update([
                    'category_id' => $parts->id, 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Catalogue correction: preserve published product URLs.
    }
};
