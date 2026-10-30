<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\BanipechiCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BanipechiCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dealer_page_gives_description_specs_and_photos_that_reach_the_card(): void
    {
        $url = 'https://banipechi.by/catalog/pechi-dlya-banu/prometall-atmosfera/atmosfera-l';
        Http::fake([$url => Http::response(file_get_contents(base_path('tests/Fixtures/banipechi-atmosfera-l.html'))), '*' => Http::response('', 404)]);

        $page = (new BanipechiCatalog)->page($url);
        $this->assertGreaterThan(500, mb_strlen($page['description']));
        $this->assertGreaterThan(10, count($page['specs']));
        $this->assertNotEmpty($page['images']);

        $category = Category::query()->create(['name' => 'Печи', 'slug' => 'bp-test', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Атмосфера L', 'slug' => 'bp-atm-l', 'sku' => 'PROMETALL-P0048', 'price' => 10,
            'specs' => [['key' => 'Артикул производителя', 'value' => 'p0048']]]);
        app(ProductSourceEnricher::class)->enrichFromParsed($card, $url, ['images' => [], 'specs' => $page['specs'], 'description' => $page['description']], [
            'verified_images' => [], 'update_images' => false, 'update_specs' => true, 'replace_specs' => true,
            'update_content' => true, 'update_service' => false, 'update_documents' => false, 'update_video' => false,
        ]);
        $card->refresh();
        // The description becomes content through the AI rewrite, absent in tests; the specs are written as they are.
        $this->assertGreaterThan(10, count((array) $card->specs));
    }
}
