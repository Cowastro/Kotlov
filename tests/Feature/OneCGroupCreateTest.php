<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCItems\Pages\ListOneCItems;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCItem;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OneCGroupCreator;
use App\Services\Integrations\OneCBridge\OneCGroupTree;
use App\Services\Integrations\OneCBridge\OneCReferences;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OneCGroupCreateTest extends TestCase
{
    use RefreshDatabase;

    private const PARENT = '53ae19fd-6761-11e9-9b7c-1c1b0d1de7c2';

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        OneCItem::query()->create(['ref_key' => self::PARENT, 'description' => 'Отопление', 'is_folder' => true, 'deletion_mark' => false]);
        [, $this->token] = OneCAgentToken::issue('test', $this->admin->id);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'X-Kotlov-Agent' => '8']);
    }

    private function requestGroup(string $name = 'Трубы', ?string $parent = self::PARENT): OneCAgentTask
    {
        return app(OneCGroupCreator::class)->request($name, $parent, $this->admin);
    }

    private function answer(OneCAgentTask $task, array $siblings = [], bool $hasParent = true, int $code = 200): void
    {
        $this->get('/1c/agent/tasks/next')->assertOk()->assertSee('task|'.$task->id.'|odata_read');
        foreach (array_keys($task->payload['queries']) as $part) {
            $rows = $part === 'siblings' ? $siblings : ($hasParent ? [['Ref_Key' => self::PARENT, 'IsFolder' => true]] : []);
            $this->call('POST', '/1c/agent/tasks/'.$task->id.'/part?name='.$part.'&code='.$code, [], [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'HTTP_X_KOTLOV_AGENT' => '8', 'CONTENT_TYPE' => 'application/octet-stream',
            ], json_encode(['value' => $rows], JSON_UNESCAPED_UNICODE))->assertOk();
        }
        $this->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();
    }

    public function test_group_creation_round_trip_uses_existing_agent_and_updates_tree_without_products(): void
    {
        $read = $this->requestGroup();
        $this->answer($read);
        $create = OneCAgentTask::query()->where('type', 'onec_item')->sole();
        $this->assertTrue($create->payload['body']['IsFolder']);
        $this->assertSame(self::PARENT, $create->payload['body']['Parent_Key']);
        $this->assertSame($read->payload['group_operation'], $create->payload['body']['Ref_Key']);
        $this->assertArrayNotHasKey('Артикул', $create->payload['body']);
        $next = $this->get('/1c/agent/tasks/next')->assertOk()->assertSee('task|'.$create->id.'|onec_item');
        $this->assertStringContainsString('IsFolder eq true', rawurldecode($next->getContent()));
        $key = $create->payload['body']['Ref_Key'];
        $this->post('/1c/agent/tasks/'.$create->id.'/result', ['ok' => '1', 'document_key' => $key, 'document_code' => 'УТ-009999'])->assertOk();
        $folder = OneCItem::query()->where('ref_key', $key)->sole();
        $this->assertTrue($folder->is_folder);
        $this->assertTrue($folder->created_from_site);
        $this->assertNull($folder->product_id);
        $this->assertSame('Отопление', $folder->path);
        $this->assertSame('Трубы', app(OneCGroupTree::class)->nodes()[0]['children'][0]['name']);
        // A repeated agent result cannot create another record.
        $this->post('/1c/agent/tasks/'.$create->id.'/result', ['ok' => '1', 'document_key' => $key])->assertStatus(409);
        $this->assertSame(2, OneCItem::query()->count());
    }

    public function test_cached_duplicates_ignore_case_and_repeated_spaces_within_same_parent(): void
    {
        OneCItem::query()->create(['ref_key' => fake()->uuid(), 'description' => '  ТРУБЫ  ', 'parent_key' => self::PARENT, 'is_folder' => true, 'deletion_mark' => false]);
        $this->expectExceptionMessage('уже существует');
        $this->requestGroup('трубы');
    }

    public function test_duplicate_queued_requests_are_rejected_and_other_parents_are_allowed(): void
    {
        $task = $this->requestGroup();
        $other = $this->requestGroup('Трубы', null);
        $this->assertNotSame($task->payload['group_operation'], $other->payload['group_operation']);
        $this->expectExceptionMessage('уже создаётся');
        $this->requestGroup(' ТРУБЫ ');
    }

    public function test_remote_existing_group_is_imported_instead_of_created_even_with_old_local_copy(): void
    {
        $key = fake()->uuid();
        $read = $this->requestGroup();
        $this->answer($read, [['Ref_Key' => $key, 'Code' => '123', 'Description' => 'ТРУБЫ', 'Parent_Key' => self::PARENT]]);
        $this->assertSame(0, OneCAgentTask::query()->where('type', 'onec_item')->count());
        $this->assertTrue(OneCItem::query()->where('ref_key', $key)->sole()->is_folder);
        $this->assertStringContainsString('уже есть', $read->fresh()->summary);
    }

    public function test_remote_failure_blocks_creation_and_records_error_instead_of_stuck_task(): void
    {
        $read = $this->requestGroup();
        $this->answer($read, code: 500);
        $this->assertSame(OneCAgentTask::FAILED, $read->fresh()->status);
        $this->assertStringContainsString('Не удалось проверить', $read->fresh()->error);
        $this->assertSame(0, OneCAgentTask::query()->where('type', 'onec_item')->count());
    }

    public function test_deleted_parent_in_live_1c_blocks_creation(): void
    {
        $read = $this->requestGroup();
        $this->answer($read, hasParent: false);
        $this->assertSame(OneCAgentTask::FAILED, $read->fresh()->status);
        $this->assertStringContainsString('Родительская группа', $read->fresh()->error);
    }

    public function test_same_name_item_is_not_a_duplicate_group_and_root_uses_zero_key(): void
    {
        OneCItem::query()->create(['ref_key' => fake()->uuid(), 'description' => 'Трубы', 'is_folder' => false, 'deletion_mark' => false]);
        $read = $this->requestGroup(parent: null);
        $this->answer($read);
        $this->assertSame(OneCReferences::ZERO, OneCAgentTask::query()->where('type', 'onec_item')->sole()->payload['body']['Parent_Key']);
    }

    public function test_invalid_and_inactive_parents_are_rejected(): void
    {
        OneCItem::query()->where('ref_key', self::PARENT)->update(['deletion_mark' => true]);
        $this->expectExceptionMessage('родительскую группу');
        $this->requestGroup();
    }

    public function test_name_limit_does_not_silently_truncate_to_create_wrong_group(): void
    {
        $this->expectExceptionMessage('100 символов');
        $this->requestGroup(str_repeat('я', 101));
    }

    public function test_customers_cannot_enqueue_groups(): void
    {
        $this->admin->role = 'customer';
        $this->expectException(DomainException::class);
        $this->requestGroup();
    }

    public function test_lost_response_retry_keeps_same_1c_key_and_existing_agent_result_is_not_marked_new(): void
    {
        $read = $this->requestGroup();
        $this->answer($read);
        $create = OneCAgentTask::query()->where('type', 'onec_item')->sole();
        $this->get('/1c/agent/tasks/next')->assertOk();
        $this->post('/1c/agent/tasks/'.$create->id.'/result', ['ok' => '0', 'error' => 'Connection lost'])->assertOk();
        app(OneCAgentTasks::class)->retry($create->fresh());
        $key = $create->payload['body']['Ref_Key'];
        $this->get('/1c/agent/tasks/next')->assertSee(base64_encode(json_encode($create->payload['body'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)));
        $this->post('/1c/agent/tasks/'.$create->id.'/result', ['ok' => '1', 'document_key' => $key, 'existed' => '1'])->assertOk();
        $this->assertFalse(OneCItem::query()->where('ref_key', $key)->sole()->created_from_site);
    }

    public function test_tree_preserves_ancestors_for_search_and_excludes_deleted_groups(): void
    {
        OneCItem::query()->create(['ref_key' => fake()->uuid(), 'description' => 'Фитинги', 'parent_key' => self::PARENT, 'is_folder' => true, 'deletion_mark' => false]);
        OneCItem::query()->create(['ref_key' => fake()->uuid(), 'description' => 'Старые фитинги', 'is_folder' => true, 'deletion_mark' => true]);
        $nodes = app(OneCGroupTree::class)->nodes('фитинг');
        $this->assertCount(1, $nodes);
        $this->assertSame('Отопление', $nodes[0]['name']);
        $this->assertSame('Фитинги', $nodes[0]['children'][0]['name']);
    }

    public function test_admin_can_select_parent_in_tree_and_submit_group_action(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ListOneCItems::class)
            ->assertSuccessful()->assertSee('Дерево групп 1С')->assertSee('Отопление')
            ->call('selectGroup', self::PARENT)
            ->assertSet('tableFilters.group.value', self::PARENT)
            ->callAction('createGroup', data: ['name' => 'Трубы', 'parent' => self::PARENT])
            ->assertHasNoActionErrors();
        $this->assertSame('Трубы', OneCAgentTask::query()->sole()->payload['create_group']['name']);
    }

    public function test_missing_part_does_not_create_and_long_name_quotes_are_escaped_in_live_search(): void
    {
        $task = $this->requestGroup("Трубы 'плюс'");
        $this->get('/1c/agent/tasks/next')->assertOk();
        $this->post('/1c/agent/tasks/'.$task->id.'/result', ['ok' => '1'])->assertOk();
        $this->assertSame(OneCAgentTask::FAILED, $task->fresh()->status);
        $this->assertSame(0, OneCAgentTask::query()->where('type', 'onec_item')->count());
    }

    public function test_truncated_remote_group_list_blocks_creation(): void
    {
        $task = $this->requestGroup();
        $this->answer($task, array_fill(0, 2001, ['Ref_Key' => fake()->uuid(), 'Code' => 'x', 'Description' => 'Другое', 'Parent_Key' => self::PARENT]));
        $this->assertSame(OneCAgentTask::FAILED, $task->fresh()->status);
        $this->assertSame(0, OneCAgentTask::query()->where('type', 'onec_item')->count());
    }
}
