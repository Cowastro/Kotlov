<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\BelomoOfficialMeterSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplyBelomoOfficialSpecsTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_every_execution_without_creating_products_changing_identity_price_or_stock(): void
    {
        Storage::fake('local');
        $category = Category::firstOrCreate(['slug' => 'schetchiki-gaza'], ['name' => 'Счётчики']);
        $cards = [];
        foreach (BelomoOfficialMeterSpecs::all() as $article => $profile) {
            $cards[] = Product::create(['category_id' => $category->id, 'sku' => 'SPEC-'.count($cards), 'slug' => 'spec-'.count($cards),
                'name' => 'Старая подпись L=250', 'specs' => ['Артикул производителя' => $article],
                'documents' => [['label' => 'Свой документ', 'url' => '/docs/own.pdf']],
                'price' => 299.12, 'stock_qty' => 13, 'in_stock' => false, 'requires_price_confirmation' => true,
                'is_active' => true, 'is_archived' => false]);
        }
        $count = Product::count();
        $this->artisan('catalog:apply-belomo-official-specs')->assertSuccessful();
        $this->assertSame('Старая подпись L=250', $cards[0]->fresh()->name);
        $this->artisan('catalog:apply-belomo-official-specs --apply')->assertSuccessful();
        foreach ($cards as $card) {
            $fresh = $card->fresh();
            $this->assertSame($card->sku, $fresh->sku);
            $this->assertSame($card->slug, $fresh->slug);
            $this->assertEquals(299.12, $fresh->price);
            $this->assertSame(13, $fresh->stock_qty);
            $this->assertTrue($fresh->requires_price_confirmation);
            $this->assertFalse($fresh->in_stock);
            $this->assertStringNotContainsString('L=', $fresh->name);
            $this->assertContains('/docs/own.pdf', array_column($fresh->documents, 'url'));
        }
        $before = array_map(fn ($p) => $p->fresh()->getAttributes(), $cards);
        $this->artisan('catalog:apply-belomo-official-specs --apply')->assertSuccessful();
        $this->assertSame($before, array_map(fn ($p) => $p->fresh()->getAttributes(), $cards));
        $this->assertSame($count, Product::count());
    }
}
