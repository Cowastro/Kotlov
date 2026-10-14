<?php

namespace Tests\Feature;

use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierDirectoryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_card_combines_brands_contacts_prices_and_sync_channels(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'directory-supplier',
            'name' => 'Поставщик из справочника',
            'communication_channels' => [[
                'type' => 'viber',
                'name' => 'Общий чат отдела закупок',
                'value' => '+375 29 100-20-30',
                'note' => 'По наличию и срокам',
            ]],
        ]);
        $brand = Brand::query()->create([
            'name' => 'Бренд справочника',
            'slug' => 'directory-brand',
        ]);
        $supplier->brands()->attach($brand);

        SupplierSource::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Онлайн-прайс и остатки',
            'type' => 'google_sheet',
            'config' => ['url' => 'https://docs.google.com/spreadsheets/d/example'],
            'schedule_cron' => 'Ежедневно в 09:00',
            'is_active' => true,
            'last_run_at' => now(),
        ]);
        SupplierSync::query()->create([
            'supplier_id' => $supplier->id,
            'key' => 'directory-supplier-sync',
            'name' => 'Поставщик из справочника',
            'code' => 'directory-supplier',
            'title' => 'Обновление прайса поставщика',
            'command' => 'supplier:sync-directory-supplier',
            'source_url' => 'https://docs.google.com/spreadsheets/d/example',
            'is_active' => true,
            'last_status' => 'updated',
            'last_run_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(SupplierResource::getUrl('view', ['record' => $supplier], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Поставщик из справочника')
            ->assertSeeText('Бренд справочника')
            ->assertSeeText('Общий чат отдела закупок')
            ->assertSeeText('Онлайн-прайс и остатки')
            ->assertSeeText('Ежедневно в 09:00')
            ->assertSeeText('Обновление прайса поставщика');

        Livewire::actingAs($admin)
            ->test(ListSuppliers::class)
            ->searchTable('Поставщик из справочника')
            ->assertSeeText('Бренд справочника')
            ->assertSeeText('Онлайн-прайс и остатки')
            ->assertSeeText('Google Таблица · Ежедневно в 09:00 · автообновление включено')
            ->assertSee('href="https://docs.google.com/spreadsheets/d/example"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_brand_knows_which_suppliers_sell_it(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'brand-link-supplier',
            'name' => 'Компания бренда',
        ]);
        $brand = Brand::query()->create([
            'name' => 'Связанный бренд',
            'slug' => 'linked-directory-brand',
        ]);

        $brand->suppliers()->attach($supplier);

        $this->assertTrue($supplier->brands()->whereKey($brand->id)->exists());
        $this->assertTrue($brand->suppliers()->whereKey($supplier->id)->exists());
    }
}
