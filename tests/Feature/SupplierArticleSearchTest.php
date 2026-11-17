<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\StorefrontProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierArticleSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_article_and_catalogue_code_find_the_same_product_without_replacing_internal_sku(): void
    {
        $supplier = Supplier::create(['code' => 'td_komplekt', 'name' => 'ТД Комплект', 'is_active' => true]);
        $product = Product::create(['category_id' => Category::firstOrFail()->id, 'slug' => 'tdk-search-test', 'price' => 10, 'sku' => 'TDK-934132', 'name' => 'Тестовый товар', 'is_active' => true]);
        $other = Product::create(['category_id' => $product->category_id, 'slug' => 'other-search-test', 'price' => 10, 'name' => 'Другой товар']);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id,
            'supplier_article' => 'ST9038-12-10', 'supplier_article_normalized' => 'ST9038-12-10',
            'supplier_catalog_code' => 'ЦБ-0139321690', 'supplier_name' => 'Лента', 'match_status' => 'matched']);
        foreach (['ST9038-12-10', 'цб-0139321690'] as $article) {
            foreach (['apply', 'applyForSuggestions', 'applyForResults'] as $method) {
                $query = Product::active()->notArchived();
                app(StorefrontProductSearch::class)->$method($query, $article);
                $this->assertSame([$product->id], $query->pluck('products.id')->all());
            }
        }
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Livewire::actingAs($admin)->test(ListProducts::class)->searchTable('ST9038-12-10')
            ->assertCanSeeTableRecords([$product])->assertCanNotSeeTableRecords([$other]);
        $this->assertSame('TDK-934132', $product->fresh()->sku);
        $product->forceFill(['is_archived' => true])->save();
        $query = Product::active()->notArchived();
        app(StorefrontProductSearch::class)->applyForResults($query, 'ST9038-12-10');
        $this->assertFalse($query->exists());
    }
}
