<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\User;
use App\Services\Integrations\OneCSetupReadiness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCSetupReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_readiness_reports_each_exchange_direction_without_exposing_credentials(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-ready',
            'name' => 'Тестовая 1С',
            'driver' => 'commerceml',
            'username' => 'exchange',
            'password_hash' => 'secret-hash',
            'is_active' => true,
            'last_authenticated_at' => now(),
            'settings' => ['allow_order_export' => true],
        ]);

        foreach ([
            ['inbound', 'catalog'],
            ['outbound', 'orders'],
            ['inbound', 'order_statuses'],
        ] as [$direction, $operation]) {
            IntegrationExchangeRun::query()->create([
                'integration_source_id' => $source->id,
                'direction' => $direction,
                'operation' => $operation,
                'status' => 'success',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
            ]);
        }

        $snapshot = app(OneCSetupReadiness::class)->snapshot($source);

        $this->assertTrue($snapshot['ready']);
        $this->assertSame(7, $snapshot['completed']);
        $this->assertSame('catalog', $snapshot['latest_catalog']->operation);
        $this->assertSame('orders', $snapshot['latest_orders']->operation);
        $this->assertSame('order_statuses', $snapshot['latest_statuses']->operation);
        $this->assertArrayNotHasKey('password_hash', $snapshot);
    }

    public function test_historical_successes_do_not_mark_stale_exchange_flows_as_ready(): void
    {
        CarbonImmutable::setTestNow('2026-10-10 12:00:00');

        $source = IntegrationSource::query()->create([
            'code' => 'onec-stale',
            'name' => 'Просроченная 1С',
            'driver' => 'commerceml',
            'username' => 'exchange',
            'password_hash' => 'secret-hash',
            'is_active' => true,
            'last_authenticated_at' => now()->subHour(),
            'settings' => [
                'order_interval_minutes' => 5,
                'catalog_interval_minutes' => 10,
                'stale_after_minutes' => 15,
                'allow_order_export' => true,
            ],
        ]);

        foreach ([
            ['inbound', 'catalog'],
            ['outbound', 'orders'],
            ['inbound', 'order_statuses'],
        ] as [$direction, $operation]) {
            IntegrationExchangeRun::query()->create([
                'integration_source_id' => $source->id,
                'direction' => $direction,
                'operation' => $operation,
                'status' => 'success',
                'started_at' => now()->subHours(2),
                'finished_at' => now()->subHours(2),
            ]);
        }

        $snapshot = app(OneCSetupReadiness::class)->snapshot($source);
        $flowChecks = collect($snapshot['checks'])->slice(4)->values();

        $this->assertFalse($snapshot['ready']);
        $this->assertSame(4, $snapshot['completed']);
        $this->assertSame('stale', $snapshot['flow_health']);
        $this->assertFalse($snapshot['catalog_fresh']);
        $this->assertSame(['warning', 'warning', 'warning'], $flowChecks->pluck('status')->all());
        $this->assertTrue($flowChecks->every(
            fn (array $check): bool => $check['next_step'] !== 'Готово'
                && str_contains($check['next_step'], 'устарел')
        ));
    }

    public function test_latest_failed_attempt_overrides_an_earlier_success_in_setup_readiness(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-failed',
            'name' => '1С с ошибкой',
            'driver' => 'commerceml',
            'username' => 'exchange',
            'password_hash' => 'secret-hash',
            'is_active' => true,
            'last_authenticated_at' => now(),
            'settings' => ['allow_order_export' => true],
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => 'connection reset',
        ]);

        $snapshot = app(OneCSetupReadiness::class)->snapshot($source);
        $catalogCheck = $snapshot['checks'][4];

        $this->assertFalse($snapshot['ready']);
        $this->assertSame('failed', $snapshot['flow_health']);
        $this->assertSame('failed', $catalogCheck['status']);
        $this->assertSame('×', $catalogCheck['icon']);
        $this->assertStringContainsString('ошибкой', $catalogCheck['next_step']);
    }

    public function test_setup_distinguishes_existing_staging_data_from_a_monitored_exchange_run(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-historical',
            'name' => 'Исторический импорт',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'historical-1',
            'name' => 'Ранее полученный товар',
            'last_seen_at' => now()->subDay(),
        ]);

        $snapshot = app(OneCSetupReadiness::class)->snapshot($source);

        $this->assertSame(1, $snapshot['staged_products_count']);
        $this->assertNotNull($snapshot['latest_staged_at']);
        $this->assertNull($snapshot['latest_run']);
        $this->assertFalse($snapshot['ready']);
    }

    public function test_admin_can_open_one_c_setup_page_and_see_next_steps(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        IntegrationSource::query()->updateOrCreate(['code' => 'onec'], [
            'name' => 'СанБизнесГруп',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/one-c-setup')
            ->assertOk()
            ->assertSee('Настройка автоматического обмена 1С')
            ->assertSee(url('/1c/exchange/onec'))
            ->assertSee('Каталог, цены и остатки поступают');
    }

    public function test_setup_page_explains_historical_staging_without_claiming_a_successful_cycle(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], [
            'name' => 'СанБизнесГруп',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'old-import',
            'last_seen_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/one-c-setup')
            ->assertOk()
            ->assertSeeText('Позиций в промежуточном каталоге: 1')
            ->assertSeeText('Данные были получены до включения журнала или вне текущего узла')
            ->assertDontSeeText('Обмен готов');
    }

    public function test_source_list_uses_the_same_flow_health_as_the_operations_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'source-list-health',
            'name' => 'Источник для списка',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)
            ->get(IntegrationSourceResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Источник для списка')
            ->assertSeeText('Работает')
            ->assertSeeText('Отдельно проверяются каталог, заказы и статусы');
    }
}
