<?php

namespace Tests\Feature;

use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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

    public function test_price_list_url_identifiers_are_not_treated_as_supplier_phone_numbers(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'url-is-not-phone',
            'name' => 'Поставщик со ссылкой на прайс',
            'contact' => 'https://docs.google.com/spreadsheets/d/example/edit?gid=2045946143#gid=2045946143',
        ]);

        $this->assertSame([], $supplier->contactPhoneNumbers());
    }

    public function test_manager_can_quickly_find_supplier_by_brand_search_or_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'brand-search-supplier',
            'name' => 'Компания нужного бренда',
        ]);
        $otherSupplier = Supplier::query()->create([
            'code' => 'other-brand-supplier',
            'name' => 'Посторонняя компания',
        ]);
        $brand = Brand::query()->create([
            'name' => 'БыстроНаходимыйБренд',
            'slug' => 'quickly-found-brand',
        ]);
        $otherBrand = Brand::query()->create([
            'name' => 'ДругойБренд',
            'slug' => 'other-brand',
        ]);
        $supplier->brands()->attach($brand);
        $otherSupplier->brands()->attach($otherBrand);

        Livewire::actingAs($admin)
            ->test(ListSuppliers::class)
            ->searchTable('БыстроНаходимыйБренд')
            ->assertSeeText('Компания нужного бренда')
            ->assertDontSeeText('Посторонняя компания');

        Livewire::actingAs($admin)
            ->test(ListSuppliers::class)
            ->filterTable('brands', [$brand->id])
            ->assertSeeText('Компания нужного бренда')
            ->assertDontSeeText('Посторонняя компания');
    }

    public function test_stale_running_sync_is_not_presented_as_currently_running(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'stale-sync-supplier',
            'name' => 'Поставщик со старым запуском',
        ]);
        SupplierSync::query()->create([
            'supplier_id' => $supplier->id,
            'key' => 'stale-sync',
            'name' => 'Старый сценарий',
            'code' => 'stale-sync-supplier',
            'title' => 'Сценарий из июня',
            'command' => 'supplier:test',
            'last_status' => 'running',
            'last_run_at' => now()->subMonths(4),
        ]);

        $this->actingAs($admin)
            ->get(SupplierResource::getUrl('view', ['record' => $supplier], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Сценарий из июня')
            ->assertSeeText('Зависло')
            ->assertDontSeeText('Выполняется');
    }

    public function test_directory_data_migration_registers_all_documented_online_price_sources(): void
    {
        $suppliers = collect([
            ['code' => 'bania', 'name' => 'BANIA.by'],
            ['code' => 'ligmet', 'name' => 'Лигмет груп'],
            ['code' => 'termostudia', 'name' => 'Термостудия'],
            ['code' => 'maytek', 'name' => 'Майтек груп'],
        ])->mapWithKeys(function (array $attributes): array {
            $supplier = Supplier::query()->updateOrCreate(
                ['code' => $attributes['code']],
                ['name' => $attributes['name']],
            );

            return [$attributes['code'] => $supplier];
        });

        $migration = require database_path('migrations/2026_10_14_140000_seed_supplier_price_sources_and_close_stale_runs.php');
        $migration->up();

        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1eDUENigmEbQjge6G1WFqvJdjZ_YBM32A/edit?gid=700541031#gid=700541031',
            $suppliers['bania']->sources()->firstOrFail()->sourceUrl(),
        );
        $this->assertSame(
            'https://drive.google.com/drive/folders/1pQQRGMKBEHHEjUF3AYsi_dTvlxxTFxzz',
            $suppliers['ligmet']->sources()->firstOrFail()->sourceUrl(),
        );
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1cQEYeQIDjAyHG-_r-ZqL0v0zsSj9hZ5m9yDpyTBBqm0/edit?gid=1298422136#gid=1298422136',
            $suppliers['termostudia']->sources()->firstOrFail()->sourceUrl(),
        );
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1Cucw7U8f2Qs6U7kcWZvrdwZitXgP17j5W0TcujUFiN8/edit?pli=1&gid=2059074837#gid=2059074837',
            $suppliers['maytek']->sources()->firstOrFail()->sourceUrl(),
        );
    }

    public function test_price_list_brand_migration_populates_brand_filter_for_known_and_inferred_suppliers(): void
    {
        $bania = Supplier::query()->updateOrCreate(['code' => 'bania'], ['name' => 'BANIA.by']);
        $ligmet = Supplier::query()->updateOrCreate(['code' => 'ligmet'], ['name' => 'Лигмет груп']);
        $thermostudio = Supplier::query()->updateOrCreate(['code' => 'thermostudio'], ['name' => 'Термостудия']);
        $maitek = Supplier::query()->updateOrCreate(['code' => 'maitek-group'], ['name' => 'Майтек Групп']);
        $other = Supplier::query()->create(['code' => 'price-derived', 'name' => 'Поставщик из товаров']);

        $brands = collect([
            ['name' => 'Теплодар', 'slug' => 'directory-teplodar'],
            ['name' => 'Kratki', 'slug' => 'directory-kratki'],
            ['name' => 'Bosch', 'slug' => 'directory-bosch'],
            ['name' => 'СТЭН', 'slug' => 'directory-sten'],
            ['name' => 'Бренд другого прайса', 'slug' => 'other-price-brand'],
        ])->mapWithKeys(function (array $attributes): array {
            $brand = Brand::query()->firstOrCreate(
                ['name' => $attributes['name']],
                ['slug' => $attributes['slug']],
            );

            return [$attributes['name'] => $brand];
        });

        $category = Category::query()->create(['name' => 'Тестовая категория', 'slug' => 'supplier-brand-test']);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brands['Бренд другого прайса']->id,
            'supplier_id' => $other->id,
            'name' => 'Товар другого поставщика',
            'slug' => 'supplier-brand-derived-product',
        ]);

        $migration = require database_path('migrations/2026_10_14_150000_link_suppliers_to_price_list_brands.php');
        $migration->up();

        $this->assertTrue($bania->brands()->whereKey($brands['Теплодар']->id)->exists(), 'BANIA.by brand was not linked.');
        $this->assertTrue($ligmet->brands()->whereKey($brands['Kratki']->id)->exists(), 'Ligmet brand was not linked.');
        $this->assertTrue($thermostudio->brands()->whereKey($brands['Bosch']->id)->exists(), 'Thermostudio brand was not linked.');
        $this->assertTrue($maitek->brands()->whereKey($brands['СТЭН']->id)->exists(), 'Maitek brand was not linked.');
        $this->assertTrue($other->brands()->whereKey($brands['Бренд другого прайса']->id)->exists(), 'Inferred product brand was not linked.');
    }
}
