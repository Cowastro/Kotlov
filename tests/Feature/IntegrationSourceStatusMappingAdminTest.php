<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Models\IntegrationSource;
use App\Models\User;
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
}
