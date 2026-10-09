<?php

namespace Tests\Feature;

use App\Filament\Supplier\Resources\SupplierProducts\SupplierProductResource;
use App\Filament\Supplier\Resources\SupplierSyncChanges\SupplierSyncChangeResource;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncChange;
use App\Models\SupplierSyncRun;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPanelIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_assigned_supplier_user_can_access_supplier_panel(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'supplier-a',
            'name' => 'Поставщик А',
        ]);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $panel = Panel::make()->id('supplier');

        $this->assertFalse($user->canAccessPanel($panel));

        $user->suppliers()->attach($supplier);
        $this->assertTrue($user->canAccessPanel($panel));

        $supplier->update(['is_active' => false]);
        $this->assertFalse($user->canAccessPanel($panel));
    }

    public function test_supplier_product_resource_is_strictly_scoped_to_assigned_suppliers(): void
    {
        $supplierA = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $supplierB = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplierA);

        $visible = SupplierProduct::query()->create([
            'supplier_id' => $supplierA->id,
            'supplier_article' => 'A-1',
            'supplier_name' => 'Доступный товар',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplierB->id,
            'supplier_article' => 'B-1',
            'supplier_name' => 'Чужой товар',
        ]);

        $this->actingAs($user);

        $this->assertSame([$visible->id], SupplierProductResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_supplier_panel_routes_are_registered(): void
    {
        $this->get('/supplier/login')->assertOk();
    }

    public function test_supplier_product_page_shows_actionable_tabs_and_only_own_products(): void
    {
        $supplier = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $otherSupplier = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplier);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_article' => 'A-ACTION',
            'supplier_name' => 'Моя позиция без цены',
            'price_byn' => 0,
            'stock_quantity' => 2,
            'last_synced_at' => now(),
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $otherSupplier->id,
            'supplier_article' => 'B-HIDDEN',
            'supplier_name' => 'Чужая позиция без цены',
            'price_byn' => 0,
            'stock_quantity' => 2,
            'last_synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(SupplierProductResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSee('Требуют внимания')
            ->assertSee('Не привязаны')
            ->assertSee('Устарели')
            ->assertSee('Моя позиция без цены')
            ->assertSee('Без цены')
            ->assertDontSee('Чужая позиция без цены');
    }

    public function test_supplier_change_history_is_strictly_scoped_to_assigned_suppliers(): void
    {
        $supplierA = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $supplierB = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplierA);
        $run = SupplierSyncRun::query()->create([
            'command' => 'supplier:sync-example',
            'status' => 'success',
            'started_at' => now(),
        ]);

        $visible = SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $supplierA->id,
            'supplier_name' => $supplierA->name,
            'supplier_article' => 'A-1',
            'product_name' => 'Доступное изменение',
            'change_flags' => ['supplier_price'],
            'supplier_price_before' => 10,
            'supplier_price_after' => 12,
            'created_at' => now(),
        ]);
        SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $supplierB->id,
            'supplier_name' => $supplierB->name,
            'supplier_article' => 'B-1',
            'product_name' => 'Чужое изменение',
            'change_flags' => ['stock_quantity'],
            'stock_quantity_before' => 2,
            'stock_quantity_after' => 9,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $this->assertSame([$visible->id], SupplierSyncChangeResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_supplier_can_open_its_change_history_page(): void
    {
        $supplier = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $otherSupplier = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplier);
        $run = SupplierSyncRun::query()->create([
            'command' => 'supplier:sync-example',
            'status' => 'success',
            'started_at' => now(),
        ]);
        SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_article' => 'A-1',
            'product_name' => 'Мой изменённый товар',
            'change_flags' => ['supplier_price', 'stock_quantity'],
            'supplier_price_before' => 10,
            'supplier_price_after' => 12,
            'stock_quantity_before' => 2,
            'stock_quantity_after' => 3,
            'created_at' => now(),
        ]);
        SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $otherSupplier->id,
            'supplier_name' => $otherSupplier->name,
            'supplier_article' => 'B-1',
            'product_name' => 'Чужой изменённый товар',
            'change_flags' => ['supplier_price'],
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(SupplierSyncChangeResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSee('История изменений')
            ->assertSee('Мой изменённый товар')
            ->assertDontSee('Чужой изменённый товар');
    }
}
