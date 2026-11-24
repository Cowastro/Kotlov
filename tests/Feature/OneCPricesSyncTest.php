<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCItemCosts;
use App\Services\Integrations\OneCBridge\OneCPrices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OneCPricesSyncTest extends TestCase
{
    use RefreshDatabase;

    private const METER = 'aaaaaaaa-0000-0000-0000-000000009758';

    private const CORD = 'aaaaaaaa-0000-0000-0000-000000007086';

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        [, $this->key] = OneCAgentToken::issue('test', $admin->id);
    }

    private function agent(): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->key, 'X-Kotlov-Agent' => '10']);
    }

    private function sendPart(int $task, string $name, array $rows): TestResponse
    {
        return $this->call('POST', '/1c/agent/tasks/'.$task.'/part?name='.$name.'&code=200', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->key,
            'HTTP_X_KOTLOV_AGENT' => '10', 'CONTENT_TYPE' => 'application/octet-stream'], json_encode(['value' => $rows], JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, list<array>> $parts */
    private function read(array $parts, bool $full = false): OneCAgentTask
    {
        $task = app(OneCPrices::class)->enqueue(null, $full);
        $this->agent()->get('/1c/agent/tasks/next')->assertOk();
        foreach ($parts as $name => $rows) {
            $this->sendPart($task->id, $name, $rows)->assertOk();
        }
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();

        return $task->fresh();
    }

    public function test_the_1c_retail_price_is_the_price_of_the_card_and_nothing_else_overwrites_it(): void
    {
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'schetchiki'], ['name' => 'Счётчики', 'is_active' => true]);
        $meter = Product::query()->create(['category_id' => $category->id, 'name' => 'Счётчик газа тестовый', 'slug' => 'schetchik', 'price' => 199]);
        $cord = Product::query()->create(['category_id' => $category->id, 'name' => 'Шнур тестовый', 'slug' => 'shnur', 'price' => 99]);
        foreach ([self::METER => $meter, self::CORD => $cord] as $key => $card) {
            OneCItem::query()->create(['ref_key' => $key, 'code' => 'УТ-'.substr($key, -4), 'description' => $card->name]);
            IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => $key.'#00000000-0000-0000-0000-000000000000',
                'name' => $card->name, 'product_id' => $card->id, 'match_status' => 'matched']);
        }

        // the first read is whole; newest first — the first record of an item is its price; no stock needed (the meter has none)
        $task = $this->read([
            'retail_1' => [['Period' => '2026-11-23T21:18:00', 'Номенклатура_Key' => self::METER, 'Цена' => 208.33],
                ['Period' => '2026-11-01T10:00:00', 'Номенклатура_Key' => self::METER, 'Цена' => 150]],
            'wholesale_1' => [['Period' => '2026-11-23T21:18:00', 'Номенклатура_Key' => self::METER, 'Цена' => 183.33],
                ['Period' => '2026-11-20T21:55:00', 'Номенклатура_Key' => self::CORD, 'Цена' => 69.33]],
        ], full: true);
        $this->assertStringContainsString('Цены 1С: розница 1, опт 2, цен на сайте изменено: 1', (string) $task->summary);
        $item = OneCItem::query()->where('ref_key', self::METER)->first();
        $this->assertSame([250.0, 220.0], [$item->price_retail, $item->price_wholesale]);
        $this->assertSame(250.0, OneCItemCosts::retail1c($item));

        // the card sells at the 1C retail price; a card without one keeps its own
        $this->assertSame(250.0, (float) $meter->fresh()->price);
        $this->assertSame(99.0, (float) $cord->fresh()->price);

        // a supplier price list or a manual edit does not overwrite it
        $meter->fresh()->forceFill(['price' => 180])->save();
        $this->assertSame(250.0, (float) $meter->fresh()->price);

        // a sale on now keeps its price; when it ends — back to the 1C retail price
        $meter->fresh()->forceFill(['price' => 200, 'price_old' => 250, 'is_sale' => true, 'sale_ends_at' => now()->addDay()])->save();
        $this->assertSame(200.0, (float) $meter->fresh()->price);
        $meter->fresh()->forceFill(['price' => 250, 'price_old' => null, 'is_sale' => false, 'sale_ends_at' => null])->save();

        // a new retail price in 1C — the next read (only what changed since the last one) moves the card
        $next = app(OneCPrices::class)->enqueue(null);
        $this->assertStringContainsString('Period ge datetime', urldecode((string) $next->payload['queries']['retail_1']));
        $next->forceFill(['status' => OneCAgentTask::CANCELLED])->save();
        $this->read(['retail_1' => [['Period' => '2026-11-24T12:00:00', 'Номенклатура_Key' => self::CORD, 'Цена' => 86.67]]]);
        $this->assertSame(104.0, (float) $cord->fresh()->price);
        $this->assertSame(250.0, (float) $meter->fresh()->price);
    }
}
