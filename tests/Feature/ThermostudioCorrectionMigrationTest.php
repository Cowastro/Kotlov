<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThermostudioCorrectionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_the_rrp_supplier_article_and_phone_contact_idempotently(): void
    {
        $category = Category::query()->create([
            'name' => 'Электрические',
            'slug' => 'electric',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Водонагреватель Ariston PRO1 R 50 V PL',
            'slug' => 'vodonagrevatel-ariston-pro1-r-50-v-pl',
            'sku' => 'PS-000.17381',
            'price' => 345,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'thermostudio'], [
            'name' => 'ООО «Термостудия»',
            'contact' => 'https://docs.google.com/spreadsheets/d/example/edit',
            'is_active' => true,
        ]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article' => 'PS-000.17381',
            'supplier_article_normalized' => 'PS-000.17381',
            'price' => 345,
            'price_byn' => 345,
        ]);

        $migration = require database_path('migrations/2026_10_18_180000_correct_thermostudio_pro1_r_50_rrp_and_contact.php');
        $migration->up();
        $migration->up();

        $this->assertSame(430.0, (float) $product->fresh()->price);
        $this->assertSame(
            '+375 (29) 308-06-20 · +375 (17) 374-94-72 · +375 (17) 374-88-66',
            $supplier->fresh()->contact,
        );
        $this->assertSame('3700589', $offer->fresh()->supplier_article);
        $this->assertSame('3700589', $offer->fresh()->supplier_article_normalized);
        $this->assertSame('3700589', $offer->fresh()->supplier_article_compact);
        $this->assertSame(345.0, (float) $offer->fresh()->price_byn);
    }
}
