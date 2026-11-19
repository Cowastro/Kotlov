<?php

namespace Tests\Feature;

use App\Filament\Resources\Evropochta\Pages\ListPackagingProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\Shipping\EvropochtaPackaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EvropochtaPackagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_specifications_become_a_conservative_packing_suggestion(): void
    {
        $product = $this->product([
            ['key' => 'Габариты', 'value' => '110х66х55', 'unit' => 'мм'],
            ['key' => 'Вес , кг', 'value' => '0,3'],
        ]);
        $packaging = app(EvropochtaPackaging::class);

        $suggestion = $packaging->suggest($product);
        $this->assertSame([11.0, 6.6, 5.5], $suggestion['item_dimensions']);
        $this->assertSame('box-20-15-10', $suggestion['box']);
        $this->assertSame(1, $suggestion['weight']);
        $this->assertNull($product->shipping_parcel);
        $this->assertTrue($packaging->approve($product));
        $this->assertFalse($packaging->approve($product));
        $this->assertEquals($suggestion, $product->fresh()->shipping_parcel);
    }

    public function test_factory_box_is_preferred_and_an_existing_manual_parcel_is_preserved(): void
    {
        $product = $this->product([
            'Габариты' => '110х66х55 мм',
            'Вес , кг' => '0,3',
            'Габариты упаковки' => '13 × 8 × 7,5 см',
            'Вес брутто' => '780 г',
        ]);
        $packaging = app(EvropochtaPackaging::class);

        $suggestion = $packaging->suggest($product);
        $this->assertSame('factory', $suggestion['source']);
        $this->assertSame('box-24-24-20', $suggestion['box']);
        $this->assertSame(2, $suggestion['weight']);

        $manual = ['length' => 30, 'width' => 20, 'height' => 10, 'weight' => 0.85];
        $product->update(['shipping_parcel' => $manual]);
        $this->assertFalse($packaging->approve($product));
        $this->assertEquals($manual, $product->fresh()->shipping_parcel);
    }

    public function test_unitless_dimensions_remain_unconfirmed(): void
    {
        $product = $this->product(['Габариты' => '110х66х55', 'Вес' => '0,3 кг']);
        $this->assertNull(app(EvropochtaPackaging::class)->suggest($product));
        $this->assertFalse(app(EvropochtaPackaging::class)->approve($product));
    }

    public function test_elekhant_pilot_uses_a_weight_band_and_the_shipping_snapshot_keeps_the_box(): void
    {
        config(['services.evropochta.token' => 'testing', 'services.evropochta.checkout_enabled' => true]);
        $product = $this->product([], ['sku' => 'PS-007.059', 'slug' => 'schetchik-gaza-bytovoj-yelexant-sgb-18']);
        $migration = require database_path('migrations/2026_11_19_152000_enable_evropochta_elekhant_packaging_pilot.php');
        $migration->up();
        $parcel = app(EvropochtaCheckout::class)->parcel([['id' => $product->id, 'quantity' => 1]]);
        $this->assertSame(1.0, $parcel['weight']);
        $this->assertSame('band', $parcel['weight_mode']);
        $this->assertSame('box-24-24-20', $parcel['box']);
        $migration->down();
        $this->assertNull($product->fresh()->shipping_parcel);
    }

    public function test_bulk_confirmation_only_saves_products_with_a_suggested_box(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $candidate = $this->product(['Габариты' => '11 × 7 × 5 см', 'Вес' => '300 г']);
        $unknown = $this->product([], ['sku' => 'PACK-2', 'slug' => 'packaging-unknown']);

        Livewire::actingAs($admin)->test(ListPackagingProducts::class)
            ->searchTable('PACK-')
            ->assertCanSeeTableRecords([$candidate, $unknown])
            ->assertSee('К подтверждению')
            ->assertSee('Нужны данные')
            ->callTableBulkAction('approvePackaging', [$candidate, $unknown])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('box-20-15-10', $candidate->fresh()->shipping_parcel['box']);
        $this->assertNull($unknown->fresh()->shipping_parcel);
    }

    private function product(array $specs, array $attributes = []): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'packaging-fixture'], ['name' => 'Комплектующие', 'parent_id' => 0]);

        return Product::query()->create($attributes + [
            'category_id' => $category->id, 'name' => 'Счётчик', 'slug' => 'packaging-example',
            'sku' => 'PACK-1', 'price' => 100, 'specs' => $specs,
        ]);
    }
}
