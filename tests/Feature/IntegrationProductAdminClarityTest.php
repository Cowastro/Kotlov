<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationCategories\IntegrationCategoryResource;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Filament\Resources\IntegrationProducts\Pages\EditIntegrationProduct;
use App\Filament\Resources\IntegrationProducts\Pages\ListIntegrationProducts;
use App\Filament\Widgets\IntegrationCatalogIntegrityOverview;
use App\Models\Category;
use App\Models\IntegrationCategory;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationProductAdminClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_exclude_one_offer_from_b2b_without_changing_source_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'offer-publication-control',
            'name' => 'Источник с выборочной публикацией',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'offer-publication-item',
            'name' => 'Позиция для выборочной публикации',
            'stock_quantity' => 3,
        ]);

        $this->actingAs($admin)
            ->get(IntegrationProductResource::getUrl('edit', ['record' => $item], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Публикация в B2B')
            ->assertSeeText('По общему правилу источника и категории')
            ->assertSeeText('Не публиковать эту позицию');

        Livewire::actingAs($admin)
            ->test(EditIntegrationProduct::class, ['record' => $item->getRouteKey()])
            ->fillForm(['b2b_enabled' => '0'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($item->fresh()->b2b_enabled);

        $this->actingAs($admin)
            ->get(IntegrationProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Исключён');
    }

    public function test_individual_category_moves_the_linked_site_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $oldCategory = Category::query()->create([
            'name' => 'Старая категория карточки',
            'slug' => 'old-linked-product-category',
            'parent_id' => 0,
        ]);
        $newCategory = Category::query()->create([
            'name' => 'Крепления и монтаж',
            'slug' => 'new-linked-product-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $oldCategory->id,
            'name' => 'Привязанная карточка для переноса',
            'slug' => 'linked-product-category-move',
            'sku' => 'LINKED-CATEGORY-1',
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'linked-category-source',
            'name' => 'Источник переноса категории',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'linked-category-external',
            'name' => 'Позиция 1С для переноса',
            'stock_quantity' => 1,
            'match_status' => 'matched',
        ]);

        Livewire::actingAs($admin)
            ->test(EditIntegrationProduct::class, ['record' => $item->getRouteKey()])
            ->assertSeeText('Категория карточки сайта')
            ->fillForm(['target_category_id' => $newCategory->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($newCategory->id, $item->fresh()->target_category_id);
        $this->assertSame($newCategory->id, $product->fresh()->category_id);
        $this->assertSame('Крепления и монтаж', $item->fresh()->resolvedSiteCategory()?->name);
    }

    public function test_admin_page_explains_full_staging_catalog_and_lists_only_positive_stock(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'clarity-test-source',
            'name' => 'СанБизнесГруп',
        ]);

        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'available-product',
            'name' => 'Товар с положительным остатком',
            'stock_quantity' => 1,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unavailable-product',
            'name' => 'Товар без остатка',
            'stock_quantity' => 0,
        ]);

        $this->actingAs($admin)
            ->get(IntegrationProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Ниже показаны только товары с положительным остатком')
            ->assertSeeText('Повторный обмен обновляет их по ID 1С и не создаёт копии')
            ->assertSeeText('В наличии')
            ->assertSeeText('Товар с положительным остатком')
            ->assertSeeText('1 шт.')
            ->assertDontSeeText('Товар без остатка');

        Livewire::actingAs($admin)
            ->test(IntegrationCatalogIntegrityOverview::class)
            ->assertSee('Вся номенклатура 1С')
            ->assertSee('Контроль дублей')
            ->assertSee('1 в наличии')
            ->assertSee('1 без остатка скрыто');
    }

    public function test_ambiguous_match_shows_a_direct_candidate_link_and_review_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'candidate-ui-source',
            'name' => 'Источник с вариантами',
        ]);
        $category = Category::query()->create([
            'name' => 'Категория кандидатов',
            'slug' => 'candidate-ui-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'sku' => 'CANDIDATE-UI',
            'name' => 'Карточка-кандидат D150',
            'slug' => 'candidate-ui-card',
            'category_id' => $category->id,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'candidate-ui-item',
            'name' => 'Внешний товар с вариантами D150',
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.82,
            'candidates' => [[
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'score' => 0.82,
            ]],
        ]);

        $this->actingAs($admin)
            ->get(IntegrationProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Карточка-кандидат D150')
            ->assertSeeText('кандидат · CANDIDATE-UI')
            ->assertSeeText('Сравнить варианты');
    }

    public function test_integrity_overview_explains_what_the_latest_catalog_sync_created_and_updated(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'latest-import-source',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'СанБизнесГруп'],
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_received' => 705,
            'items_created' => 5,
            'items_updated' => 700,
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationCatalogIntegrityOverview::class)
            ->assertSee('Последний импорт')
            ->assertSee('СанБизнесГруп')
            ->assertSee('получено 705')
            ->assertSee('новых 5')
            ->assertSee('обновлено 700');
    }

    public function test_admin_can_open_a_safe_list_of_possible_identity_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'duplicate-review-source',
            'name' => 'Источник для проверки дублей',
        ]);

        $first = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'duplicate-review-a',
            'external_sku' => 'DUPLICATE-500',
            'name' => 'Первая позиция',
            'stock_quantity' => 2,
        ]);
        $second = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'duplicate-review-b',
            'external_sku' => 'duplicate 500',
            'name' => 'Вторая позиция',
            'stock_quantity' => 1,
        ]);
        $unrelated = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unrelated-review-item',
            'external_sku' => 'UNIQUE-900',
            'name' => 'Отдельная позиция',
            'stock_quantity' => 3,
        ]);

        $this->actingAs($admin)
            ->get(IntegrationProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Возможные дубли');

        Livewire::actingAs($admin)
            ->test(ListIntegrationProducts::class)
            ->set('activeTab', 'identity_collisions')
            ->assertCanSeeTableRecords([$first, $second])
            ->assertCanNotSeeTableRecords([$unrelated]);

        Livewire::actingAs($admin)
            ->test(IntegrationCatalogIntegrityOverview::class)
            ->assertSee('возможных дублей по реквизитам: 1')
            ->assertSee('Все позиции помечены 1С как доступные')
            ->assertSee('диапазон 1–3 шт.')
            ->assertSee('проверьте склад выгрузки')
            ->assertSeeHtml('tab=identity_collisions');
    }

    public function test_bulk_acceptance_rechecks_each_suggestion_and_skips_stale_candidates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'bulk-suggestion-source',
            'name' => 'Источник рекомендаций',
        ]);
        $category = Category::query()->create([
            'name' => 'Карточки для рекомендаций',
            'slug' => 'bulk-suggestion-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Действующая карточка',
            'slug' => 'bulk-suggestion-product',
            'sku' => 'BULK-VALID',
        ]);
        $valid = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bulk-valid',
            'external_sku' => 'SUP-BULK-VALID',
            'name' => 'Товар с действующей рекомендацией',
            'stock_quantity' => 2,
            'match_status' => 'suggested',
            'match_method' => 'exact_name',
            'match_confidence' => 0.95,
            'candidates' => [['product_id' => $product->id, 'score' => 0.95]],
        ]);
        $stale = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bulk-stale',
            'external_sku' => 'SUP-BULK-STALE',
            'name' => 'Товар с устаревшей рекомендацией',
            'stock_quantity' => 1,
            'match_status' => 'suggested',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.9,
            'candidates' => [['product_id' => 999999, 'score' => 0.9]],
        ]);

        Livewire::actingAs($admin)
            ->test(ListIntegrationProducts::class)
            ->callTableBulkAction('acceptSuggestions', [$valid, $stale])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('integration_products', [
            'id' => $valid->id,
            'product_id' => $product->id,
            'match_status' => 'matched',
            'match_method' => 'manual_suggestion',
        ]);
        $this->assertDatabaseHas('integration_products', [
            'id' => $stale->id,
            'product_id' => null,
            'match_status' => 'suggested',
        ]);
    }

    public function test_group_screen_explains_supplier_channel_and_links_back_to_filtered_workbench(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'group-ui-supplier',
            'name' => 'Поставщик групп',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'group-ui-source',
            'name' => 'Учётная база поставщика',
            'driver' => 'commerceml',
            'settings' => ['partner_name' => 'Поставщик групп'],
        ]);
        $group = IntegrationCategory::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'group-ui-external',
            'name' => 'Дымоходы',
            'path' => 'Каталог / Дымоходы',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'integration_category_id' => $group->id,
            'external_id' => 'group-ui-product',
            'name' => 'Товар исходной группы',
            'stock_quantity' => 3,
        ]);

        $this->actingAs($admin)
            ->get(IntegrationCategoryResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Поставщик групп')
            ->assertSeeText('1С / CommerceML · Учётная база поставщика')
            ->assertSeeText('Каталог / Дымоходы')
            ->assertSeeText('Структуру сайта не меняет')
            ->assertSeeText('Товары группы');
    }

    public function test_bulk_group_rule_updates_only_groups_of_one_selected_source_without_moving_site_cards(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'bulk-group-source',
            'name' => 'Источник массовых правил',
            'driver' => 'commerceml',
        ]);
        $siteCategory = Category::query()->create([
            'name' => 'Категория назначения правил',
            'slug' => 'bulk-group-rule-target',
            'parent_id' => 0,
        ]);
        $existingCategory = Category::query()->create([
            'name' => 'Категория существующей карточки',
            'slug' => 'bulk-group-existing-card',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $existingCategory->id,
            'name' => 'Существующая карточка не перемещается',
            'slug' => 'bulk-group-existing-product',
            'sku' => 'GROUP-EXISTING',
        ]);
        $firstGroup = IntegrationCategory::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bulk-group-1',
            'name' => 'Первая группа',
            'path' => 'Каталог / Первая группа',
        ]);
        $secondGroup = IntegrationCategory::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bulk-group-2',
            'name' => 'Вторая группа',
            'path' => 'Каталог / Вторая группа',
        ]);
        $first = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'integration_category_id' => $firstGroup->id,
            'product_id' => $product->id,
            'external_id' => 'bulk-group-product-1',
            'name' => 'Привязанный товар',
            'stock_quantity' => 2,
            'match_status' => 'matched',
        ]);
        $second = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'integration_category_id' => $secondGroup->id,
            'external_id' => 'bulk-group-product-2',
            'name' => 'Непривязанный товар',
            'stock_quantity' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(ListIntegrationProducts::class)
            ->callTableBulkAction('assignSourceGroupCategory', [$first, $second], [
                'category_id' => $siteCategory->id,
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame($siteCategory->id, $firstGroup->fresh()->category_id);
        $this->assertSame($siteCategory->id, $secondGroup->fresh()->category_id);
        $this->assertSame($existingCategory->id, $product->fresh()->category_id);
        $this->assertSame($product->id, $first->fresh()->product_id);
        $this->assertNull($second->fresh()->product_id);
    }

    public function test_bulk_group_rule_rejects_mixed_sources_without_partial_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $siteCategory = Category::query()->create([
            'name' => 'Запрещённое смешанное назначение',
            'slug' => 'mixed-group-rule-target',
            'parent_id' => 0,
        ]);
        $records = collect(['A', 'B'])->map(function (string $suffix): IntegrationProduct {
            $source = IntegrationSource::query()->create([
                'code' => 'mixed-group-source-'.strtolower($suffix),
                'name' => 'Источник '.$suffix,
                'driver' => 'commerceml',
            ]);
            $group = IntegrationCategory::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => 'mixed-group-'.$suffix,
                'name' => 'Группа '.$suffix,
                'path' => 'Каталог / Группа '.$suffix,
            ]);

            return IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'integration_category_id' => $group->id,
                'external_id' => 'mixed-group-product-'.$suffix,
                'name' => 'Товар '.$suffix,
                'stock_quantity' => 1,
            ]);
        });

        Livewire::actingAs($admin)
            ->test(ListIntegrationProducts::class)
            ->callTableBulkAction('assignSourceGroupCategory', $records, [
                'category_id' => $siteCategory->id,
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(0, IntegrationCategory::query()->whereNotNull('category_id')->count());
    }

    public function test_admin_can_create_and_immediately_link_a_new_site_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'create-card-supplier',
            'name' => 'Поставщик новой карточки',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'create-card-source',
            'name' => '1С поставщика новой карточки',
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Новая категория привязки',
            'slug' => 'new-linked-card-category',
            'parent_id' => 0,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'create-card-external-id',
            'external_sku' => 'TS.NEW.001',
            'name' => 'Новая позиция из 1С',
            'price' => 100,
            'price_byn' => 120,
            'stock_quantity' => 7,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => 999999, 'score' => 0.5]],
        ]);

        Livewire::actingAs($admin)
            ->test(ListIntegrationProducts::class)
            ->callTableAction('createProduct', $item, [
                'name' => 'Созданная карточка сайта',
                'category_id' => $category->id,
                'retail_price' => 159.90,
            ])
            ->assertHasNoTableActionErrors();

        $item->refresh();
        $product = Product::query()->findOrFail($item->product_id);

        $this->assertSame('Созданная карточка сайта', $product->name);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($supplier->id, $product->supplier_id);
        $this->assertSame('159.90', $product->price);
        $this->assertMatchesRegularExpression('/^KOTLOV-\d{6}$/', $product->sku);
        $this->assertSame(7, $product->stock_qty);
        $this->assertTrue($product->in_stock);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->availability_status);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.NEW.001',
            'product_id' => $product->id,
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_create_product_action_is_hidden_after_the_item_is_linked(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Категория уже привязанной карточки',
            'slug' => 'already-linked-card-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Уже созданная карточка',
            'slug' => 'already-created-card',
            'sku' => 'KOTLOV-ALREADY',
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'already-linked-source',
            'name' => 'Источник привязанной карточки',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'already-linked-external',
            'name' => 'Уже привязанная позиция',
            'stock_quantity' => 1,
            'match_status' => 'matched',
        ]);

        Livewire::actingAs($admin)
            ->test(ListIntegrationProducts::class)
            ->assertTableActionHidden('createProduct', $item);
    }
}
