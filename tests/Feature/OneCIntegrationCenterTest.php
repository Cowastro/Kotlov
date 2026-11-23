<?php

namespace Tests\Feature;

use App\Filament\Pages\OneCIntegration;
use App\Filament\Pages\OneCSetup;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCItem;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCIntegrationOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OneCIntegrationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function source(): IntegrationSource
    {
        return IntegrationSource::updateOrCreate(['code' => 'onec'], ['name' => '1С СанБизнесГруп', 'driver' => 'commerceml', 'is_active' => true,
            'username' => 'secret-user-never-display', 'password_hash' => 'secret-hash-never-display']);
    }

    public function test_file_failure_and_live_agent_remain_independent_without_leaking_credentials(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = $this->source();
        IntegrationExchangeRun::create(['integration_source_id' => $source->id, 'direction' => 'inbound', 'operation' => 'catalog', 'status' => 'failed', 'started_at' => now()]);
        OneCAgentToken::create(['name' => 'agent', 'token_hash' => hash('sha256', 'never-expose-agent-key'), 'last_seen_at' => now()]);
        $snapshot = app(OneCIntegrationOverview::class)->snapshot();
        $this->assertSame('failed', $snapshot['files'][0]['status']);
        $this->assertTrue($snapshot['agent']['online']);
        $this->actingAs($user)->get('/admin/onec-integration')->assertOk()->assertSeeText('Ошибка загрузки')->assertSeeText('Агент на связи')
            ->assertSeeText('Заказы 1С')->assertSeeText('Номенклатура и привязки')->assertSeeText('Технические настройки для администратора')
            ->assertDontSee('secret-user-never-display')->assertDontSee('secret-hash-never-display')->assertDontSee('never-expose-agent-key');
    }

    public function test_manager_can_open_center_but_not_technical_setup_and_client_cannot_open_center(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager)->get('/admin/onec-integration')->assertOk()->assertDontSeeText('Технические настройки для администратора')->assertDontSee('href="https://localhost/admin/one-c-setup"', false);
        $this->get('/admin/one-c-setup')->assertForbidden();
        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->actingAs($client)->get('/admin/onec-integration')->assertForbidden();
    }

    public function test_old_address_keeps_working_with_clear_scope_and_new_center_link(): void
    {
        $this->source();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get('/admin/one-c-setup')->assertOk()->assertSeeText('Файловый обмен 1С')
            ->assertSeeText('Эта страница проверяет только файловый обмен')->assertSee('/admin/onec-integration');
        $this->assertFalse(OneCSetup::shouldRegisterNavigation());
        $this->assertSame('Интеграция с 1С', OneCIntegration::getNavigationLabel());
    }

    public function test_overview_counts_characteristic_links_and_limits_document_tasks_without_mutations(): void
    {
        OneCItem::create(['ref_key' => 'a0000000-0000-0000-0000-000000000001', 'description' => 'Товар']);
        DB::table('onec_items')->update(['characteristic_count' => 3, 'site_link_count' => 3, 'link_conflict' => true, 'stock_on_hand' => 15]);
        for ($i = 0; $i < 8; $i++) {
            OneCAgentTask::create(['type' => DocumentStatusTaskHandler::TYPE, 'title' => 'Документ '.$i, 'status' => 'done', 'finished_at' => now()]);
        }
        OneCAgentTask::create(['type' => 'odata_read', 'title' => 'Чтение каталога', 'status' => 'queued']);
        $before = OneCAgentTask::count();
        $data = app(OneCIntegrationOverview::class)->snapshot();
        $this->assertSame(1, $data['items']['total']);
        $this->assertSame(1, $data['items']['linked']);
        $this->assertSame(1, $data['items']['conflicts']);
        $this->assertCount(6, $data['tasks']);
        $this->assertSame('Документ 7', $data['tasks'][0]['title']);
        $this->assertSame($before, OneCAgentTask::count());
        $this->assertEquals(15, OneCItem::first()->stock_on_hand);
    }

    public function test_successful_file_upload_does_not_claim_that_offline_agent_is_working(): void
    {
        $source = $this->source();
        IntegrationExchangeRun::create(['integration_source_id' => $source->id, 'direction' => 'inbound', 'operation' => 'catalog', 'status' => 'success', 'started_at' => now(), 'finished_at' => now()]);
        $data = app(OneCIntegrationOverview::class)->snapshot();
        $this->assertSame('healthy', $data['files'][0]['status']);
        $this->assertFalse($data['agent']['online']);
        $this->assertArrayNotHasKey('ready', $data);
    }
}
