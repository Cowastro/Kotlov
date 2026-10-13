<?php

namespace Tests\Feature;

use App\Filament\Resources\SupplierSyncs\SupplierSyncResource;
use App\Models\SupplierSync;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierSyncLegacyResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_supplier_sync_registry_is_hidden_and_read_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->assertFalse(SupplierSyncResource::shouldRegisterNavigation());
        $this->assertTrue(SupplierSyncResource::canViewAny());
        $this->assertFalse(SupplierSyncResource::canCreate());
        $this->assertFalse(SupplierSyncResource::canEdit(new SupplierSync));
        $this->assertFalse(SupplierSyncResource::canDelete(new SupplierSync));

        $this->get(SupplierSyncResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSee('Технический реестр импортов')
            ->assertSee('Журнал синхронизаций')
            ->assertDontSee('Обновить все активные')
            ->assertDontSee('Очистить логи')
            ->assertDontSee('Добавить поставщика');
    }

    public function test_manager_cannot_open_legacy_supplier_sync_registry(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(SupplierSyncResource::getUrl('index', panel: 'admin'))
            ->assertForbidden();
    }
}
