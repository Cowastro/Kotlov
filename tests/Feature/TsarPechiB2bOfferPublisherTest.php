<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\TsarPechiB2bOfferPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TsarPechiB2bOfferPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_product_is_published_at_twenty_percent_below_retail_as_procurement_offer(): void
    {
        $supplier = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], [
            'name' => 'ООО «СанБизнесГруп»',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
        ]);
        $brand = Brand::query()->updateOrCreate(['slug' => 'tsar-pechi'], ['name' => 'Царь-Печи']);
        $category = Category::query()->create([
            'name' => 'Печи для бани',
            'slug' => 'test-tsar-pechi-stoves',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Печь Царь-Печи Ярило',
            'slug' => 'test-tsar-pechi-yarilo',
            'sku' => 'PS-009.940',
            'price' => 648,
            'is_active' => true,
            'is_archived' => false,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article' => $product->sku,
            'currency' => 'BYN',
            'stock_status' => 'preorder',
            'match_status' => 'matched',
            'match_confidence' => 'manual_price_list',
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

        $result = app(TsarPechiB2bOfferPublisher::class)->sync();

        $source = IntegrationSource::query()->where('code', TsarPechiB2bOfferPublisher::SOURCE_CODE)->firstOrFail();
        $offer = IntegrationProduct::query()->whereBelongsTo($source, 'source')->firstOrFail();
        $this->assertSame(1, $result['offers']);
        $this->assertTrue($source->isProcurementMode());
        $this->assertSame(648.0, (float) $offer->price_byn);
        $this->assertSame(0.0, (float) $offer->stock_quantity);
        $this->assertContains($source->id, $client->fresh()->allowed_integration_source_ids);

        $this->actingAs($user)
            ->get(route('b2b.catalog'))
            ->assertOk()
            ->assertSeeText('Печь Царь-Печи Ярило')
            ->assertSeeText('518,40')
            ->assertSeeText('Розница 648,00')
            ->assertSeeText('Под заказ у СанБизнесГруп');

        $this->actingAs($user)
            ->postJson(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('subtotal', 1036.8);

        $item = session('cart')[$product->id];
        $this->assertSame('b2b', $item['pricing_type']);
        $this->assertSame($offer->id, $item['integration_product_id']);
        $this->assertNull($item['warehouse_stock_id']);
    }
}
