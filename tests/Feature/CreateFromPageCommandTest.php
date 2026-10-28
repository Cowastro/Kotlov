<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateFromPageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_1c_row_becomes_a_card_with_the_page_title_and_its_own_photos(): void
    {
        Category::query()->create(['name' => 'Печное литьё', 'slug' => 'pechnoe-i-kaminnoe-lite', 'is_active' => true]);
        $source = IntegrationSource::query()->firstOrCreate(['code' => 'onec'], ['name' => '1С']);
        $item = IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'x-7767', 'external_code' => 'УТ-00007767',
            'name' => 'Плита 2х конфорочная П2-3 Б 710x410мм', 'match_status' => 'unmatched']);
        Http::fake([
            'www.21vek.by/*' => Http::response('<h1 class="t">Плита для печи Балезинский ЛМЗ П2-3 Б</h1>'
                .'"https://cdn21vek.by/img/galleries/1/2/23_balezinskijlmz_a.jpeg" "https://cdn21vek.by/img/galleries/9/9/other_b.jpeg"'),
            'cdn21vek.by/*' => Http::response(str_repeat('x', 10), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->artisan('integration:create-from-page', ['code' => 'УТ-00007767', 'category' => 'pechnoe-i-kaminnoe-lite', 'price' => '181',
            'url' => 'https://www.21vek.by/sauna_boilers/23_balezinskijlmz.html', '--apply' => true])
            ->expectsOutputToContain("audit.create_from_page.card={$item->id}:plita-dlia-peci-balezinskii-lmz-p2-3-b:181.00:pechnoe-i-kaminnoe-lite:1")
            ->assertSuccessful();

        $card = Product::query()->find($item->fresh()->product_id);
        $this->assertSame('Плита для печи Балезинский ЛМЗ П2-3 Б', $card->name);
        $this->assertSame(181.0, (float) $card->price);
    }

    public function test_a_row_without_a_retail_price_can_be_created_as_price_on_request(): void
    {
        Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], ['name' => 'Крепления дымохода', 'is_active' => true]);
        $source = IntegrationSource::query()->firstOrCreate(['code' => 'onec'], ['name' => '1С']);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'aston-red',
            'name' => 'Мастер-флеш ASTON №6 красный',
            'match_status' => 'unmatched',
        ]);
        Http::fake([
            'example.com/*' => Http::response('<h1>Мастер-флеш ASTON №6 красный</h1><meta property="og:image" content="https://example.com/aston.jpg">'),
            'example.com/aston.jpg' => Http::response(str_repeat('x', 10), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->artisan('integration:create-from-page', [
            'code' => 'aston-red',
            'category' => 'krepleniya-dymohod',
            'price' => '0',
            'url' => 'https://example.com/aston',
            '--allow-price-request' => true,
            '--apply' => true,
        ])->expectsOutputToContain("audit.create_from_page.card={$item->id}:master-fles-aston-6-krasnyi:0.00:krepleniya-dymohod:1")
            ->assertSuccessful();

        $card = Product::query()->find($item->fresh()->product_id);
        $this->assertSame(0.0, (float) $card->price);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_zero_price_still_requires_an_explicit_price_request_flag(): void
    {
        Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], ['name' => 'Крепления дымохода', 'is_active' => true]);
        $source = IntegrationSource::query()->firstOrCreate(['code' => 'onec'], ['name' => '1С']);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'aston-red',
            'name' => 'Мастер-флеш ASTON №6 красный',
            'match_status' => 'unmatched',
        ]);
        Http::fake(['example.com/*' => Http::response('<h1>Мастер-флеш ASTON №6 красный</h1>')]);

        $this->artisan('integration:create-from-page', [
            'code' => 'aston-red',
            'category' => 'krepleniya-dymohod',
            'price' => '0',
            'url' => 'https://example.com/aston',
        ])->expectsOutputToContain('audit.create_from_page.skipped=aston-red:no_price')
            ->assertSuccessful();

        $this->assertNull($item->fresh()->product_id);
    }
}
