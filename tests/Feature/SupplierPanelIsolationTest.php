<?php

namespace Tests\Feature;

use App\Filament\Supplier\Resources\SupplierProducts\SupplierProductResource;
use App\Models\Supplier;
use App\Models\SupplierProduct;
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
}
