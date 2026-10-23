<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Suppliers\SupplierOfferCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierOfferCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_price_row_without_a_card_becomes_a_hidden_card_for_a_commercial_offer(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'tsirkulyatsionnyie'], ['name' => 'Циркуляционные насосы', 'parent_id' => 0, 'is_active' => true]);
        Brand::query()->create(['name' => 'WELLMIX', 'slug' => 'wellmix', 'is_active' => true]);
        Http::fake([
            'aqualider.by/sitemap.xml' => Http::response('<urlset><url><loc>https://aqualider.by/p/sdvoennyy-cirkulyacionnyy-wellmix-wrs-d-50-120sf-17109972</loc></url></urlset>'),
            '*' => Http::response('<html><body><h1>Сдвоенный циркуляционный насос Wellmix WRS D 50-120SF</h1></body></html>'),
        ]);

        $csv = tempnam(sys_get_temp_dir(), 'tsk').'.csv';
        file_put_contents($csv, implode("\n", [
            'Артикул,,Наименование,Бренд,Модель,Опт 1,МРЦ,Наличие',
            '17109972,,"WRS D 50-120SF (3скорости,220V)",WELLMIX,"WRS D 50-120SF (3скорости,220V)","3549,43","4617,14",в наличии',
        ]));
        $this->artisan('supplier:sync-tsk-nasosy', ['--apply' => true, '--stock-file' => $csv])->assertSuccessful();

        $offer = SupplierProduct::query()->where('supplier_article', '17109972')->sole();
        $this->assertNull($offer->product_id);
        $this->assertSame('unmatched', $offer->match_status);

        $cards = app(SupplierOfferCards::class);
        $found = $cards->search('WRS D 50-120');
        $this->assertArrayHasKey($offer->id, $found);
        $this->assertStringContainsString('РРЦ 4 617,14', $found[$offer->id]);
        $this->assertSame($category->id, $cards->suggestedCategoryId($offer->id));

        $product = $cards->create($offer->id);

        $this->assertFalse($product->is_active);
        $this->assertSame(Product::CREATED_VIA_COMMERCIAL_OFFER, $product->created_via);
        $this->assertSame(4617.14, (float) $product->price);
        $this->assertSame('WELLMIX WRS D 50-120SF (3скорости,220V)', $product->name);
        $this->assertSame('https://aqualider.by/p/sdvoennyy-cirkulyacionnyy-wellmix-wrs-d-50-120sf-17109972', $product->content_source_url);
        $this->assertSame($product->id, (int) $offer->fresh()->product_id);
        $this->assertSame([], $cards->search('WRS D 50-120'));
        // Creating again returns the same card instead of a duplicate.
        $this->assertSame($product->id, $cards->create($offer->id)->id);

        // The next sync keeps the link on the new card.
        $this->artisan('supplier:sync-tsk-nasosy', ['--apply' => true, '--stock-file' => $csv])->assertSuccessful();
        $this->assertSame($product->id, (int) $offer->fresh()->product_id);
    }
}
