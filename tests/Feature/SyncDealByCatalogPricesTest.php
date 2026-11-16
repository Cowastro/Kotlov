<?php

namespace Tests\Feature;

use App\Console\Commands\SyncDealByCatalogPricesCommand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByClient;
use App\Services\Marketplaces\DealByPricePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncDealByCatalogPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_override_cannot_replace_the_catalog_as_price_source(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $card->update(['is_archived' => true]);
        Supplier::updateOrCreate(['code' => 'bania'], ['name' => 'Банька', 'is_active' => true]);
        $remote = $this->remote(1494);
        Storage::disk('local')->put('deal-by/bania-reviewed-retail-links.json', json_encode([100 => [
            'local_id' => $card->id, 'fingerprint' => app(DealByPricePlan::class)->fingerprint($remote['products'][0]),
            'retail_article' => 'CATALOG-18M', 'retail_name' => 'Печь ДТ-3',
        ]]));
        $file = storage_path('app/supplier-cache/bania-retail-pricelist.xlsx');
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([['Печь ДТ-3', 'CATALOG-18M', 'шт', 1900]], null, 'A4');
        (new Xlsx($sheet))->save($file);
        try {
            $this->mock(DealByClient::class, function ($mock) use ($remote): void {
                $mock->shouldReceive('listProducts')->once()->andReturn($remote);
                $mock->shouldNotReceive('editProducts');
            });
            $this->artisan('deal:sync-catalog-prices', ['--apply' => true])->assertFailed();
            Storage::disk('local')->assertMissing('deal-by/product-links.json');
        } finally {
            unlink($file);
        }
    }

    public function test_bania_imported_article_is_synced_in_supplier_scope_with_expired_discount(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $supplier = Supplier::updateOrCreate(['code' => 'bania'], ['name' => 'Банька', 'is_active' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $card->id,
            'supplier_article' => 'FACTORY-18M', 'match_status' => 'created_from_price_list',
            'raw' => ['source' => 'bania_google_wholesale_price_list']]);
        $before = $this->remote(1494);
        $before['products'][0]['sku'] = 'FACTORY-18M';
        $before['products'][0]['name'] = 'Supplier title';
        $before['products'][0]['discount'] = ['value' => 10, 'date_end' => '30.04.2025'];
        $after = $before;
        $after['products'][0]['price'] = 1852;
        $this->mock(DealByClient::class, function ($mock) use ($before, $after): void {
            $mock->shouldReceive('listProducts')->twice()->andReturn($before, $after);
            $mock->shouldReceive('editProducts')->once()->with([['id' => 100, 'price' => 1852.0]])->andReturn([]);
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true, '--supplier' => 'bania'])
            ->expectsOutputToContain('metric.deal_catalog_prices.linked=1')->assertSuccessful();
    }

    public function test_untrusted_import_and_supplier_scope_cannot_bypass_identity_checks(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $supplier = Supplier::updateOrCreate(['code' => 'bania'], ['name' => 'Банька', 'is_active' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $card->id,
            'supplier_article' => 'UNTRUSTED', 'match_status' => 'created_from_price_list', 'raw' => ['source' => 'unknown']]);
        $other = $card->replicate()->forceFill(['sku' => 'OTHER', 'slug' => 'other-card', 'name' => 'Other model']);
        $other->save();
        $remote = $this->remote(1494);
        $remote['products'][0]['name'] = 'Other model';
        $remote['products'][] = ['id' => 99, 'sku' => 'UNTRUSTED', 'name' => 'Unknown', 'price' => 100, 'currency' => 'BYN'];
        $this->mock(DealByClient::class, function ($mock) use ($remote): void {
            $mock->shouldReceive('listProducts')->once()->andReturn($remote);
            $mock->shouldNotReceive('editProducts');
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true, '--supplier' => 'bania'])->assertFailed();
        Storage::disk('local')->assertMissing('deal-by/product-links.json');
    }

    private function card(): Product
    {
        $category = Category::query()->create(['name' => 'Печи', 'slug' => 'deal-catalog-test', 'is_active' => true]);

        return Product::query()->create(['category_id' => $category->id, 'name' => 'Печь 18 М', 'slug' => 'deal-catalog-stove',
            'sku' => 'CATALOG-18M', 'price' => 1852, 'currency' => 'BYN', 'is_active' => true, 'is_archived' => false, 'requires_price_confirmation' => false]);
    }

    private function remote(float $price): array
    {
        return ['products' => [['id' => 100, 'sku' => 'CATALOG-18M', 'name' => 'Печь 18 М', 'price' => $price, 'currency' => 'BYN']]];
    }

    public function test_preview_does_not_write_remote_prices_or_permanent_links(): void
    {
        Storage::fake('local');
        $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->once()->andReturn($this->remote(1494));
            $mock->shouldNotReceive('editProducts');
        });
        $this->artisan('deal:sync-catalog-prices')->expectsOutputToContain('metric.deal_catalog_prices.update=1')->assertSuccessful();
        Storage::disk('local')->assertMissing('deal-by/product-links.json');
    }

    public function test_apply_verifies_api_and_persists_the_stable_mapping(): void
    {
        Storage::fake('local');
        $card = $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->twice()->andReturn($this->remote(1494), $this->remote(1852));
            $mock->shouldReceive('editProducts')->once()->with([['id' => 100, 'price' => 1852.0]])->andReturn([]);
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true])->expectsOutputToContain('metric.deal_catalog_prices.linked=1')->assertSuccessful();
        $links = json_decode(Storage::disk('local')->get('deal-by/product-links.json'), true);
        $this->assertSame($card->id, $links[100]['local_id']);
        $this->assertSame(1852.0, (float) $card->fresh()->price);
    }

    public function test_an_accepted_write_with_unchanged_api_price_is_a_failure_and_is_not_linked(): void
    {
        Storage::fake('local');
        $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->twice()->andReturn($this->remote(1494));
            $mock->shouldReceive('editProducts')->once()->andReturn([]);
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true])->expectsOutputToContain('metric.deal_catalog_prices.verification_failures=1')->assertFailed();
        $this->assertSame([], json_decode(Storage::disk('local')->get('deal-by/product-links.json'), true));
    }

    public function test_malformed_catalog_cannot_trigger_partial_price_writes(): void
    {
        Storage::fake('local');
        $this->card();
        $this->mock(DealByClient::class, function ($mock): void {
            $mock->shouldReceive('listProducts')->once()->andReturn(['unexpected' => []]);
            $mock->shouldNotReceive('editProducts');
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true])->assertFailed();
    }

    public function test_deal_by_legacy_byr_label_keeps_modern_byn_amount_without_conversion(): void
    {
        Storage::fake('local');
        $this->card();
        config()->set('services.deal_by.base_url', 'https://my.deal.by/api/v1');
        $before = $this->remote(1494);
        $after = $this->remote(1852);
        $before['products'][0]['currency'] = $after['products'][0]['currency'] = 'BYR';
        $this->mock(DealByClient::class, function ($mock) use ($before, $after): void {
            $mock->shouldReceive('listProducts')->twice()->andReturn($before, $after);
            $mock->shouldReceive('editProducts')->once()->with([['id' => 100, 'price' => 1852.0]])->andReturn([]);
        });
        $this->artisan('deal:sync-catalog-prices', ['--apply' => true])
            ->expectsOutputToContain('metric.deal_catalog_prices.legacy_byr_labels=1')
            ->expectsOutputToContain('metric.deal_catalog_prices.verification_failures=0')->assertSuccessful();
    }

    public function test_byr_is_not_reinterpreted_for_another_api_endpoint(): void
    {
        Storage::fake('local');
        $this->card();
        config()->set('services.deal_by.base_url', 'https://other.marketplace.test/api/v1');
        $payload = $this->remote(1494);
        $payload['products'][0]['currency'] = 'BYR';
        $this->mock(DealByClient::class, function ($mock) use ($payload): void {
            $mock->shouldReceive('listProducts')->once()->andReturn($payload);
            $mock->shouldNotReceive('editProducts');
        });
        $this->artisan('deal:sync-catalog-prices')
            ->expectsOutputToContain('metric.deal_catalog_prices.unsupported_currency=1')->assertSuccessful();
    }

    public function test_price_reader_does_not_retain_descriptions_or_image_galleries(): void
    {
        $payload = $this->remote(1494);
        $payload['products'][0]['description'] = str_repeat('Large catalog description ', 10000);
        $payload['products'][0]['images'] = array_fill(0, 100, ['url' => 'https://images.example.test/photo.jpg']);
        $client = $this->mock(DealByClient::class);
        $client->shouldReceive('listProducts')->once()->andReturn($payload);
        $method = new \ReflectionMethod(SyncDealByCatalogPricesCommand::class, 'loadProducts');
        $row = $method->invoke(new SyncDealByCatalogPricesCommand, $client)->first();
        $this->assertSame(100, $row['id']);
        $this->assertSame(1494.0, $row['price']);
        $this->assertArrayNotHasKey('description', $row);
        $this->assertArrayNotHasKey('images', $row);
    }
}
