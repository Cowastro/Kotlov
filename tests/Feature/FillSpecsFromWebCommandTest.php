<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Suppliers\WebProductSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FillSpecsFromWebCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_takes_the_page_naming_the_model_and_lists_it_in_the_dry_run(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'komplektuyushchie-dlya-nasosov'], ['name' => 'Комплектующие для насосов', 'parent_id' => 0, 'is_active' => true]);
        $relay = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 40, 'name' => 'Реле давления UNIPUMP PM5', 'slug' => 'rele-pm5']);
        $this->mock(WebProductSources::class)->shouldReceive('search')->once()->andReturn([
            'https://shop.by/catalog/rele-davleniya' => 'shop.by · Реле давления',
            'https://pumps.by/product/unipump-pm5-rele-davleniya' => 'pumps.by · UNIPUMP PM5',
        ]);
        Http::fake(['*' => Http::response('<html><body><h1>PM5</h1></body></html>')]);

        $this->artisan('catalog:fill-specs-from-web', ['--products' => (string) $relay->id])
            ->expectsOutputToContain("audit.web_specs.card={$relay->id}:rele-pm5:pumps-by:product-unipump-pm5-rele-davleniya")
            ->expectsOutputToContain('metric.web_specs.page_found=1')
            ->assertSuccessful();
    }
}
