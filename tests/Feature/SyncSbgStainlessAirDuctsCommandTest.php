<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Marketplaces\DealByClient;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncSbgStainlessAirDuctsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_and_completes_the_running_metre_stainless_range(): void
    {
        $category = Category::query()->create([
            'name' => 'Гибкие воздуховоды',
            'slug' => 'gibkie-vozduhovody',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create(['code' => 'onec', 'name' => '1С']);

        $existing = [];
        foreach ([100 => 16.80, 110 => 17.68] as $diameter => $price) {
            $existing[$diameter] = Product::query()->create([
                'category_id' => $category->id,
                'name' => "Воздуховод гибкий из нержавеющей стали Ø{$diameter} мм, 3 м",
                'slug' => "old-duct-{$diameter}",
                'sku' => "KOTLOV-TEST-{$diameter}",
                'price' => $price,
                'is_active' => true,
            ]);
            IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => "row-{$diameter}",
                'external_code' => "CODE-{$diameter}",
                'name' => "Воздуховод гибкий гофрированный {$diameter} (3м нерж.ст)",
                'product_id' => $existing[$diameter]->id,
                'match_status' => 'matched',
                'stock_quantity' => $diameter === 100 ? 3 : 8,
            ]);
        }

        $rows = [];
        foreach ([120 => 0.5, 130 => 6.5] as $diameter => $stock) {
            $rows[$diameter] = IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => "row-{$diameter}",
                'external_code' => "CODE-{$diameter}",
                'name' => "Воздуховод гибкий гофрированный {$diameter} (3м нерж.ст)",
                'match_status' => 'ambiguous',
                'stock_quantity' => $stock,
            ]);
        }

        $prices = [80 => 50, 100 => 50, 110 => 55, 120 => 59, 125 => 61, 130 => 63, 135 => 67, 140 => 70, 150 => 73, 200 => 93];
        $remote = [];
        foreach ($prices as $diameter => $price) {
            $remote[] = [
                'id' => $diameter,
                'sku' => 'ВНС'.$diameter,
                'name' => "Воздуховод {$diameter}",
                'price' => $price,
                'description' => "<p>Описание {$diameter}</p>",
                'main_image' => "https://images.deal.by/duct-{$diameter}.jpg",
            ];
        }
        $this->mock(DealByClient::class, fn ($mock) => $mock->shouldReceive('listProducts')->once()->andReturn(['products' => $remote]));
        $this->mock(ProductSourceEnricher::class, fn ($mock) => $mock->shouldReceive('enrichFromParsed')->times(10)->andReturn([]));
        $this->mock(SupplierProductAvailabilityService::class, fn ($mock) => $mock->shouldReceive('refresh')->times(10));

        $this->artisan('catalog:sync-sbg-stainless-air-ducts', ['--apply' => true])
            ->expectsOutputToContain('metric.sbg_stainless_duct.sizes=10')
            ->expectsOutputToContain('metric.sbg_stainless_duct.created=8')
            ->expectsOutputToContain('metric.sbg_stainless_duct.updated=2')
            ->expectsOutputToContain('metric.sbg_stainless_duct.linked=2')
            ->assertSuccessful();

        $this->assertSame(10, Product::query()->where('name', 'like', '%нержавеющей стали%')->count());
        $this->assertSame(50.0, (float) $existing[100]->fresh()->price);
        $this->assertSame('55.00', $existing[110]->fresh()->price);

        foreach ([120 => '0.5 м', 130 => '6.5 м'] as $diameter => $stockLabel) {
            $row = $rows[$diameter]->fresh();
            $this->assertSame('matched', $row->match_status);
            $this->assertSame('пог. м', $row->product->unit);
            $this->assertSame($prices[$diameter], (int) $row->product->price);
            $this->assertSame($stockLabel, $row->formattedStockQuantity());
        }
    }
}
