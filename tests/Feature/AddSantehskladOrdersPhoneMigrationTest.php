<?php

namespace Tests\Feature;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddSantehskladOrdersPhoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_orders_phone_without_losing_existing_supplier_contacts(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'name' => 'Сантехсклад',
            'type' => 'organization',
            'classification' => 'supplier',
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'santehsklad',
            'name' => 'Сантехсклад',
            'crm_counterparty_id' => $counterparty->id,
            'contact' => 'https://santehsklad.by',
            'communication_channels' => [[
                'type' => 'email',
                'name' => 'Общий отдел',
                'value' => 'info@example.by',
                'note' => null,
            ]],
        ]);

        $migration = require database_path('migrations/2026_10_19_140000_add_santehsklad_orders_phone.php');
        $migration->up();
        $migration->up();

        $supplier->refresh();
        $channels = collect($supplier->communication_channels);

        $this->assertStringContainsString('+375 (29) 515-53-77', $supplier->contact);
        $this->assertTrue($channels->contains(fn (array $channel): bool => ($channel['value'] ?? null) === 'info@example.by'));
        $this->assertCount(1, $channels->filter(fn (array $channel): bool => ($channel['value'] ?? null) === '+375295155377'));
        $this->assertSame('Приём заявок', $channels->firstWhere('value', '+375295155377')['name']);
        $this->assertSame(['+375295155377'], $supplier->contactPhoneNumbers());

        $point = CrmContactPoint::query()
            ->where('counterparty_id', $counterparty->id)
            ->where('normalized_value', '375295155377')
            ->sole();

        $this->assertSame('Сантехсклад — приём заявок', $point->label);
        $this->assertTrue($point->is_primary);
    }
}
