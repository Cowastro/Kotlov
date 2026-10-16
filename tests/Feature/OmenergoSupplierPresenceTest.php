<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmenergoSupplierPresenceTest extends TestCase
{
    use RefreshDatabase;

    private function card(): array
    {
        $category = Category::create(['name' => 'Статус поставщика', 'slug' => 'supplier-presence']);
        $supplier = Supplier::create(['name' => 'ОмЭнерго', 'code' => 'omenergo', 'is_active' => true]);
        $product = Product::create(['name' => 'Тест ОмЭнерго', 'sku' => 'OMEN-PRESENCE', 'slug' => 'omen-presence', 'category_id' => $category->id, 'price' => 100, 'is_active' => true, 'availability_status' => 'check']);
        $link = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'PRESENCE', 'in_stock' => true, 'stock_quantity' => null, 'stock_status' => 'available_without_quantity', 'last_stock_synced_at' => now()]);

        return [$product, $supplier, $link];
    }

    public function test_qualitative_stock_changes_label_but_never_confirms_checkout_or_quantity(): void
    {
        [$product, $supplier, $link] = $this->card();
        $this->assertSame('В наличии', $product->availabilityLabel());
        $this->assertFalse($product->canBePurchased());
        $this->assertTrue($product->requiresAvailabilityConfirmation());
        $this->assertNull($link->fresh()->stock_quantity);
        $link->update(['stock_status' => 'low_without_quantity']);
        $this->assertSame('В наличии', $product->fresh()->availabilityLabel());
        $eager = Product::withSupplierChannels()->findOrFail($product->id);
        $this->assertSame('В наличии', $eager->availabilityLabel());
        $this->assertFalse($eager->canBePurchased());
    }

    public function test_stale_disabled_and_price_hold_channels_cannot_advertise_supplier_presence(): void
    {
        [$product, $supplier, $link] = $this->card();
        $link->update(['last_stock_synced_at' => now()->subHours(25)]);
        $this->assertSame('Доступно к заказу', $product->fresh()->availabilityLabel());
        $link->update(['last_stock_synced_at' => now()]);
        $supplier->update(['is_active' => false]);
        $this->assertSame('Доступно к заказу', $product->fresh()->availabilityLabel());
        $supplier->update(['is_active' => true]);
        $product->update(['requires_price_confirmation' => true]);
        $this->assertSame('Доступно к заказу', $product->fresh()->availabilityLabel());
        $product->update(['requires_price_confirmation' => false, 'is_archived' => true]);
        $this->assertSame('Нет в наличии', $product->fresh()->availabilityLabel());
    }

    public function test_product_page_displays_supplier_label_without_changing_confirmation_status(): void
    {
        [$product] = $this->card();
        $response = $this->get('/supplier-presence/omen-presence')->assertOk();
        $this->assertTrue(str_contains($response->getContent(), 'В наличии'), 'Product page must show qualitative availability.');
        $response->assertDontSee('у поставщика')->assertDontSee('Менеджер подтвердит наличие и срок поставки после оформления.');
        $this->assertSame('https://schema.org/InStock', json_decode($response->viewData('schemaJson'), true, flags: JSON_THROW_ON_ERROR)['offers']['availability']);
        foreach (['partials.product-card', 'partials.product-card-list'] as $partial) {
            $html = view($partial, ['product' => Product::withSupplierChannels()->with('category')->findOrFail($product->id)])->render();
            $this->assertTrue(str_contains($html, 'В наличии'), $partial.' must show unified availability.');
            $this->assertStringNotContainsString('у поставщика', $html);
            $this->assertTrue(str_contains($html, 'data-needs-confirmation="1"'), $partial.' must retain manager confirmation.');
        }
        $this->assertSame('check', $product->fresh()->effectiveAvailabilityStatus());
        $this->assertFalse($product->fresh()->canBePurchased());
    }

    public function test_archived_card_does_not_publish_old_price_as_a_search_offer(): void
    {
        [$product] = $this->card();
        $active = $this->get('/supplier-presence/omen-presence')->assertOk();
        $this->assertArrayHasKey('offers', json_decode($active->viewData('schemaJson'), true, flags: JSON_THROW_ON_ERROR));
        $product->update(['is_archived' => true]);
        $archived = $this->get('/supplier-presence/omen-presence')->assertOk();
        $this->assertArrayNotHasKey('offers', json_decode($archived->viewData('schemaJson'), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_only_fresh_enabled_quantities_are_displayed_and_summary_is_not_trusted(): void
    {
        [$product, $supplier, $link] = $this->card();
        $product->update(['stock_qty' => 99]);
        $this->assertSame('В наличии', $product->fresh()->availabilityLabel());
        $link->update(['stock_quantity' => 7, 'stock_status' => 'available']);
        $this->assertSame('В наличии: 7 шт.', $product->fresh()->publicAvailabilityLabel());
        $this->assertSame('В наличии: 7 шт.', Product::withSupplierChannels()->findOrFail($product->id)->publicAvailabilityLabel());
        $html = $this->get('/supplier-presence/omen-presence')->assertOk()->getContent();
        $this->assertStringContainsString('В наличии: 7 шт.', $html);
        $this->assertStringNotContainsString('99 шт.', $html);
        $supplier->update(['is_active' => false]);
        $this->assertSame('Доступно к заказу', $product->fresh()->availabilityLabel());
        $supplier->update(['is_active' => true]);
        $link->update(['last_stock_synced_at' => now()->subHours(25)]);
        $this->assertSame('Доступно к заказу', $product->fresh()->availabilityLabel());
        $product->update(['manual_stock_management' => true, 'in_stock' => true, 'availability_status' => 'in_stock', 'stock_qty' => 3]);
        $this->assertSame('В наличии: 3 шт.', $product->fresh()->publicAvailabilityLabel());
    }
}
