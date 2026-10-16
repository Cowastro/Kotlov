<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\OmenergoCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class EnrichOmenergoCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $cardUrl = 'https://omenergo.by/katalog/test-omenergo-card/';

    private string $imageUrl = 'https://omenergo.by/image/testing/omenergo-unit-photo.png';

    private array $generatedFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function fixture(): Product
    {
        Storage::fake('local');
        $supplier = Supplier::create(['code' => 'omenergo', 'name' => 'ОмЭнерго', 'is_active' => true]);
        $category = Category::create(['name' => 'Стабилизаторы тест', 'slug' => 'omen-enrichment-test']);
        $product = Product::create([
            'name' => 'Стабилизатор напряжения Энергия Voltron 500 (HP)', 'slug' => 'omen-enrichment-test',
            'sku' => 'OMENERGO-E0101-0153', 'category_id' => $category->id, 'is_active' => false,
            'images' => [], 'specs' => [], 'price' => 226.35,
        ]);
        SupplierProduct::create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'Е0101-0153',
            'raw' => ['article' => 'Е0101-0153', 'article_key' => 'E0101-0153', 'name' => 'Энергия Voltron 500 (HP)',
                'category' => 'однофазные стабилизаторы напряжения', 'brand' => 'Энергия', 'country' => 'Китай',
                'warranty_months' => 36, 'weight_kg' => 3.5, 'kit' => 'Руководство и крепёж', 'description' => 'Релейный, 500 ВА, 5% точность.'],
        ]);
        $entry = ['name' => 'Стабилизатор напряжения Энергия Voltron 500', 'url' => $this->cardUrl];
        $this->partialMock(OmenergoCatalog::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('index')->once()->andReturn([$entry]);
        });
        $html = '<h1>Стабилизатор напряжения Энергия Voltron 500</h1>'
            .'<div class="us-product-info-item-manufacturer"><a>Энергия</a></div>'
            .'<a class="oct-gallery" href="'.$this->imageUrl.'">Фото</a>'
            .'<div class="us-product-description-cont"><table><tr><td>Мощность</td><td>500 ВА</td></tr>'
            .'<tr><td>Фазность</td><td>1</td></tr><tr><td>Тип стабилизатора</td><td>релейный</td></tr></table></div>';
        Http::fake([$this->cardUrl => Http::response($html), $this->imageUrl => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $this->generatedFiles[] = public_path('img/products/omenergo/e0101-0153-1-'.substr(hash('sha256', $this->imageUrl), 0, 10).'.png');

        return $product;
    }

    public function test_verified_images_and_specs_are_saved_before_publishing(): void
    {
        $product = $this->fixture();
        $this->artisan('supplier:enrich-omenergo', ['--apply' => true, '--publish-complete' => true])->assertSuccessful();
        $product->refresh();
        $this->assertTrue($product->is_active);
        $this->assertCount(1, $product->images);
        $this->assertFileExists(public_path($product->images[0]));
        $this->assertGreaterThanOrEqual(200, getimagesize(public_path($product->images[0]))[0]);
        $this->assertStringContainsString('Главное о модели', $product->content);
        $this->assertStringContainsString('500 ВА', $product->content);
        $this->assertNotContains('Технические особенности', array_column($product->specs, 'key'));
        $this->assertSame('226.35', $product->price);
    }

    public function test_dry_run_does_not_change_or_publish_product_or_download_photo(): void
    {
        $product = $this->fixture();
        $before = $product->fresh()->getAttributes();
        $this->artisan('supplier:enrich-omenergo', ['--dry-run' => true, '--publish-complete' => true])->assertSuccessful();
        $this->assertSame($before, $product->fresh()->getAttributes());
        Http::assertNotSent(fn ($request) => $request->url() === $this->imageUrl);
    }

    private function png(): string
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $raw = str_repeat("\0".str_repeat("\0\0\0", 220), 220);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 220, 220, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress($raw)).$chunk('IEND', '');
    }
}
