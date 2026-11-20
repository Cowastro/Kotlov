<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCAgentTasks\Pages\ListOneCAgentTasks;
use App\Filament\Resources\OneCAgentTasks\Pages\ViewOneCAgentTask;
use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCPriceBatch;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCPriceBatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class OneCBridgeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Бак', 'slug' => 'bak', 'price' => 111, 'manual_purchase_price_byn' => 70]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'adcaedcf-5146-11ea-a246-1c1b0d1de7c2',
            'external_code' => 'УТ-00000001', 'name' => 'Бак-ватерпасс 304 d180', 'stock_quantity' => 2, 'price' => 0, 'product_id' => $card->id, 'match_status' => 'matched']);
        [, $this->key] = OneCAgentToken::issue('test', $this->admin->id);
    }

    private function approvedBatch(): OneCPriceBatch
    {
        $batches = app(OneCPriceBatches::class);
        $batch = $batches->buildFromPriceless($this->admin);
        $batches->approve($batch, $this->admin);

        return $batch->fresh();
    }

    private function sendPart(string $uri, string $body): TestResponse
    {
        return $this->call('POST', $uri, [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->key, 'HTTP_X_KOTLOV_AGENT' => '3',
            'CONTENT_TYPE' => 'application/octet-stream'], $body);
    }

    private function agent(): static
    {
        return $this->withToken($this->key)->withHeader('X-Kotlov-Agent', '2');
    }

    public function test_an_approved_batch_becomes_a_task_the_new_agent_takes_once_and_closes(): void
    {
        $this->agent()->get('/1c/agent/tasks/next')->assertOk()->assertSeeText('none');
        $batch = $this->approvedBatch();
        $task = OneCAgentTask::query()->sole();
        $this->assertSame(OneCAgentTask::QUEUED, $task->status);
        $this->assertSame('price_batch', $task->type);

        $lines = explode("\n", trim($this->agent()->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertSame('task|'.$task->id.'|price_batch', $lines[0]);
        $this->assertSame('batch|'.$batch->id, $lines[1]);
        $this->assertSame('end', end($lines));
        $this->assertCount(4, array_filter($lines, fn (string $l): bool => str_starts_with($l, 'row|')));
        $this->assertSame(OneCPriceBatch::SENDING, $batch->fresh()->status);
        $this->agent()->get('/1c/agent/tasks/next')->assertSeeText('none');
        $this->assertSame('2', OneCAgentToken::query()->sole()->agent_version);

        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1', 'document_number' => 'УТ-00000017',
            'document_key' => '29E03C60-E292-11F1-8C60-1C1B0D1DE7C2'])->assertOk()->assertJson(['task_status' => 'done', 'status' => 'created']);
        $task->refresh();
        $this->assertSame(OneCAgentTask::DONE, $task->status);
        $this->assertSame('Документ УТ-00000017, не проведён', $task->summary);
        $this->assertSame('УТ-00000017', $batch->fresh()->onec_document_number);
        // A repeated answer (the agent lost the first reply) does not change anything.
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertStatus(409);
    }

    public function test_a_failed_task_is_retried_from_the_bridge_or_cancelled_back_to_a_draft(): void
    {
        $batch = $this->approvedBatch();
        $task = OneCAgentTask::query()->sole();
        $this->agent()->get('/1c/agent/tasks/next')->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '0', 'error' => '1С ответила 500: нет прав'])->assertOk();
        $this->assertSame(OneCPriceBatch::FAILED, $batch->fresh()->status);
        $this->assertSame('1С ответила 500: нет прав', $task->fresh()->error);

        $tasks = app(OneCAgentTasks::class);
        $tasks->retry($task->fresh());
        $this->assertSame(OneCAgentTask::QUEUED, $task->fresh()->status);
        $this->assertSame(OneCPriceBatch::APPROVED, $batch->fresh()->status);

        $tasks->cancel($task->fresh());
        $this->assertSame(OneCAgentTask::CANCELLED, $task->fresh()->status);
        $this->assertSame(OneCPriceBatch::DRAFT, $batch->fresh()->status);
        $this->agent()->get('/1c/agent/tasks/next')->assertSeeText('none');

        // Sent again from the batch page: a new task, the old ones stay as history.
        app(OneCPriceBatches::class)->approve($batch->fresh(), $this->admin);
        $this->assertSame(2, OneCAgentTask::query()->count());
        $this->expectExceptionMessage('уже в очереди');
        $tasks->enqueue('price_batch', $batch, 'дубль', null);
    }

    public function test_a_task_taken_and_never_answered_comes_back_and_finally_fails(): void
    {
        $batch = $this->approvedBatch();
        $task = OneCAgentTask::query()->sole();
        $this->agent()->get('/1c/agent/tasks/next')->assertOk();
        $this->travel(31)->minutes();
        $this->agent()->get('/1c/agent/tasks/next')->assertOk()->assertSeeText('task|'.$task->id);
        $this->assertSame(2, $task->fresh()->attempts);

        $task->forceFill(['attempts' => OneCAgentTasks::MAX_ATTEMPTS])->save();
        $this->travel(31)->minutes();
        $this->agent()->get('/1c/agent/tasks/next')->assertSeeText('none');
        $this->assertSame(OneCAgentTask::FAILED, $task->fresh()->status);
        $this->assertSame(OneCPriceBatch::FAILED, $batch->fresh()->status);
    }

    public function test_prices_start_from_the_bridge_and_old_finished_batches_show_in_its_history(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ListOneCAgentTasks::class)->callAction('buildPriceless')->assertHasNoActionErrors();
        $batch = OneCPriceBatch::query()->sole();
        $this->assertFalse(OneCPriceBatchResource::shouldRegisterNavigation());

        $batch->forceFill(['status' => OneCPriceBatch::CREATED, 'onec_document_number' => 'УТ-00000017', 'finished_at' => now()])->save();
        (require database_path('migrations/2026_11_21_110000_backfill_onec_bridge_history.php'))->up();
        $task = OneCAgentTask::query()->sole();
        $this->assertSame(OneCAgentTask::DONE, $task->status);
        $this->assertSame('Документ УТ-00000017, не проведён', $task->summary);
        $this->assertSame($batch->id, $task->subject_id);
        (require database_path('migrations/2026_11_21_110000_backfill_onec_bridge_history.php'))->up();
        $this->assertSame(1, OneCAgentTask::query()->count());
    }

    public function test_the_agent_reads_1c_lists_for_orders_and_the_answers_show_in_the_task(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);
        Livewire::test(ListOneCAgentTasks::class)->callAction('readOrderReferences')->assertHasNoActionErrors();
        $task = OneCAgentTask::query()->sole();
        $this->assertNull($task->subject_id);

        $lines = explode("\n", trim($this->agent()->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertSame('task|'.$task->id.'|odata_read', $lines[0]);
        $this->assertContains('get|warehouses|Catalog_%D0%A1%D0%BA%D0%BB%D0%B0%D0%B4%D1%8B?$format=json&$select=Ref_Key%2CDescription%2CIsFolder%2CDeletionMark&$top=300', $lines);
        foreach (array_slice($lines, 1, -1) as $line) {
            $this->assertMatchesRegularExpression('/^get\|[a-z0-9_]+\|[A-Za-z0-9%_.~-]+\?[A-Za-z0-9%_.~$=&-]+$/', $line);
        }

        $body = json_encode(['value' => [['Ref_Key' => 'aaaaaaaa-0000-0000-0000-000000000001', 'Description' => 'Основной склад', 'IsFolder' => false]]], JSON_UNESCAPED_UNICODE);
        $this->sendPart('/1c/agent/tasks/'.$task->id.'/part?name=warehouses&code=200', $body)->assertOk();
        $this->sendPart('/1c/agent/tasks/'.$task->id.'/part?name=vat_rates&code=404', '{"odata.error":{"message":{"value":"Нет такого"}}}')->assertOk();
        $this->sendPart('/1c/agent/tasks/'.$task->id.'/part?name=passwords&code=200', '{}')->assertStatus(409);
        $this->agent()->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1', 'read' => '1'])->assertOk();
        $this->assertSame('Прочитано 1 из 8', $task->fresh()->summary);

        Livewire::test(ViewOneCAgentTask::class, ['record' => $task->getRouteKey()])
            ->assertOk()->assertSee('Основной склад')->assertSee('aaaaaaaa-0000-0000-0000-000000000001')->assertSee('Нет такого');
    }

    public function test_the_site_learns_from_1c_that_the_accountant_posted_the_price_document(): void
    {
        Storage::fake('local');
        $batch = $this->approvedBatch();
        $priceTask = OneCAgentTask::query()->sole();
        $this->agent()->get('/1c/agent/tasks/next')->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$priceTask->id.'/result', ['ok' => '1', 'document_number' => 'УТ-00000017',
            'document_key' => '29E03C60-E292-11F1-8C60-1C1B0D1DE7C2'])->assertOk();
        $this->assertSame('Документ УТ-00000017, не проведён', $priceTask->fresh()->summary);

        $this->artisan('onec:check-documents')->expectsOutputToContain('metric.onec_check_documents.queued=')->assertSuccessful();
        $check = OneCAgentTask::query()->where('type', 'doc_status')->sole();
        $lines = explode("\n", trim($this->agent()->get('/1c/agent/tasks/next')->assertOk()->getContent()));
        $this->assertSame('task|'.$check->id.'|doc_status', $lines[0]);
        $this->assertMatchesRegularExpression('/^get\|prices_1\|[A-Za-z0-9%_.~-]+\?[A-Za-z0-9%_.~$=&-]+$/', $lines[1]);
        $this->assertStringContainsString(rawurlencode("Ref_Key eq guid'29e03c60-e292-11f1-8c60-1c1b0d1de7c2'"), $lines[1]);

        $this->sendPart('/1c/agent/tasks/'.$check->id.'/part?name=prices_1&code=200', json_encode(['value' => [
            ['Ref_Key' => '29e03c60-e292-11f1-8c60-1c1b0d1de7c2', 'Number' => 'УТ-00000017', 'Posted' => true, 'DeletionMark' => false],
        ]]))->assertOk();
        $this->agent()->post('/1c/agent/tasks/'.$check->id.'/result', ['ok' => '1'])->assertOk();

        $this->assertTrue($batch->fresh()->onec_posted);
        $this->assertSame('Документ УТ-00000017, проведён', $priceTask->fresh()->summary);
        $this->assertSame('Проверено 1, проведено 1', $check->fresh()->summary);
        // Posted documents are not asked about again, and done checks do not clutter the bridge.
        $this->assertNull(app(DocumentStatusTaskHandler::class)->enqueueCheck(null));
        $this->actingAs($this->admin);
        Livewire::test(ListOneCAgentTasks::class)->assertCanSeeTableRecords([$priceTask])->assertCanNotSeeTableRecords([$check]);
    }

    public function test_the_first_agent_still_gets_price_batches_and_the_panel_asks_to_update_it(): void
    {
        $batch = $this->approvedBatch();
        $lines = explode("\n", trim($this->withToken($this->key)->get('/1c/agent/price-batches/next?format=lines')->assertOk()->getContent()));
        $this->assertSame('batch|'.$batch->id, $lines[0]);
        $this->withToken($this->key)->post('/1c/agent/price-batches/'.$batch->id.'/result', ['ok' => '1', 'document_number' => 'УТ-00000018'])
            ->assertOk()->assertJson(['status' => 'created']);
        $this->assertSame(OneCAgentTask::DONE, OneCAgentTask::query()->sole()->status);

        $health = app(OneCAgentTasks::class)->health();
        $this->assertTrue($health['online']);
        $this->assertSame('1', $health['version']);
        $this->assertTrue($health['outdated']);

        $this->actingAs($this->admin);
        Livewire::test(ListOneCAgentTasks::class)->assertOk()->assertSee('устарела')->assertSee('Цены: пакет №'.$batch->id);
    }
}
