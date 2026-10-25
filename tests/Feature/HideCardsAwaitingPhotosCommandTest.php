<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HideCardsAwaitingPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_cards_without_a_photo_are_hidden_and_come_back_with_one(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'in-lain-nasosy'], ['name' => 'Ин-лайн насосы', 'parent_id' => 0, 'is_active' => true]);
        $supplier = Supplier::query()->create(['code' => 'tsk_nasosy', 'name' => 'ТСК Насосы', 'is_active' => true]);
        $bare = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 900, 'name' => 'Центробежный насос ECL 40-160', 'slug' => 'ecl-40-160']);
        $photo = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 900, 'name' => 'Центробежный насос ECL 50-160', 'slug' => 'ecl-50-160', 'images' => ['img/ecl.webp']]);
        foreach ([$bare, $photo] as $i => $card) {
            SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $card->id, 'supplier_article' => 'A'.$i, 'supplier_article_normalized' => 'A'.$i, 'match_status' => 'matched']);
        }

        $this->artisan('catalog:hide-cards-awaiting-photos', ['--supplier' => 'tsk_nasosy', '--apply' => true])
            ->expectsOutputToContain('metric.awaiting_photos.hidden=1')
            ->assertSuccessful();
        $this->assertFalse($bare->fresh()->is_active);
        $this->assertSame(Product::CREATED_VIA_AWAITING_PHOTOS, $bare->fresh()->created_via);
        $this->assertTrue($photo->fresh()->is_active);

        $bare->forceFill(['images' => ['img/found.webp']])->save();
        $this->artisan('catalog:hide-cards-awaiting-photos', ['--publish' => true, '--apply' => true])
            ->expectsOutputToContain('metric.awaiting_photos.published=1')
            ->assertSuccessful();
        $this->assertTrue($bare->fresh()->is_active);
        $this->assertNull($bare->fresh()->created_via);
    }
}
