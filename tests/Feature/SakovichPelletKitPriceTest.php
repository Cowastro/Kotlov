<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Suppliers\SakovichPelletKits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SakovichPelletKitPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_components_are_selected_by_supplier_article_and_form_retail_totals(): void
    {
        $supplier = Supplier::create(['name' => 'Сантехсклад', 'code' => 'santehsklad']);
        $category = Category::create(['name' => 'Котлы', 'slug' => 'sakovich-test']);
        $expected = ['KOTLOV-000320' => '12240.00', 'KOTLOV-000301' => '18280.00', 'KOTLOV-000289' => '29990.00', 'KOTLOV-000318' => '43330.00'];
        foreach ($expected as $sku => $price) {
            Product::create(['category_id' => $category->id, 'name' => $sku, 'slug' => strtolower($sku), 'sku' => $sku, 'price' => 0]);
        }
        $rows = json_decode(file_get_contents(resource_path('data/suppliers/santehsklad-sakovich-2026-09-17.json')), true)['rows'];
        foreach ($rows as $row) {
            if ($row['source_sheet'] !== 'PELLET') {
                continue;
            }
            $supplier->supplierProducts()->create([
                'supplier_article' => $row['article'], 'supplier_article_normalized' => $row['article_key'],
                'supplier_name' => $row['name'], 'currency' => 'BYN', 'price_byn' => $row['wholesale'],
                'price' => $row['wholesale'], 'raw' => $row,
            ]);
        }
        app(SakovichPelletKits::class)->refresh($supplier);
        foreach ($expected as $sku => $price) {
            $product = Product::where('sku', $sku)->firstOrFail();
            $this->assertSame($price, $product->price);
            $this->assertFalse($product->requires_price_confirmation);
            $this->assertSame(1, $product->supplierProducts()->count());
        }
    }
}
