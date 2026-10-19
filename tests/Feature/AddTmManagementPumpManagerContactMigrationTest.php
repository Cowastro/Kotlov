<?php

namespace Tests\Feature;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddTmManagementPumpManagerContactMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_the_pump_manager_without_losing_existing_tm_contacts_or_creating_duplicates(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'name' => 'ТМ Менеджмент',
            'type' => 'organization',
            'classification' => 'supplier',
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'tm-management',
            'name' => 'ТМ Менеджмент',
            'crm_counterparty_id' => $counterparty->id,
            'contact' => '+375 17 290-82-78',
            'communication_channels' => [[
                'type' => 'email',
                'name' => 'Общий отдел',
                'value' => 'info@example.test',
                'note' => null,
            ]],
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'type' => 'phone',
            'value' => '+375 17 290-82-78',
            'normalized_value' => '375172908278',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_10_19_130000_add_tm_management_pump_manager_contact.php');
        $migration->up();
        $migration->up();

        $supplier->refresh();
        $channels = collect($supplier->communication_channels);

        $this->assertStringContainsString('+375 17 290-82-78', $supplier->contact);
        $this->assertStringContainsString('+375 (44) 592-09-20', $supplier->contact);
        $this->assertCount(1, $channels->where('value', '+375445920920'));
        $this->assertSame('Андрей Логацкий', $channels->firstWhere('value', '+375445920920')['name']);
        $this->assertSame('Специалист насосного отдела ТМ Менеджмент', $channels->firstWhere('value', '+375445920920')['note']);

        $point = CrmContactPoint::query()
            ->where('counterparty_id', $counterparty->id)
            ->where('normalized_value', '375445920920')
            ->sole();

        $this->assertSame('Андрей Логацкий — насосный отдел ТМ', $point->label);
        $this->assertFalse($point->is_primary);
        $this->assertTrue($point->is_active);
        $this->assertSame(['+375 17 290-82-78', '+375445920920'], $supplier->fresh()->contactPhoneNumbers());
    }
}
