<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\OneCItems\OneCItemResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\AdminProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class AdminProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private Product $door;

    private Product $wrong;

    private OneCItem $item;

    private IntegrationSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::query()->firstOrFail();
        $this->door = Product::query()->create(['category_id' => $category->id, 'slug' => 'search-test-door', 'sku' => 'PS-003.396',
            'name' => 'Дверь DoorWood Теплый день 170х70', 'price' => 296, 'is_active' => true, 'is_archived' => false]);
        $this->wrong = Product::query()->create(['category_id' => $category->id, 'slug' => 'search-test-fitting', 'sku' => 'BEREG-00009721',
            'name' => 'Пресс-муфта Giacomini', 'price' => 20, 'is_active' => true, 'is_archived' => false]);
        $this->item = OneCItem::query()->create(['ref_key' => fake()->uuid(), 'code' => 'УТ-00009721', 'description' => $this->door->name,
            'article' => 'DW01124', 'product_id' => $this->door->id, 'is_folder' => false, 'deletion_mark' => false]);
        $this->source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1С', 'is_active' => true]);
        IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => $this->item->ref_key,
            'external_code' => $this->item->code, 'external_sku' => 'DW01124', 'name' => $this->door->name, 'product_id' => $this->door->id, 'match_status' => 'matched']);
    }

    private function ids(string $search): array
    {
        return app(AdminProductSearch::class)->apply(Product::query()->where('is_archived', false), $search)->orderBy('products.id')->pluck('products.id')->all();
    }

    public function test_exact_onec_code_never_matches_a_supplier_sku_with_the_same_number(): void
    {
        foreach (['УТ-00009721', 'ут-00009721', 'UT-00009721', 'УТ00009721', 'УТ — 00009721', 'УТ-9721', '00УТ-00009721'] as $code) {
            $this->assertSame([$this->door->id], $this->ids($code), $code);
        }
    }

    public function test_unknown_onec_code_does_not_fall_back_to_a_numeric_suffix(): void
    {
        $this->wrong->update(['sku' => 'BEREG-00009999']);
        $this->assertSame([], $this->ids('УТ-00009999'));
    }

    public function test_unlinked_onec_item_does_not_invent_a_card_link(): void
    {
        $this->item->update(['product_id' => null]);
        IntegrationProduct::query()->where('external_id', $this->item->ref_key)->update(['product_id' => null, 'match_status' => 'unmatched']);
        $this->assertSame([], $this->ids('УТ-00009721'));
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Livewire::actingAs($admin)->test(ListProducts::class)->searchTable('УТ-00009721')
            ->assertSee('Карточка не привязана')->assertCanNotSeeTableRecords([$this->door, $this->wrong])
            ->assertSee('Открыть в номенклатуре 1С')
            ->assertSee('href="'.OneCItemResource::getUrl('index', ['search' => 'УТ-00009721']).'"', false)
            ->searchTable('УТ-99999999')->assertDontSee('Карточка не привязана')->assertDontSee('Открыть в номенклатуре 1С');
    }

    public function test_cached_direct_link_and_link_without_cached_item_both_work(): void
    {
        IntegrationProduct::query()->where('external_id', $this->item->ref_key)->delete();
        $this->assertSame([$this->door->id], $this->ids('УТ-00009721'));
        $this->item->delete();
        IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => fake()->uuid(),
            'external_code' => 'УТ-00009721', 'name' => 'Дверь', 'product_id' => $this->door->id, 'match_status' => 'matched']);
        $this->assertSame([$this->door->id], $this->ids('УТ-00009721'));
    }

    public function test_all_characteristics_are_found_but_ignored_links_are_excluded(): void
    {
        $this->item->update(['product_id' => null]);
        IntegrationProduct::query()->where('external_id', $this->item->ref_key)->update(['product_id' => null, 'match_status' => 'unmatched']);
        foreach (['a', 'b'] as $characteristic) {
            IntegrationProduct::query()->create(['integration_source_id' => $this->source->id, 'external_id' => $this->item->ref_key.'#'.$characteristic,
                'name' => 'Исполнение', 'product_id' => $characteristic === 'a' ? $this->door->id : $this->wrong->id, 'match_status' => $characteristic === 'a' ? 'matched' : 'ignored']);
        }
        $this->assertSame([$this->door->id], $this->ids('УТ-00009721'));
    }

    public function test_factory_article_from_onec_and_supplier_catalogue_code_work(): void
    {
        $this->assertSame([$this->door->id], $this->ids('dw01124'));
        $supplier = Supplier::query()->create(['code' => 'search-supplier', 'name' => 'Поставщик', 'is_active' => true]);
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $this->door->id,
            'supplier_article' => 'DW01124', 'supplier_article_normalized' => 'DW01124', 'supplier_catalog_code' => 'ЦБ-0139321690', 'match_status' => 'matched']);
        $this->assertSame([$this->door->id], $this->ids('цб-0139321690'));
    }

    public function test_internal_sku_has_priority_over_other_supplier_identifiers_and_retired_sku_resolves(): void
    {
        $supplier = Supplier::query()->create(['code' => 'search-supplier', 'name' => 'Поставщик', 'is_active' => true]);
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $this->wrong->id, 'supplier_article' => $this->door->sku]);
        $this->assertSame([$this->door->id], $this->ids($this->door->sku));
        $alias = Product::query()->create(['category_id' => $this->door->category_id, 'name' => 'Старый артикул', 'slug' => 'old-search-test',
            'sku' => 'OLD-9721', 'is_archived' => true]);
        $alias->forceFill(['merged_into_product_id' => $this->door->id])->save();
        $this->assertSame([$this->door->id], $this->ids($alias->sku));
    }

    public function test_filters_remain_in_force_for_exact_identifier_search(): void
    {
        $this->door->update(['is_archived' => true]);
        $this->assertSame([], $this->ids('УТ-00009721'));
        $this->assertFalse(app(AdminProductSearch::class)->apply(Product::query()->whereKey($this->wrong->id), 'УТ-00009721')->exists());
    }

    public function test_names_and_supplier_names_keep_existing_search(): void
    {
        $this->assertSame([$this->door->id], $this->ids('Дверь DoorWood'));
        $this->assertSame([$this->wrong->id], $this->ids('Giacomini'));
    }

    public function test_filament_search_uses_the_whole_code_and_shows_the_correct_onec_badge(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Livewire::actingAs($admin)->test(ListProducts::class)->searchTable('УТ-00009721')
            ->assertCanSeeTableRecords([$this->door])->assertCanNotSeeTableRecords([$this->wrong])->assertSee('УТ-00009721');
    }

    public function test_onec_column_reuses_eager_loaded_links_and_items_without_per_cell_queries(): void
    {
        // PHP statics survive between tests; a real table request resets both caches in configure().
        foreach (['onecLinks', 'onecItems'] as $property) {
            (new \ReflectionProperty(ProductsTable::class, $property))->setValue(null, []);
        }
        $record = ProductResource::getEloquentQuery()->findOrFail($this->door->id);
        $link = new ReflectionMethod(ProductsTable::class, 'onecLink');
        $item = new ReflectionMethod(ProductsTable::class, 'onecItem');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($this->source->id, $link->invoke(null, $record)->integration_source_id);
        $this->assertSame($this->item->ref_key, $item->invoke(null, $record)->ref_key);
        $this->assertSame($this->item->ref_key, $item->invoke(null, $record)->ref_key);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
