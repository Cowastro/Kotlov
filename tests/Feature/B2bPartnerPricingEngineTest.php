<?php

namespace Tests\Feature;

use App\Models\B2bPricingRule;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Pricing\B2bPartnerPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bPartnerPricingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_most_specific_rule_and_does_not_publish_a_draft(): void
    {
        [$supplier, $category, $brand, $product, $offer] = $this->catalogFixture();

        $this->rule($supplier, B2bPricingRule::SCOPE_SUPPLIER, null, 18, 'Поставщик');
        $this->rule($supplier, B2bPricingRule::SCOPE_CATEGORY, $category->id, 15, 'Категория');
        $this->rule($supplier, B2bPricingRule::SCOPE_BRAND, $brand->id, 12, 'Бренд');
        $productRule = $this->rule($supplier, B2bPricingRule::SCOPE_PRODUCT, $product->id, 10, 'Товар');

        $result = app(B2bPartnerPricingEngine::class)->preview($supplier);

        $this->assertSame(1, $result['would_publish']);
        $this->assertSame(0, $result['published']);
        $this->assertSame($productRule->id, $result['items']->first()['rule_id']);
        $this->assertSame(112.0, $result['items']->first()['b2b_price']);
        $this->assertFalse($result['items']->first()['publishable']);

        $productRule->delete();
        $result = app(B2bPartnerPricingEngine::class)->preview($supplier);

        $this->assertSame('Бренд', $result['items']->first()['rule_name']);
        $this->assertSame(114.0, $result['items']->first()['b2b_price']);
        $this->assertSame($offer->id, $result['items']->first()['offer_id']);
    }

    public function test_only_active_rule_and_enabled_supplier_are_publishable(): void
    {
        [$supplier] = $this->catalogFixture();
        $supplier->update(['b2b_partner_enabled' => true]);
        $this->rule(
            $supplier,
            B2bPricingRule::SCOPE_SUPPLIER,
            null,
            20,
            'Активное правило',
            B2bPricingRule::STATUS_ACTIVE,
        );

        $result = app(B2bPartnerPricingEngine::class)->preview($supplier->fresh(), false);

        $this->assertSame(1, $result['published']);
        $this->assertTrue($result['items']->first()['publishable']);
        $this->assertSame(125.0, $result['items']->first()['b2b_price']);
    }

    public function test_it_explains_why_an_offer_cannot_be_published(): void
    {
        [$supplier, , , $product, $offer] = $this->catalogFixture();
        $this->rule($supplier, B2bPricingRule::SCOPE_SUPPLIER, null, 20, 'Поставщик');

        $offer->update(['last_stock_synced_at' => now()->subHours(25)]);
        $result = app(B2bPartnerPricingEngine::class)->preview($supplier);
        $this->assertSame(1, $result['reasons'][B2bPartnerPricingEngine::REASON_STALE_STOCK]);

        $offer->update(['last_stock_synced_at' => now(), 'b2b_excluded' => true, 'b2b_exclusion_reason' => 'Не продавать']);
        $result = app(B2bPartnerPricingEngine::class)->preview($supplier);
        $this->assertSame(1, $result['reasons'][B2bPartnerPricingEngine::REASON_EXCLUDED]);

        $offer->update(['b2b_excluded' => false]);
        $product->update(['price' => 110]);
        $result = app(B2bPartnerPricingEngine::class)->preview($supplier);
        $this->assertSame(1, $result['reasons'][B2bPartnerPricingEngine::REASON_PRICE_CONFLICT]);
    }

    /** @return array{Supplier, Category, Brand, Product, SupplierProduct} */
    private function catalogFixture(): array
    {
        $supplier = Supplier::query()->create([
            'code' => 'partner-test',
            'name' => 'Тестовый партнёр',
            'b2b_partner_enabled' => false,
            'b2b_client_warehouse_label' => 'Партнёрский склад',
            'b2b_stock_max_age_hours' => 24,
            'b2b_delivery_days_min' => 1,
            'b2b_delivery_days_max' => 2,
            'b2b_require_positive_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Насосы', 'slug' => 'partner-test-pumps']);
        $brand = Brand::query()->create(['name' => 'Partner Brand', 'slug' => 'partner-brand']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Тестовый насос',
            'slug' => 'partner-test-pump',
            'sku' => 'PARTNER-1',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article' => 'SUP-1',
            'price' => 100,
            'price_byn' => 100,
            'currency' => 'BYN',
            'in_stock' => true,
            'stock_quantity' => 5,
            'match_status' => 'matched',
            'last_stock_synced_at' => now(),
            'last_synced_at' => now(),
        ]);

        return [$supplier, $category, $brand, $product, $offer];
    }

    private function rule(
        Supplier $supplier,
        string $scope,
        ?int $scopeId,
        float $margin,
        string $name,
        string $status = B2bPricingRule::STATUS_DRAFT,
    ): B2bPricingRule {
        return $supplier->b2bPricingRules()->create([
            'name' => $name,
            'scope_type' => $scope,
            'scope_id' => $scopeId,
            'calculation_type' => B2bPricingRule::TYPE_TARGET_MARGIN,
            'value' => $margin,
            'rounding_step_byn' => 1,
            'status' => $status,
        ]);
    }
}
