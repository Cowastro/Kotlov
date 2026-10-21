<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierChannelAvailabilityRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_stock_requires_an_available_linked_supplier_channel(): void
    {
        $unlinked = $this->product('UNLINKED', true);

        $this->assertSame(Product::AVAILABILITY_CHECK, $unlinked->effectiveAvailabilityStatus());

        $supplier = Supplier::query()->create([
            'code' => 'availability-rule-supplier',
            'name' => 'Поставщик правила наличия',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $unlinked->id,
            'supplier_article' => 'LEGACY-LINK-1',
            'in_stock' => true,
            'stock_quantity' => 2,
        ]);

        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $unlinked->fresh()->effectiveAvailabilityStatus());
    }

    public function test_active_matched_1c_channel_can_confirm_stock(): void
    {
        $product = $this->product('ONE-C-LINK', true);
        $supplier = Supplier::query()->create([
            'code' => 'availability-rule-one-c',
            'name' => 'Поставщик 1С правила наличия',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'availability-rule-one-c-source',
            'name' => '1С правила наличия',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'availability-rule-one-c-item',
            'name' => 'Позиция 1С правила наличия',
            'stock_quantity' => 4,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);

        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->fresh()->effectiveAvailabilityStatus());
    }

    public function test_unpriced_supplier_catalog_link_does_not_hide_confirmed_one_c_stock(): void
    {
        $product = $this->product('TIS-SBG-OVERLAP', true);
        $supplier = Supplier::query()->updateOrCreate([
            'code' => 'sanbusinessgroup',
        ], [
            'name' => 'ООО «СанБизнесГруп»',
            'is_active' => true,
        ]);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'TIS-NO-PRICE',
            'price_byn' => null,
            'in_stock' => false,
            'stock_quantity' => null,
            'match_status' => 'matched',
            'match_confidence' => 'official_source',
        ]);

        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'onec-sanbusinessgroup-overlap',
            'name' => '1С СанБизнесГруп',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'onec-tis-sbg-overlap',
            'name' => 'Позиция Теплов и Сухов на складе СанБизнесГруп',
            'price_byn' => 80,
            'stock_quantity' => 3,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);

        $product = $product->fresh();

        $this->assertTrue($product->hasAssignedSupplier());
        $this->assertTrue($product->hasVerifiedPublicPrice());
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame(3.0, $product->confirmedStockQuantity());
        $this->assertTrue($product->canBeAddedToCart());
    }

    public function test_disabled_product_is_out_of_stock_even_when_supplier_has_stock(): void
    {
        $product = $this->product('DISABLED-LINK', false);
        $supplier = Supplier::query()->create([
            'code' => 'disabled-availability-rule',
            'name' => 'Поставщик отключённого товара',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'DISABLED-LINK-1',
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);

        $product = $product->fresh();

        $this->assertSame(Product::AVAILABILITY_OUT_OF_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertFalse($product->canBeOrdered());
    }

    public function test_stale_supplier_stock_requires_consultation(): void
    {
        $product = $this->product('STALE-LINK', true);
        $supplier = Supplier::query()->create([
            'code' => 'stale-availability-rule',
            'name' => 'Поставщик со старым остатком',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'STALE-LINK-1',
            'in_stock' => true,
            'stock_quantity' => 3,
            'last_stock_synced_at' => now()->subHours(SupplierProduct::STALE_AFTER_HOURS + 1),
        ]);

        $this->assertSame(Product::AVAILABILITY_CHECK, $product->fresh()->effectiveAvailabilityStatus());
        $this->assertFalse($product->fresh()->canBePurchased());
    }

    public function test_catalog_sort_prioritizes_only_products_with_fresh_confirmed_stock(): void
    {
        $unlinked = $this->product('SORT-UNLINKED', true);
        $unlinked->forceFill(['price' => 1])->save();
        $stale = $this->product('SORT-STALE', true);
        $stale->forceFill(['price' => 5])->save();
        $fresh = $this->product('SORT-FRESH', true);
        $fresh->forceFill(['price' => 300])->save();

        $supplier = Supplier::query()->create([
            'code' => 'catalog-stock-priority',
            'name' => 'Поставщик сортировки остатков',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $stale->id,
            'supplier_article' => 'SORT-STALE',
            'in_stock' => true,
            'stock_quantity' => 2,
            'last_stock_synced_at' => now()->subHours(SupplierProduct::STALE_AFTER_HOURS + 1),
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $fresh->id,
            'supplier_article' => 'SORT-FRESH',
            'in_stock' => true,
            'stock_quantity' => 2,
            'last_stock_synced_at' => now(),
        ]);

        $ordered = Product::query()
            ->whereKey([$unlinked->id, $stale->id, $fresh->id])
            ->withSupplierChannels()
            ->prioritizeConfirmedStock()
            ->orderBy('price')
            ->get();

        $this->assertSame([$fresh->id, $unlinked->id, $stale->id], $ordered->pluck('id')->all());
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $ordered->first()->effectiveAvailabilityStatus());
        $this->assertSame(Product::AVAILABILITY_CHECK, $ordered->get(1)->effectiveAvailabilityStatus());
        $this->assertSame(Product::AVAILABILITY_CHECK, $ordered->get(2)->effectiveAvailabilityStatus());
    }

    public function test_homepage_rating_keeps_confirmed_stock_above_higher_rated_unconfirmed_products(): void
    {
        $unconfirmed = $this->product('HOME-UNCONFIRMED', true);
        $unconfirmed->forceFill(['rating' => 5, 'reviews_count' => 100])->save();
        $confirmed = $this->product('HOME-CONFIRMED', true);
        $confirmed->forceFill(['rating' => 4, 'reviews_count' => 10])->save();

        $supplier = Supplier::query()->create([
            'code' => 'homepage-rating-stock',
            'name' => 'Поставщик рейтинга главной',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $confirmed->id,
            'supplier_article' => 'HOME-CONFIRMED',
            'in_stock' => true,
            'stock_quantity' => 2,
            'last_stock_synced_at' => now(),
        ]);

        $ordered = Product::query()
            ->whereKey([$unconfirmed->id, $confirmed->id])
            ->homepageRatingOrder()
            ->get();

        $this->assertSame([$confirmed->id, $unconfirmed->id], $ordered->pluck('id')->all());
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $ordered->first()->effectiveAvailabilityStatus());
        $this->assertSame(Product::AVAILABILITY_CHECK, $ordered->last()->effectiveAvailabilityStatus());
    }

    public function test_one_c_stock_remains_valid_for_a_day_and_then_requires_consultation(): void
    {
        $product = $this->product('STALE-ONE-C', true);
        $supplier = Supplier::query()->create([
            'code' => 'stale-one-c-supplier',
            'name' => 'Поставщик со старым остатком 1С',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'stale-one-c-source',
            'name' => 'Старый остаток 1С',
            'driver' => 'commerceml',
            'is_active' => true,
            'settings' => ['stale_after_minutes' => 15],
        ]);
        $this->assertSame(1440, $source->stockValidityMinutes());
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'stale-one-c-item',
            'name' => 'Позиция со старым остатком 1С',
            'stock_quantity' => 4,
            'stock_confirmed_at' => now()->subMinutes(1439),
            'match_status' => 'matched',
        ]);

        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->fresh()->effectiveAvailabilityStatus());
        $this->assertTrue($product->fresh()->canBePurchased());

        $storefrontProduct = Product::query()
            ->withSupplierChannels()
            ->findOrFail($product->id);

        $this->assertSame('commerceml', $storefrontProduct->integrationProducts->first()->source->driver);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $storefrontProduct->effectiveAvailabilityStatus());
        $this->assertTrue($storefrontProduct->canBePurchased());

        IntegrationProduct::query()->where('product_id', $product->id)->update([
            'stock_confirmed_at' => now()->subMinutes(1441),
        ]);

        $this->assertSame(Product::AVAILABILITY_CHECK, $product->fresh()->effectiveAvailabilityStatus());
        $this->assertFalse($product->fresh()->canBePurchased());
    }

    public function test_product_page_keeps_one_c_stock_valid_when_source_settings_use_defaults(): void
    {
        $product = $this->product('STOREFRONT-ONE-C', true);
        $supplier = Supplier::query()->create([
            'code' => 'storefront-one-c-supplier',
            'name' => 'Поставщик витрины 1С',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'storefront-one-c-source',
            'name' => '1С витрины',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $source->forceFill(['settings' => []])->saveQuietly();

        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'storefront-one-c-item',
            'name' => 'Позиция витрины 1С',
            'stock_quantity' => 7,
            'stock_confirmed_at' => now()->subMinutes(30),
            'match_status' => 'matched',
        ]);

        $response = $this->get('/supplier-channel-rule-products/'.$product->slug);

        $response->assertOk();
        $response->assertSee('В наличии');
        $response->assertDontSee('Уточняйте наличие');
    }

    private function product(string $sku, bool $active): Product
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'supplier-channel-rule-products'],
            ['name' => 'Товары правила каналов', 'parent_id' => 0, 'is_active' => true]
        );

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар '.$sku,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'price' => 100,
            'is_active' => $active,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}
