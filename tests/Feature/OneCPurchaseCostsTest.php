<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentToken;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCPurchaseCosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OneCPurchaseCostsTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM = 'adcaedcf-5146-11ea-a246-1c1b0d1de7c2';

    private function doc(string $key, string $number, string $date, float $gross, float $vat, float $qty, string $currency = OneCPurchaseCosts::BYN, bool $posted = true): array
    {
        return ['Ref_Key' => $key, 'Number' => $number, 'Date' => $date, 'Posted' => $posted, 'DeletionMark' => false, 'Валюта_Key' => $currency,
            'Партнер_Key' => 'cccccccc-0000-0000-0000-000000000001', 'Организация_Key' => 'c5322188-0f48-11e9-bad6-1c1b0d1de7c2',
            'Товары' => [['LineNumber' => '1', 'Номенклатура_Key' => self::ITEM, 'Характеристика_Key' => '00000000-0000-0000-0000-000000000000',
                'Количество' => $qty, 'Цена' => round(($gross - $vat) / $qty, 2), 'Сумма' => $gross - $vat, 'СуммаНДС' => $vat, 'СуммаСНДС' => $gross]]];
    }

    public function test_the_card_gets_the_price_of_its_last_byn_purchase_in_1c(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Бак', 'slug' => 'bak', 'price' => 111]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => self::ITEM.'#00000000-0000-0000-0000-000000000000',
            'external_code' => 'УТ-00000001', 'name' => 'Бак', 'price' => 0, 'product_id' => $card->id, 'match_status' => 'matched']);
        [, $key] = OneCAgentToken::issue('test', $admin->id);
        $agent = fn () => $this->withHeaders(['Authorization' => 'Bearer '.$key, 'X-Kotlov-Agent' => '5']);

        $task = app(OneCPurchaseCosts::class)->enqueue($admin, 14);
        $this->assertSame(['purchases_1', 'purchases_2'], array_keys($task->payload['queries']));
        $agent()->get('/1c/agent/tasks/next')->assertOk();
        $body = json_encode(['value' => [
            $this->doc('aaaaaaaa-0000-0000-0000-000000000001', '00УТ-000100', '2026-11-10T10:00:00', 120, 20, 2),
            $this->doc('aaaaaaaa-0000-0000-0000-000000000002', '00УТ-000200', '2026-11-18T10:00:00', 72, 12, 1),
            $this->doc('aaaaaaaa-0000-0000-0000-000000000003', '00УТ-000300', '2026-11-20T10:00:00', 10, 0, 1, 'eeeeeeee-0000-0000-0000-000000000001'),
            $this->doc('aaaaaaaa-0000-0000-0000-000000000004', '00УТ-000400', '2026-11-21T10:00:00', 999, 0, 1, OneCPurchaseCosts::BYN, false),
        ]], JSON_UNESCAPED_UNICODE);
        foreach (['purchases_1' => $body, 'purchases_2' => '{"value":[]}'] as $name => $json) {
            $this->call('POST', '/1c/agent/tasks/'.$task->id.'/part?name='.$name.'&code=200', [], [], [],
                ['HTTP_AUTHORIZATION' => 'Bearer '.$key, 'HTTP_X_KOTLOV_AGENT' => '5', 'CONTENT_TYPE' => 'application/octet-stream'], $json)->assertOk();
        }
        $agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();

        $card->refresh();
        $this->assertSame('60.00', (string) $card->onec_cost_byn);
        $this->assertSame('72.00', (string) $card->onec_cost_vat_byn);
        $this->assertSame('00УТ-000200', $card->onec_cost_doc);
        $this->assertSame('Поступлений: 3, строк: 3, себестоимость обновлена у карточек: 1', $task->fresh()->summary);
    }
}
