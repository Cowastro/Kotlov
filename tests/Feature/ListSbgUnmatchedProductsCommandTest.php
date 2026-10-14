<?php

namespace Tests\Feature;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ListSbgUnmatchedProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_the_current_sbg_positive_stock_unmatched_queue(): void
    {
        $source = IntegrationSource::query()->updateOrCreate([
            'code' => 'onec',
        ], [
            'name' => '1С СанБизнесГруп',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $otherSource = IntegrationSource::query()->create([
            'code' => 'other-source',
            'name' => 'Другой источник',
            'driver' => 'api',
            'is_active' => true,
        ]);

        $older = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'older-unmatched',
            'external_code' => 'УТ-OLD',
            'name' => 'Старая строка',
            'price_byn' => 12.34,
            'stock_quantity' => 2,
            'match_status' => 'unmatched',
            'last_seen_at' => now()->subMinute(),
        ]);
        $newer = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'newer-unmatched',
            'external_code' => 'УТ-NEW',
            'external_sku' => 'SKU-NEW',
            'name' => "Новая\nстрока",
            'price_byn' => 45.67,
            'stock_quantity' => 6,
            'match_status' => 'unmatched',
            'last_seen_at' => now(),
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'zero-unmatched',
            'name' => 'Нулевой остаток',
            'stock_quantity' => 0,
            'match_status' => 'unmatched',
            'last_seen_at' => now()->addMinute(),
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'matched-positive',
            'name' => 'Уже привязан',
            'stock_quantity' => 9,
            'match_status' => 'matched',
            'last_seen_at' => now()->addMinutes(2),
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $otherSource->id,
            'external_id' => 'other-positive',
            'name' => 'Чужая строка',
            'stock_quantity' => 8,
            'match_status' => 'unmatched',
            'last_seen_at' => now()->addMinutes(3),
        ]);

        $this->assertSame(0, Artisan::call('integration:list-sbg-unmatched', ['--limit' => 2]));

        $output = Artisan::output();
        $this->assertStringContainsString('source_found=1', $output);
        $this->assertStringContainsString('source_id='.$source->id, $output);
        $this->assertStringContainsString('source_code=onec', $output);
        $this->assertStringContainsString('metric.sbg_unmatched.count=2', $output);
        $this->assertStringContainsString('item.1={"id":'.$newer->id, $output);
        $this->assertStringContainsString('"external_code":"УТ-NEW"', $output);
        $this->assertStringContainsString('"name":"Новая строка"', $output);
        $this->assertStringContainsString('"stock_quantity":6', $output);
        $this->assertStringContainsString('item.2={"id":'.$older->id, $output);
        $this->assertStringNotContainsString('zero-unmatched', $output);
        $this->assertStringNotContainsString('matched-positive', $output);
        $this->assertStringNotContainsString('other-positive', $output);
    }
}
