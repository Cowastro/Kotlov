<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCPriceBatches\Pages\ListOneCPriceBatches;
use App\Filament\Resources\OneCPriceBatches\Pages\ViewOneCPriceBatch;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentToken;
use App\Models\OneCPriceBatch;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCPriceBatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneCPriceBatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Бак', 'slug' => 'bak', 'price' => 111, 'manual_purchase_price_byn' => 70]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'ADCAEDCF-5146-11EA-A246-1C1B0D1DE7C2',
            'external_code' => 'УТ-00000001', 'name' => 'Бак-ватерпасс 304 d180', 'stock_quantity' => 2, 'price' => 0, 'product_id' => $card->id, 'match_status' => 'matched']);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'adcaedcf-0000-11ea-a246-1c1b0d1de7c2',
            'external_code' => 'УТ-00000002', 'name' => 'Без карточки', 'stock_quantity' => 1, 'price' => 0, 'match_status' => 'unmatched']);
    }

    public function test_a_batch_is_built_approved_handed_to_the_agent_once_and_closed_with_the_1c_document(): void
    {
        $service = app(OneCPriceBatches::class);
        $batch = $service->buildFromPriceless($this->admin);
        $this->assertSame(1, $batch->items()->count());
        $item = $batch->items()->first();
        $this->assertSame('adcaedcf-5146-11ea-a246-1c1b0d1de7c2', $item->item_key);
        $this->assertEquals(111, (float) $item->retail);
        $this->assertEquals(88.8, (float) $item->wholesale);

        [, $key] = OneCAgentToken::issue('test', $this->admin->id);
        $this->getJson('/1c/agent/price-batches/next')->assertUnauthorized();
        $this->withToken($key)->getJson('/1c/agent/price-batches/next')->assertOk()->assertJson(['batch' => null]);

        $service->approve($batch, $this->admin);
        $payload = $this->withToken($key)->getJson('/1c/agent/price-batches/next')->assertOk()->json('batch');
        $this->assertSame('Цены с сайта KOTLOV, пакет №'.$batch->id, $payload['comment']);
        $this->assertCount(4, $payload['rows']);
        $byType = collect($payload['rows'])->keyBy('price_type_key');
        $this->assertEquals(92.5, $byType['7b0fbdc2-1ffa-11e9-bad6-1c1b0d1de7c2']['price']);
        $this->assertTrue($byType['7b0fbdc2-1ffa-11e9-bad6-1c1b0d1de7c2']['manual']);
        $this->assertEquals(74.0, $byType['de262f38-66a1-11e9-9b7c-1c1b0d1de7c2']['price']);
        $this->assertEquals(88.8, $byType['a56e299c-d38a-11e9-a218-1c1b0d1de7c2']['price']);
        $this->assertSame('[ОптоваяЦена]*1.2', $byType['a56e299c-d38a-11e9-a218-1c1b0d1de7c2']['formula']);
        $this->assertEquals(111.0, $byType['a56e299d-d38a-11e9-a218-1c1b0d1de7c2']['price']);
        $this->assertSame(OneCPriceBatch::SENDING, $batch->fresh()->status);
        $this->withToken($key)->getJson('/1c/agent/price-batches/next')->assertJson(['batch' => null]);

        $this->withToken($key)->postJson('/1c/agent/price-batches/'.$batch->id.'/result', [
            'ok' => true, 'document_number' => 'УТ-00000017', 'document_key' => '29E03C60-E292-11F1-8C60-1C1B0D1DE7C2',
        ])->assertOk()->assertJson(['status' => OneCPriceBatch::CREATED]);
        $batch->refresh();
        $this->assertSame('УТ-00000017', $batch->onec_document_number);
        $this->assertSame('29e03c60-e292-11f1-8c60-1c1b0d1de7c2', $batch->onec_document_key);
        $this->withToken($key)->postJson('/1c/agent/price-batches/'.$batch->id.'/result', ['ok' => true])->assertStatus(409);
    }

    public function test_a_wholesale_above_retail_blocks_the_batch_and_a_revoked_key_is_refused(): void
    {
        $service = app(OneCPriceBatches::class);
        $batch = $service->buildFromPriceless($this->admin);
        $batch->items()->update(['wholesale' => 200]);

        $this->expectExceptionMessage('опт выше розницы');
        try {
            $service->approve($batch, $this->admin);
        } finally {
            [$token, $key] = OneCAgentToken::issue('old', $this->admin->id);
            $token->forceFill(['revoked_at' => now()])->save();
            $this->withToken($key)->getJson('/1c/agent/ping')->assertUnauthorized();
            $this->assertSame(OneCPriceBatch::DRAFT, $batch->fresh()->status);
        }
    }

    public function test_managers_build_and_send_a_batch_in_the_admin(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ListOneCPriceBatches::class)->callAction('buildPriceless')->assertHasNoActionErrors();
        $batch = OneCPriceBatch::query()->latest('id')->firstOrFail();

        Livewire::test(ViewOneCPriceBatch::class, ['record' => $batch->getRouteKey()])->assertOk()
            ->callAction('approve')->assertHasNoActionErrors();
        $this->assertSame(OneCPriceBatch::APPROVED, $batch->fresh()->status);

        Livewire::test(ListOneCPriceBatches::class)->callAction('agentKey')->assertHasNoActionErrors();
        $this->assertSame(1, OneCAgentToken::query()->whereNull('revoked_at')->count());
    }
}
