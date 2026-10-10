<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Models\IntegrationSource;
use App\Models\User;
use App\Services\Integrations\IntegrationOrderStatusMapper;
use App\Services\Integrations\IntegrationOrderStatusRuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationSourceStatusMappingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_source_specific_order_and_payment_status_mapping_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'status-mapping-admin',
            'name' => 'Источник со статусами',
            'settings' => [
                'order_status_rules' => [
                    ['source' => 'Передан логисту', 'target' => 'shipped'],
                ],
                'payment_status_rules' => [
                    ['source' => 'Проведена кассой', 'target' => 'paid'],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(IntegrationSourceResource::getUrl('edit', ['record' => $source], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Сопоставление статусов заказов')
            ->assertSeeText('Статусы заказа')
            ->assertSeeText('Статусы оплаты');

        $this->assertSame(2, $source->statusRulesCount());
    }

    public function test_rule_manager_adds_and_replaces_source_rules_without_losing_other_settings(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'status-rule-manager',
            'name' => 'Источник правил',
            'settings' => [
                'partner_name' => 'Поставщик правил',
                'order_status_rules' => ['Собран' => 'processing'],
            ],
        ]);
        $manager = app(IntegrationOrderStatusRuleManager::class);

        $manager->store($source, 'Передан логисту', 'shipped', 'Проведена кассой', 'paid');
        $manager->store($source->fresh(), '  ПЕРЕДАН   ЛОГИСТУ ', 'delivered', null, null);

        $source->refresh();
        $this->assertSame('Поставщик правил', data_get($source->settings, 'partner_name'));
        $this->assertSame(3, $source->statusRulesCount());
        $this->assertSame(
            'delivered',
            app(IntegrationOrderStatusMapper::class)->orderStatus('передан логисту', $source),
        );
        $this->assertSame(
            'paid',
            app(IntegrationOrderStatusMapper::class)->paymentStatus('Проведена кассой', $source),
        );
    }
}
