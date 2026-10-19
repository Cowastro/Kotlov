<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncSantehskladPricelistCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_exact_cards_updates_all_exact_duplicates_and_never_invents_stock(): void
    {
        $brand = Brand::create(['name' => 'Sakovich', 'slug' => 'sakovich-test', 'is_active' => true]);
        $category = Category::create(['name' => 'Котлы', 'slug' => 'santehsklad-test']);
        $detailed = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Твердотопливный котел Sakovich Standart 12',
            'slug' => 'sakovich-standart-12-detailed-test',
            'sku' => 'KOTLOV-SAKOVICH-12',
            'price' => 1950,
            'content' => str_repeat('Подробное описание ', 100),
            'images' => ['image-1.jpg'],
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'requires_price_confirmation' => true,
        ]);
        $duplicate = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Твердотопливный котел Sakovich Standart 12 кВт (сталь 4 мм)',
            'slug' => 'sakovich-standart-12-duplicate-test',
            'sku' => 'PS-011.212',
            'price' => 2050,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $praktik = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Пеллетный котел Sakovich Pellet Standart 15 с горелкой Praktik и бункером',
            'slug' => 'sakovich-pellet-standart-praktik-15-test',
            'sku' => 'SAKOVICH-PRAKTIK-15',
            'price' => 6600,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $accessory = Product::create([
            'category_id' => $category->id,
            'name' => 'Регулятор тяги ICMA',
            'slug' => 'regulyator-tyagi-icma-test',
            'sku' => 'ICMA-DRAFT-TEST',
            'price' => 130,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $this->artisan('supplier:sync-santehsklad-pricelist', ['--dry-run' => true])
            ->expectsOutput('metric.santehsklad.rows=103')
            ->expectsOutput('metric.santehsklad.matched=3')
            ->expectsOutput('metric.santehsklad.unmatched=100')
            ->expectsOutput('metric.santehsklad.duplicate_card_groups=1')
            ->expectsOutput('metric.santehsklad.invalid_price_pairs=0')
            ->assertSuccessful();
        $this->assertDatabaseMissing('suppliers', ['code' => 'santehsklad']);

        $this->artisan('supplier:sync-santehsklad-pricelist', [
            '--apply' => true,
            '--sync-retail-prices' => true,
        ])->assertSuccessful();

        $supplier = Supplier::where('code', 'santehsklad')->firstOrFail();
        $this->assertSame(103, $supplier->supplierProducts()->count());
        $offer = $supplier->supplierProducts()->where('supplier_article_normalized', 'STANDART-12')->firstOrFail();
        $this->assertSame($detailed->id, $offer->product_id);
        $this->assertSame('1732.50', $offer->price_byn);
        $this->assertFalse($offer->in_stock);
        $this->assertNull($offer->stock_quantity);
        $this->assertSame('unknown', $offer->stock_status);
        $this->assertNull($offer->last_stock_synced_at);

        $this->assertSame('2250.00', $detailed->fresh()->price);
        $this->assertSame('2250.00', $duplicate->fresh()->price);
        $this->assertFalse($detailed->fresh()->requires_price_confirmation);
        $this->assertSame(Product::AVAILABILITY_CHECK, $detailed->fresh()->effectiveAvailabilityStatus());
        $this->assertSame('6800.00', $praktik->fresh()->price);
        $this->assertSame('150.00', $accessory->fresh()->price);
    }

    public function test_it_can_create_and_link_every_current_pricelist_row_without_inventing_stock(): void
    {
        foreach ([
            'tverdotoplivnye' => 'Твердотопливные котлы',
            'kotly-na-pelletah' => 'Пеллетные котлы',
            'burzhuiki-pechi' => 'Отопительные печи',
            'pelletnye-gorelki' => 'Пеллетные горелки',
            'komplektuyushhie-dlya-otopleniya' => 'Комплектующие',
        ] as $slug => $name) {
            Category::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
        }
        $productCountBefore = Product::query()->count();

        $this->artisan('supplier:sync-santehsklad-pricelist', [
            '--apply' => true,
            '--sync-retail-prices' => true,
            '--create-missing-products' => true,
        ])
            ->expectsOutput('metric.santehsklad.unlinked=0')
            ->expectsOutput('metric.santehsklad.products_created=103')
            ->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'santehsklad')->firstOrFail();
        $this->assertSame(103, $supplier->supplierProducts()->whereNotNull('product_id')->count());
        $this->assertSame($productCountBefore + 103, Product::query()->count());
        $this->assertSame(0, $supplier->supplierProducts()->whereHas('product', fn ($query) => $query->where('in_stock', true))->count());

        $legacyBurner = $supplier->supplierProducts()
            ->where('supplier_article_normalized', 'ACCESSORY-22C200B9E5E180B28724')
            ->firstOrFail();
        $this->assertStringContainsString('KIPI Rotary 4–16 кВт', $legacyBurner->product->name);
        $this->assertStringContainsString('KIPI Rotary 5-16 кВт', $legacyBurner->supplier_name);
        $this->assertSame(Product::AVAILABILITY_CHECK, $legacyBurner->product->availability_status);
    }
}
