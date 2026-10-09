<?php

namespace Tests\Feature;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use App\Models\User;
use App\Services\Integrations\OneCSetupReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCSetupReadinessTest extends TestCase
{
    use RefreshDatabase;

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
}
