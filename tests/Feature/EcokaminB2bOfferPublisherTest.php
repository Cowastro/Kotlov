<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\EcokaminB2bOfferPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcokaminB2bOfferPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_moscow_stock_becomes_an_orderable_ecokamin_b2b_offer(): void
    {
        $supplier = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], [
            'name' => 'ООО «СанБизнесГруп»',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
        ]);
        $brand = Brand::query()->updateOrCreate(['slug' => 'ecokamin'], ['name' => 'ЭкоКамин']);
        $category = Category::query()->create([
            'name' => 'Печи-камины',
            'slug' => 'test-ecokamin-stoves',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Камин ЭкоКамин Мадрид',
            'slug' => 'test-ecokamin-madrid',
            'sku' => 'KOTLOV-ECO-1',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $stock = ProductWarehouseStock::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва',
            'external_article' => 'KM203',
            'available_quantity' => 8,
            'delivery_days' => 7,
            'as_of_date' => '2026-10-23',
            'last_synced_at' => now(),
        ]);
        $oneC = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], [
            'supplier_id' => $supplier->id,
            'name' => '1С СанБизнесГруп',
            'is_active' => true,
            'settings' => [
                'b2b_enabled' => true,
                'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE,
                'b2b_category_ids' => [$category->id],
                'partner_name' => 'ООО «СанБизнесГруп»',
            ],
        ]);
        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'is_active' => true,
        ]);
        $client = B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Партнёр»',
            'allowed_integration_source_ids' => [$oneC->id],
        ]);

        $result = app(EcokaminB2bOfferPublisher::class)->sync();

        $source = IntegrationSource::query()->where('code', EcokaminB2bOfferPublisher::SOURCE_CODE)->firstOrFail();
        $offer = IntegrationProduct::query()->whereBelongsTo($source, 'source')->firstOrFail();
        $this->assertSame(1, $result['offers']);
        $this->assertSame($product->id, $offer->product_id);
        $this->assertSame(8.0, (float) $offer->stock_quantity);
        $this->assertTrue($offer->b2b_enabled);
        $this->assertContains($source->id, $client->fresh()->allowed_integration_source_ids);

        $this->actingAs($user)
            ->get(route('b2b.catalog'))
            ->assertOk()
            ->assertSeeText('Камин ЭкоКамин Мадрид')
            ->assertSeeText('170,00')
            ->assertSeeText('Склад Москва: 8 шт.');

        $this->actingAs($user)
            ->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('subtotal', 340);

        $item = session('cart')[$product->id];
        $this->assertSame('b2b', $item['pricing_type']);
        $this->assertSame($offer->id, $item['integration_product_id']);
        $this->assertSame($stock->id, $item['warehouse_stock_id']);
        $this->assertSame('Склад Москва', $item['warehouse_name']);
    }
}
