<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmCounterparties\Pages\ListCrmCounterparties;
use App\Models\CrmContact;
use App\Models\CrmCounterparty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmCounterpartyDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_move_a_spam_lead_to_trash_and_restore_it(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $spam = $this->lead('Спам из почты');
        $contact = CrmContact::query()->create([
            'counterparty_id' => $spam->id,
            'full_name' => 'Спам',
            'is_primary' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(ListCrmCounterparties::class)
            ->callTableAction('deleteSpam', $spam)
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('crm_counterparties', ['id' => $spam->id]);
        $this->assertDatabaseHas('crm_contacts', [
            'id' => $contact->id,
            'counterparty_id' => $spam->id,
        ]);
        $trashedSpam = CrmCounterparty::withTrashed()->findOrFail($spam->id);

        Livewire::actingAs($manager)
            ->test(ListCrmCounterparties::class)
            ->callTableAction('restore', $trashedSpam)
            ->assertHasNoTableActionErrors();

        $this->assertNotSoftDeleted('crm_counterparties', ['id' => $spam->id]);
    }

    public function test_bulk_spam_action_skips_active_counterparties(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $firstSpam = $this->lead('Спам 1');
        $secondSpam = $this->lead('Спам 2');
        $supplier = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'supplier',
            'business_unit' => 'wholesale',
            'status' => 'active',
            'name' => 'Действующий поставщик',
            'source' => 'manual',
        ]);

        Livewire::actingAs($manager)
            ->test(ListCrmCounterparties::class)
            ->callTableBulkAction('deleteSpam', [$firstSpam, $secondSpam, $supplier])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSoftDeleted('crm_counterparties', ['id' => $firstSpam->id]);
        $this->assertSoftDeleted('crm_counterparties', ['id' => $secondSpam->id]);
        $this->assertNotSoftDeleted('crm_counterparties', ['id' => $supplier->id]);
    }

    private function lead(string $name): CrmCounterparty
    {
        return CrmCounterparty::query()->create([
            'type' => 'person',
            'classification' => 'unclassified',
            'business_unit' => 'retail',
            'status' => 'lead',
            'name' => $name,
            'source' => 'email',
        ]);
    }
}
