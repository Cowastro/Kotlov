<?php

namespace Tests\Feature;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrectMercurySupplierPhoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_corrects_supplier_and_linked_crm_phone_without_changing_other_contacts(): void
    {
        $counterparty = CrmCounterparty::query()->create(['name' => 'Меркурий', 'type' => 'organization']);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'mercury'], [
            'name' => 'Меркурий', 'crm_counterparty_id' => $counterparty->id,
            'contact' => '+375 (29) 640-09-59 · info@pkmercury.by',
            'communication_channels' => [['type' => 'phone', 'value' => '+375296400959'], ['type' => 'email', 'value' => 'info@pkmercury.by']],
        ]);
        CrmContactPoint::query()->create(['counterparty_id' => $counterparty->id, 'type' => 'phone', 'value' => '+375296400959', 'is_primary' => true, 'is_active' => true]);
        $migration = require database_path('migrations/2026_10_16_110000_correct_mercury_supplier_phone.php');
        $migration->up();
        $migration->up();
        $this->assertSame('+375 (29) 787-38-92 · info@pkmercury.by', $supplier->fresh()->contact);
        $this->assertSame('+375297873892', $supplier->fresh()->communication_channels[0]['value']);
        $this->assertSame('info@pkmercury.by', $supplier->fresh()->communication_channels[1]['value']);
        $this->assertSame(['+375297873892'], $supplier->fresh()->contactPhoneNumbers());
        $this->assertSame(1, CrmContactPoint::query()->where('counterparty_id', $counterparty->id)->count());
    }
}
