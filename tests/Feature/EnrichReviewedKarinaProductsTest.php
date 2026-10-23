<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\PriceList\EnrichReviewedKarinaProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EnrichReviewedKarinaProductsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $secondComplete = false): array
    {
        $category = Category::create(['name' => 'KARINA enrichment', 'slug' => 'karina-enrichment-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::create(['name' => 'KARINA', 'slug' => 'karina-enrichment-brand']);
        $products = collect([
            ['name' => 'Электрическая печь KARINA Lite 6', 'slug' => 'created-lite-6'],
            ['name' => 'Электрическая печь KARINA Classic Steam 6', 'slug' => 'created-classic-steam-6'],
            ['name' => 'Электрическая печь KARINA Forta 99', 'slug' => 'created-forta-99'],
        ])->map(fn (array $row) => Product::create([
            'category_id' => $category->id, 'brand_id' => $brand->id,
            'name' => $row['name'], 'slug' => $row['slug'], 'price' => 1000,
            'is_active' => false, 'is_archived' => false, 'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => true,
            'created_via' => EnrichReviewedKarinaProducts::CREATED_VIA,
        ]));

        $liteUrl = 'https://karina.market/series/elektricheskaya-pech-karina-lite-6';
        $steamUrl = 'https://karina.market/series/elektricheskaya-pech-karina-classic-steam-6';
        $listing = '<a href="'.$liteUrl.'">Lite</a><a href="'.$steamUrl.'">Steam</a>';
        Http::fake(function (Request $request) use ($listing, $liteUrl, $steamUrl, $secondComplete) {
            if (str_contains($request->url(), '/elektricheskie-pechi/')) {
                return Http::response($listing);
            }
            if ($request->url() === $liteUrl) {
                return Http::response($this->officialPage('lite-6.jpg', true));
            }
            if ($request->url() === $steamUrl) {
                return Http::response($this->officialPage('steam-6.jpg', $secondComplete));
            }

            return Http::response('', 404);
        });

        $report = [
            'schema' => 'karina-audit-v1', 'source_currency' => 'RUB',
            'currency_confirmed' => true, 'applied' => false,
            'workbook' => ['sha256' => str_repeat('a', 64)],
            'products' => $products->map->toArray()->all(),
        ];

        return [$products, $report];
    }

    public function test_official_content_dry_run_then_apply_activates_only_complete_exact_card(): void
    {
        [$products, $report] = $this->fixture();
        $service = new EnrichReviewedKarinaProducts;
        $plan = $service->buildPlan($report);

        $this->assertSame(3, $plan['summary']['created_products']);
        $this->assertSame(2, $plan['summary']['official_pages']);
        $this->assertSame(1, $plan['summary']['eligible']);
        $this->assertSame(1, $plan['summary']['incomplete']);
        $this->assertSame(1, $plan['summary']['not_found']);

        $preview = $service->apply($report, $plan);
        $this->assertSame(1, $preview['planned']);
        $this->assertSame(0, $preview['activated']);
        $this->assertFalse($products[0]->fresh()->is_active);

        $result = $service->apply($report, $plan, true);
        $ready = $products[0]->fresh();
        $this->assertSame(1, $result['activated']);
        $this->assertTrue($ready->is_active);
        $this->assertFalse($ready->in_stock);
        $this->assertFalse($ready->requires_price_confirmation);
        $this->assertSame(['https://karina.market/image/catalog/lite-6.jpg'], $ready->images);
        $this->assertCount(3, $ready->specs);
        $this->assertSame(1000.0, (float) $ready->price);
        $this->assertSame('https://karina.market/series/elektricheskaya-pech-karina-lite-6', $ready->content_source_url);
        $this->assertFalse($products[1]->fresh()->is_active);
        $this->assertFalse($products[2]->fresh()->is_active);
    }

    public function test_changed_second_product_blocks_whole_enrichment_before_any_write(): void
    {
        [$products, $report] = $this->fixture(true);
        $service = new EnrichReviewedKarinaProducts;
        $plan = $service->buildPlan($report);
        $this->assertSame(2, $plan['summary']['eligible']);
        $products[1]->update(['price' => 1200]);

        try {
            $service->apply($report, $plan, true);
            $this->fail('Changed catalog should block the enrichment.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('catalog changed', $e->getMessage());
        }

        $this->assertFalse($products[0]->fresh()->is_active);
        $this->assertFalse($products[1]->fresh()->is_active);
        $this->assertNull($products[0]->fresh()->content_source_url);
    }

    private function officialPage(string $image, bool $withDescription): string
    {
        $description = $withDescription
            ? '<div id="tab-description"><div itemprop="description"><p>Official verified description with enough text for safe publication of this exact KARINA model and configuration.</p></div></div>'
            : '<div id="tab-description"><div itemprop="description"></div></div>';

        return '<html><head><meta property="og:image" content="https://karina.market/image/catalog/'.$image.'"></head><body>'
            .'<div id="tab-specification"><table>'
            .'<tr><td>Мощность</td><td>6 кВт</td></tr>'
            .'<tr><td>Напряжение</td><td>220 В</td></tr>'
            .'<tr><td>Монтаж</td><td>Напольный</td></tr>'
            .'</table></div>'.$description.'</body></html>';
    }
}
