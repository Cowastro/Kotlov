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

    public function test_approved_wholesale_user_sees_wholesale_price_with_tax_retail_difference_and_stock(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSeeText('Партнёрская цена от ООО «СанБизнесГруп»')
            ->assertSeeText('96.00 BYN')
            ->assertSeeText('Цена с НДС 20%')
            ->assertSeeText('Розничная цена: 120.00 BYN')
            ->assertSeeText('Ваша скидка к рознице: 24.00 BYN (20.0%)')
            ->assertSeeText('Основной: 7.000')
            ->assertSee('В корзину', false)
            ->assertSeeText('— 96.00 BYN');
    }

    public function test_unapproved_wholesale_user_does_not_see_wholesale_price(): void
    {
        [$product, $user] = $this->catalogFixture(approved: false);

        $this->actingAs($user)
            ->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('Ваша оптовая цена')
            ->assertDontSeeText('96.00 BYN')
            ->assertSeeText('120.00 BYN');
    }

    public function test_cart_uses_wholesale_price_for_approved_partner(): void
    {
        [$product, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('subtotal', 192);

        $item = session('cart')[$product->id];
        $this->assertSame(96.0, $item['price']);
        $this->assertSame('b2b', $item['pricing_type']);
        $this->assertSame('inclusive', $item['price_tax_mode']);
        $this->assertNotNull($item['integration_product_id']);
    }

    public function test_admin_can_preview_partner_card_without_changing_their_account(): void
    {
        [$product] = $this->catalogFixture(approved: false);
        $admin = User::factory()->create([
            'role' => 'admin',
            'client_type' => 'retail',
            'b2b_approved' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/'.$product->category->slug.'/'.$product->slug.'?b2b-preview=1')
            ->assertOk()
            ->assertSeeText('Предпросмотр карточки для одобренного B2B-партнёра')
            ->assertSeeText('96.00 BYN');
    }

    public function test_partner_account_groups_available_products_from_sanbusinessgroup(): void
    {
        [, $user] = $this->catalogFixture(approved: true);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSeeText('Партнёрские цены активны')
            ->assertSeeText('Поставщик: ООО «СанБизнесГруп»')
            ->assertSeeText('Группы товаров с партнёрскими ценами')
            ->assertSeeText('Дымоходы')
            ->assertSeeText('1 позиций в наличии');
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
        $source = IntegrationSource::query()->updateOrCreate(
            ['code' => 'onec'],
            [
                'name' => '1С',
                'is_active' => true,
                'settings' => [
                    'price_tax_mode' => 'exclusive',
                    'vat_rate' => 20,
                    'warehouse_label' => 'Основной',
                    'b2b_enabled' => true,
                    'partner_name' => 'ООО «СанБизнесГруп»',
                ],
            ],
        );
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
