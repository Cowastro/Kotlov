<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishAtmosCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_cards_with_photos_specs_and_supplier_links(): void
    {
        Storage::fake('public');
        Category::query()->firstOrCreate(['slug' => 'tverdotoplivnye'], ['name' => 'Твердотопливные котлы', 'parent_id' => 0, 'is_active' => true]);
        Supplier::query()->create(['code' => 'atmos', 'name' => 'ТД «АТМОС»', 'currency' => 'BYN', 'is_active' => true]);
        $page = (string) file_get_contents(base_path('tests/Fixtures/atmos-price-page.html'));
        $jpeg = str_repeat('x', 5000);
        Http::fake([
            'price.teplota.by/ajax/show_image' => Http::response(['Name' => 'BURNiT', 'Image' => 'uploads/goods/img_1.jpg']),
            'price.teplota.by/uploads/*' => Http::response($jpeg),
            'price.teplota.by/prices*' => Http::response(str_repeat($page, 34)),
            'price.teplota.by/' => Http::response('<a href = "/prices?section=1&brand=3 ">BURNIT</a>'),
            'teplota.by/index.pl*' => Http::response('<a href="https://teplota.by/img/misc/1.jpg">1</a><a href="https://teplota.by/img/misc/2.jpg">2</a>'),
            'teplota.by/img/*' => Http::response($jpeg),
        ]);

        $before = Product::query()->count();
        $this->artisan('catalog:publish-atmos-cards')
            ->expectsOutputToContain('metric.atmos_cards.planned=3')
            ->expectsOutputToContain('metric.atmos_cards.duplicate=99')
            ->assertSuccessful();
        $this->assertSame($before, Product::query()->count());

        $this->artisan('catalog:publish-atmos-cards --apply --limit=2')->assertSuccessful();
        $this->artisan('catalog:publish-atmos-cards --apply')->expectsOutputToContain('metric.atmos_cards.linked=68')->assertSuccessful();

        $this->assertSame($before + 3, Product::query()->count());
        $card = Product::query()->where('name', 'Твердотопливный котел BURNiT WBS AC 30')->sole();
        $this->assertSame('5865.00', (string) $card->price);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $card->availability_status);
        $this->assertCount(3, $card->images);
        Storage::disk('public')->assertExists($card->images[0]);
        $this->assertSame('burnit', $card->brand->slug);
        $this->assertSame('in_stock', $card->supplierProducts()->sole()->stock_status);
        $later = Product::query()->where('name', 'Твердотопливный котел BURNiT WBS AC 50')->sole();
        $this->assertSame(Product::AVAILABILITY_CHECK, $later->availability_status);
        $this->assertSame('preorder', $later->supplierProducts()->sole()->stock_status);
    }
}
