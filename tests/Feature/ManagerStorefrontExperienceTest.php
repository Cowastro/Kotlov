<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\ManagerStorefrontProductContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerStorefrontExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_supplier_context_is_only_available_to_active_managers(): void
    {
        [$product] = $this->makeProductWithSupplierOffer();
        $context = app(ManagerStorefrontProductContext::class);

        $this->assertFalse($context->canView(null));
        $this->assertCount(0, $context->offers($product, null));

        $inactiveManager = User::factory()->create(['role' => 'manager', 'is_active' => false]);
        $this->assertFalse($context->canView($inactiveManager));
        $this->assertCount(0, $context->offers($product, $inactiveManager));

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $offers = $context->offers($product, $manager);

        $this->assertTrue($context->canView($manager));
        $this->assertCount(1, $offers);
        $this->assertSame('Закрытый поставщик', $offers->first()['supplier']);
        $this->assertSame(['+375 29 111-22-33'], $offers->first()['phones']);
        $this->assertSame('SECRET-123', $offers->first()['article']);
        $this->assertSame(4.0, $offers->first()['purchase_price']);
        $this->assertSame(6.0, $offers->first()['margin']);
        $this->assertSame(60.0, $offers->first()['margin_percent']);
        $this->assertSame('7 шт.', $offers->first()['stock']);
    }

    public function test_internal_supplier_markup_is_not_rendered_for_guests(): void
    {
        [$product] = $this->makeProductWithSupplierOffer();

        $this->view('partials.manager-product-context', ['product' => $product])
            ->assertDontSee('Закрытый поставщик')
            ->assertDontSee('SECRET-123');

        $manager = User::factory()->create(['role' => 'sales_manager', 'is_active' => true]);
        $this->actingAs($manager);

        $this->view('partials.manager-product-context', ['product' => $product])
            ->assertSee('Закрытый поставщик')
            ->assertSee('SECRET-123')
            ->assertSee('Закупка: 4.00 BYN')
            ->assertSee('Позвонить: +375 29 111-22-33')
            ->assertSee('tel:+375291112233', false);
    }

    public function test_only_manager_search_can_find_products_by_supplier_article_or_name(): void
    {
        [$product] = $this->makeProductWithSupplierOffer();

        $this->get('/search?q=SECRET-123')
            ->assertOk()
            ->assertDontSee($product->name);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager)
            ->get('/search?q=SECRET-123')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Закрытый поставщик');

        $this->getJson('/search/suggest?'.http_build_query(['q' => 'Закрытый поставщик']))
            ->assertOk()
            ->assertJsonFragment(['name' => $product->name]);
    }

    /** @return array{Product, SupplierProduct} */
    private function makeProductWithSupplierOffer(): array
    {
        $category = Category::create([
            'name' => 'Тестовый каталог',
            'slug' => 'manager-storefront-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Товар без секретов в названии',
            'slug' => 'manager-storefront-product',
            'sku' => 'PUBLIC-SKU',
            'price' => 10,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $supplier = Supplier::create([
            'code' => 'private-supplier',
            'name' => 'Закрытый поставщик',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => 'Отдел продаж: +375 29 111-22-33',
            'is_active' => true,
        ]);
        $offer = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'SECRET-123',
            'supplier_name' => 'Внутреннее название',
            'price' => 4,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => 4,
            'in_stock' => true,
            'stock_quantity' => 7,
            'stock_status' => 'in_stock',
            'last_stock_synced_at' => now(),
            'last_synced_at' => now(),
            'match_status' => 'matched',
        ]);

        return [$product, $offer];
    }
}
