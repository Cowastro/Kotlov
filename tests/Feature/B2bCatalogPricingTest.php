<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bCatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_wholesale_user_sees_net_wholesale_price_and_stock(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('Ваша оптовая цена')
            ->assertSeeText('80.00 BYN')
            ->assertSeeText('Цена без НДС')
            ->assertSeeText('Основной: 7.000');
    }

    public function test_unapproved_wholesale_user_does_not_see_wholesale_price(): void
    {
        [$product, $user] = $this->catalogFixture(approved: false);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Ваша оптовая цена')
            ->assertDontSeeText('80.00 BYN')
            ->assertSeeText('120.00 BYN');
    }

    public function test_cart_uses_wholesale_price_for_approved_partner(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('subtotal', 160);

        $item = session('cart')[$product->id];
        $this->assertSame(80.0, $item['price']);
        $this->assertSame('b2b', $item['pricing_type']);
        $this->assertSame('exclusive', $item['price_tax_mode']);
        $this->assertNotNull($item['integration_product_id']);
    }

    /** @return array{Product, User} */
    private function catalogFixture(bool $approved): array
    {
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'dymohody-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Труба дымохода D115',
            'slug' => 'truba-d115-test',
            'sku' => 'TEST-D115',
            'price' => 120,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
            'is_active' => true,
            'settings' => [
                'price_tax_mode' => 'exclusive',
                'warehouse_label' => 'Основной',
            ],
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'onec-d115',
            'name' => 'Труба дымохода D115',
            'price' => 80,
            'stock_quantity' => 7,
            'match_status' => 'matched',
        ]);
        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => $approved,
            'is_active' => true,
        ]);

        return [$product->load('category'), $user];
    }
}
