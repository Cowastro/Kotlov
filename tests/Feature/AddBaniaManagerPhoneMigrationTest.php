<?php

namespace Tests\Feature;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddBaniaManagerPhoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_bania_manager_phone_without_losing_existing_contacts_or_creating_duplicates(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'name' => 'БАНЬКА',
            'type' => 'organization',
            'classification' => 'supplier',
        ]);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'bania'], [
            'name' => 'BANIA.by',
            'crm_counterparty_id' => $counterparty->id,
            'contact' => 'https://bania.by',
            'communication_channels' => [[
                'type' => 'email',
                'name' => 'Общий отдел',
                'value' => 'info@bania.by',
                'note' => null,
            ]],
        ]);

        $migration = require database_path('migrations/2026_10_19_070000_add_bania_manager_phone.php');
        $migration->up();
        $migration->up();

        $supplier->refresh();
        $channels = collect($supplier->communication_channels);

        $this->assertStringStartsWith('+375 (44) 724-55-70', $supplier->contact);
        $this->assertTrue($channels->contains(fn (array $channel): bool => ($channel['value'] ?? null) === 'info@bania.by'));
        $this->assertCount(1, $channels->filter(fn (array $channel): bool => ($channel['value'] ?? null) === '+375447245570'));
        $this->assertSame('Менеджер Банька', $channels->firstWhere('value', '+375447245570')['name']);
        $this->assertSame('По наличию и заказам', $channels->firstWhere('value', '+375447245570')['note']);
        $this->assertSame(['+375447245570'], $supplier->contactPhoneNumbers());

        $point = CrmContactPoint::query()
            ->where('counterparty_id', $counterparty->id)
            ->where('normalized_value', '375447245570')
            ->sole();

        $this->assertSame('Менеджер Банька', $point->label);
        $this->assertTrue($point->is_primary);
        $this->assertTrue($point->is_active);
    }
}
