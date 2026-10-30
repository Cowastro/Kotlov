<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bPriceLevel;
use App\Models\B2bPricingRule;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\B2bSupplyOptionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class B2bExternalSupplyOptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_warehouse_requires_explicit_client_access_and_never_leaks_purchase_price(): void
    {
        [$supplier, $product, $offer] = $this->externalFixture();
        $allowed = $this->approvedPartner([$supplier->id]);
        $blocked = $this->approvedPartner([]);

        $this->actingAs($allowed)
            ->get(route('b2b.catalog', ['q' => $product->sku]))
            ->assertOk()
            ->assertSeeText($product->name)
            ->assertSeeText('125,00 BYN')
            ->assertSeeText('Партнёрский склад')
            ->assertDontSee('purchase_price', false)
            ->assertDontSee('100,00 BYN');

        $this->actingAs($blocked)
            ->get(route('b2b.catalog', ['q' => $product->sku]))
            ->assertOk()
            ->assertDontSeeText($product->name);

        $this->actingAs($blocked)
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 1,
                'pricing_mode' => 'b2b',
                'supply_option' => 'supplier:'.$offer->id,
            ])
            ->assertUnprocessable();
    }

    public function test_price_level_discount_is_clamped_to_the_rules_minimum_margin(): void
    {
        [$supplier, $product, $offer] = $this->externalFixture();
        $level = B2bPriceLevel::query()->create([
            'code' => 'vip-test',
            'name' => 'VIP тест',
            'rank' => 50,
            'sale_price_discount_percent' => 20,
            'is_active' => true,
        ]);
        $user = $this->approvedPartner([$supplier->id], $level->id);

        $option = app(B2bSupplyOptionResolver::class)
            ->optionForKey($product, $user, 'supplier:'.$offer->id);

        $this->assertNotNull($option);
        $this->assertSame(125.0, $option['base_price']);
        $this->assertSame(112.0, $option['price']);
        $this->assertSame(10.4, $option['discount_percent']);
        $this->assertArrayNotHasKey('purchase_price', $option);
        $this->assertArrayNotHasKey('profit', $option);
        $this->assertArrayNotHasKey('margin_percent', $option);

        $this->actingAs($user)
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 2,
                'pricing_mode' => 'b2b',
                'supply_option' => 'supplier:'.$offer->id,
            ])
            ->assertOk()
            ->assertJsonPath('subtotal', 224);

        $item = session('cart')[$product->id];
        $this->assertSame($offer->id, $item['supplier_product_id']);
        $this->assertNull($item['integration_product_id']);
        $this->assertSame('supplier:'.$offer->id, $item['supply_option']);
    }

    public function test_stale_external_stock_removes_the_whole_price_and_stock_option(): void
    {
        [$supplier, $product, $offer] = $this->externalFixture();
        $user = $this->approvedPartner([$supplier->id]);
        $offer->update([
            'last_stock_synced_at' => now()->subHours(25),
            'last_synced_at' => now()->subHours(25),
        ]);

        $this->assertNull(app(B2bSupplyOptionResolver::class)
            ->optionForKey($product, $user, 'supplier:'.$offer->id));

        $this->actingAs($user)
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 1,
                'pricing_mode' => 'b2b',
                'supply_option' => 'supplier:'.$offer->id,
            ])
            ->assertUnprocessable();
    }

    public function test_checkout_persists_the_exact_external_offer_and_internal_supply_snapshot(): void
    {
        Mail::fake();

        [$supplier, $product, $offer] = $this->externalFixture();
        $user = $this->approvedPartner([$supplier->id]);

        $this->actingAs($user)
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 2,
                'pricing_mode' => 'b2b',
                'supply_option' => 'supplier:'.$offer->id,
            ])
            ->assertOk();

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('checkout.store'), [
                'order_token' => 'external-b2b-order',
                'customer_name' => 'ООО Внешний склад',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ])
            ->assertRedirect();

        $item = OrderItem::query()->sole();
        $this->assertSame($offer->id, $item->supplier_product_id);
        $this->assertNull($item->integration_product_id);
        $this->assertSame($supplier->id, $item->supply_supplier_id);
        $this->assertSame('legacy', $item->supply_channel);
        $this->assertSame(100.0, (float) $item->supply_purchase_price);
        $this->assertSame(125.0, (float) $item->price);
    }

    /** @return array{Supplier, Product, SupplierProduct} */
    private function externalFixture(): array
    {
        $supplier = Supplier::query()->create([
            'code' => 'external-b2b-test',
            'name' => 'Внешний B2B поставщик',
            'is_active' => true,
            'b2b_partner_enabled' => true,
            'b2b_client_warehouse_label' => 'Партнёрский склад',
            'b2b_stock_max_age_hours' => 24,
            'b2b_delivery_days_min' => 1,
            'b2b_delivery_days_max' => 2,
            'b2b_priority' => 100,
            'b2b_require_positive_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Внешние товары',
            'slug' => 'external-b2b-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Насос внешнего склада',
            'slug' => 'external-b2b-pump',
            'sku' => 'EXT-B2B-1',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article' => 'EXT-1',
            'supplier_name' => $product->name,
            'price' => 100,
            'price_byn' => 100,
            'currency' => 'BYN',
            'in_stock' => true,
            'stock_quantity' => 7,
            'match_status' => 'matched',
            'last_stock_synced_at' => now(),
            'last_synced_at' => now(),
        ]);
        $supplier->b2bPricingRules()->create([
            'name' => 'Безопасная маржа',
            'scope_type' => B2bPricingRule::SCOPE_SUPPLIER,
            'calculation_type' => B2bPricingRule::TYPE_TARGET_MARGIN,
            'value' => 20,
            'minimum_profit_byn' => 0,
            'minimum_margin_percent' => 10,
            'rounding_step_byn' => 1,
            'minimum_retail_discount_percent' => 0,
            'priority' => 100,
            'status' => B2bPricingRule::STATUS_ACTIVE,
        ]);

        return [$supplier, $product, $offer];
    }

    /** @param list<int> $supplierIds */
    private function approvedPartner(array $supplierIds, ?int $priceLevelId = null): User
    {
        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'is_active' => true,
        ]);
        B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Внешний склад»',
            'b2b_price_level_id' => $priceLevelId,
            'allowed_integration_source_ids' => [],
            'allowed_supplier_ids' => $supplierIds,
        ]);

        return $user;
    }
}
