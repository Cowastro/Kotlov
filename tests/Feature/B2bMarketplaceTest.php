<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ContactRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_b2b_catalog_without_preview_query_parameter(): void
    {
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'admin-b2b-category',
            'is_active' => true,
        ]);
        $product = $this->product($category, 'Товар для проверки администратора', 'ADMIN-B2B-1');
        $source = $this->source('admin-b2b-source', $category);
        $this->offer($source, $product, 80, 5);
        $admin = User::factory()->create([
            'role' => 'admin',
            'client_type' => 'retail',
            'b2b_approved' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('b2b.catalog'))
            ->assertOk()
            ->assertSeeText('Оптовый отдел')
            ->assertSeeText('+375 (29) 354-40-41')
            ->assertSee('tel:+375293544041', false)
            ->assertSeeText('Товар для проверки администратора');
    }

    public function test_crm_manager_can_only_open_read_only_b2b_preview(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест CRM',
            'slug' => 'crm-b2b-preview-category',
            'is_active' => true,
        ]);
        $product = $this->product($category, 'Товар для CRM-превью', 'CRM-PREVIEW-1');
        $source = $this->source('crm-preview-source', $category);
        $this->offer($source, $product, 80, 5);
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_crm_user' => true,
            'client_type' => 'retail',
            'b2b_approved' => false,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('b2b.catalog'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('b2b.catalog', ['b2b-preview' => 1]))
            ->assertOk()
            ->assertSeeText('Тестовый просмотр для CRM')
            ->assertSeeText('Товар для CRM-превью')
            ->assertSeeText('80,00 BYN')
            ->assertSeeText('Тестовый просмотр')
            ->assertDontSee('data-qty-input="#b2b-qty-'.$product->id.'"', false);

        $this->actingAs($manager)
            ->getJson(route('b2b.catalog.suggest', ['b2b-preview' => 1, 'q' => 'CRM-PREVIEW-1']))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Товар для CRM-превью');
    }

    public function test_non_crm_manager_cannot_open_b2b_preview(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_crm_user' => false,
            'client_type' => 'retail',
            'b2b_approved' => false,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('b2b.catalog', ['b2b-preview' => 1]))
            ->assertForbidden();
    }

    public function test_partner_only_sees_prices_from_sources_granted_to_their_company(): void
    {
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'b2b-marketplace-category',
            'is_active' => true,
        ]);
        $allowedProduct = $this->product($category, 'Труба со склада', 'ALLOWED-1');
        $hiddenProduct = $this->product($category, 'Чужой прайс', 'HIDDEN-1');
        $allowedSource = $this->source('allowed-source', $category);
        $hiddenSource = $this->source('hidden-source', $category);
        $this->offer($allowedSource, $allowedProduct, 80, 5);
        $this->offer($hiddenSource, $hiddenProduct, 70, 8);

        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'is_active' => true,
        ]);
        B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Тест»',
            'allowed_integration_source_ids' => [$allowedSource->id],
        ]);

        $this->actingAs($user)
            ->get(route('b2b.catalog'))
            ->assertOk()
            ->assertSeeText('Быстрый заказ товаров')
            ->assertSeeText('Труба со склада')
            ->assertDontSeeText('Специальные условия онлайн')
            ->assertDontSeeText('Чужой прайс');

        $this->actingAs($user)
            ->getJson(route('b2b.catalog.suggest', ['q' => 'Труба']))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Труба со склада')
            ->assertJsonMissing(['name' => 'Чужой прайс']);

        $this->actingAs($user)
            ->postJson(route('cart.add'), ['product_id' => $hiddenProduct->id, 'quantity' => 1, 'pricing_mode' => 'b2b'])
            ->assertUnprocessable();
    }

    public function test_products_without_a_granted_offer_are_reference_only(): void
    {
        $category = Category::query()->create([
            'name' => 'Котлы',
            'slug' => 'b2b-reference-category',
            'is_active' => true,
        ]);
        $reference = $this->product($category, 'Справочный котел', 'REF-1');
        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'is_active' => true,
        ]);
        B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Тест»',
            'allowed_integration_source_ids' => [],
        ]);

        $this->actingAs($user)
            ->get(route('b2b.catalog', ['mode' => 'reference', 'q' => 'REF-1']))
            ->assertOk()
            ->assertSeeText('Справочный котел')
            ->assertSeeText('Запросить оптовую цену')
            ->assertDontSee('data-qty-input="#b2b-qty-'.$reference->id.'"', false);
    }

    public function test_partner_can_request_a_missing_wholesale_price_without_retyping_company_details(): void
    {
        $category = Category::query()->create([
            'name' => 'Котлы',
            'slug' => 'b2b-price-request-category',
            'is_active' => true,
        ]);
        $product = $this->product($category, 'Котёл без оптовой цены', 'REQUEST-1');
        $user = User::factory()->create([
            'name' => 'Иван Петров',
            'phone' => '+375291112233',
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'company_name' => 'ООО «Монтаж»',
            'company_inn' => '123456789',
            'is_active' => true,
        ]);
        B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Монтаж»',
            'unp' => '123456789',
            'allowed_integration_source_ids' => [],
        ]);

        $notifier = $this->mock(ContactRequestNotifier::class);
        $notifier->shouldReceive('notify')->once();

        $this->actingAs($user)
            ->postJson(route('b2b.price-requests.store'), [
                'product_id' => $product->id,
                'quantity' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'new');

        $this->assertDatabaseHas('contact_requests', [
            'user_id' => $user->id,
            'source' => 'b2b_price_request',
            'product_id' => $product->id,
            'requested_quantity' => 12,
            'status' => 'new',
        ]);
        $this->assertStringContainsString('ООО «Монтаж»', (string) ContactRequest::query()->value('message'));

        $this->actingAs($user)
            ->postJson(route('b2b.price-requests.store'), [
                'product_id' => $product->id,
                'quantity' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('contact_requests', 1);

        ContactRequest::query()->update([
            'status' => 'done',
            'client_response' => 'Оптовая цена 105 BYN, поставка 3 дня.',
        ]);

        $this->actingAs($user)
            ->get(route('b2b.catalog', ['mode' => 'reference', 'q' => 'REQUEST-1']))
            ->assertOk()
            ->assertSeeText('Оптовая цена 105 BYN, поставка 3 дня.');
    }

    public function test_multiple_price_sources_of_one_partner_are_grouped_into_one_supplier_filter(): void
    {
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'b2b-supplier-group-category',
            'is_active' => true,
        ]);
        $firstProduct = $this->product($category, 'Товар из 1С', 'GROUP-1');
        $secondProduct = $this->product($category, 'Товар из отдельного прайса', 'GROUP-2');
        $firstSource = $this->source('group-source-1', $category, 'ООО «СанБизнесГруп»');
        $secondSource = $this->source('group-source-2', $category, 'ООО «СанБизнесГруп»');
        $otherSource = $this->source('group-source-3', $category, 'Другой поставщик');
        $this->offer($firstSource, $firstProduct, 80, 5);
        $this->offer($secondSource, $secondProduct, 70, 8);
        $otherProduct = $this->product($category, 'Товар другого поставщика', 'GROUP-3');
        $this->offer($otherSource, $otherProduct, 60, 4);
        $user = $this->approvedUser([$firstSource->id, $secondSource->id, $otherSource->id]);

        $response = $this->actingAs($user)->get(route('b2b.catalog'));
        $sourceKey = substr(sha1(mb_strtolower('ООО «СанБизнесГруп»')), 0, 12);

        $response->assertOk()
            ->assertSeeText('Товар из 1С')
            ->assertSeeText('Товар из отдельного прайса');
        $this->assertSame(1, substr_count($response->getContent(), '<option value="'.$sourceKey.'"'));

        $this->actingAs($user)
            ->get(route('b2b.catalog', ['source' => $sourceKey]))
            ->assertOk()
            ->assertSeeText('Товар из 1С')
            ->assertSeeText('Товар из отдельного прайса')
            ->assertDontSeeText('Товар другого поставщика');
    }

    public function test_procurement_price_uses_fresh_local_stock_before_delivery_fallback(): void
    {
        $category = Category::query()->create([
            'name' => 'Печи',
            'slug' => 'b2b-procurement-stock-category',
            'is_active' => true,
        ]);
        $product = $this->product($category, 'Печь Царь-Печи Ярило', 'TSAR-LOCAL-1');
        $localSource = $this->source('local-stock-test', $category, 'ООО «СанБизнесГруп»');
        $localSettings = $localSource->settings;
        $localSettings['warehouse_label'] = 'Основной';
        $localSource->update(['settings' => $localSettings]);
        $procurementSource = $this->source('tsar-pechi-test', $category, 'ООО «СанБизнесГруп»');
        $procurementSettings = $procurementSource->settings;
        $procurementSettings['procurement_mode'] = true;
        $procurementSettings['warehouse_label'] = 'Склад Москва · поставка 7 дней';
        $procurementSource->update(['settings' => $procurementSettings]);

        $this->offer($localSource, $product, 110, 3);
        $this->offer($procurementSource, $product, 80, 0);
        $user = $this->approvedUser([$localSource->id, $procurementSource->id]);

        $this->actingAs($user)
            ->get(route('b2b.catalog', ['q' => 'TSAR-LOCAL-1']))
            ->assertOk()
            ->assertSeeText('80,00 BYN')
            ->assertSeeText('Основной: 3 шт.')
            ->assertDontSeeText('Склад Москва · поставка 7 дней');

        $this->actingAs($user)
            ->getJson(route('b2b.catalog.suggest', ['q' => 'TSAR-LOCAL-1']))
            ->assertOk()
            ->assertJsonPath('items.0.stock', 'Основной: 3 шт.');
    }

    public function test_procurement_delivery_is_shown_when_local_stock_is_empty(): void
    {
        $category = Category::query()->create([
            'name' => 'Печи',
            'slug' => 'b2b-procurement-fallback-category',
            'is_active' => true,
        ]);
        $product = $this->product($category, 'Печь Царь-Печи Добрыня', 'TSAR-ORDER-1');
        $source = $this->source('tsar-pechi-fallback', $category, 'ООО «СанБизнесГруп»');
        $settings = $source->settings;
        $settings['procurement_mode'] = true;
        $settings['warehouse_label'] = 'Склад Москва · поставка 7 дней';
        $source->update(['settings' => $settings]);
        $this->offer($source, $product, 80, 0);
        $user = $this->approvedUser([$source->id]);

        $this->actingAs($user)
            ->get(route('b2b.catalog', ['q' => 'TSAR-ORDER-1']))
            ->assertOk()
            ->assertSeeText('Склад Москва · поставка 7 дней');
    }

    public function test_category_tree_filters_the_whole_selected_branch(): void
    {
        $root = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'b2b-tree-root',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $child = Category::query()->create([
            'name' => 'Трубы моно',
            'slug' => 'b2b-tree-child',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);
        $other = Category::query()->create([
            'name' => 'Котлы',
            'slug' => 'b2b-tree-other',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $childProduct = $this->product($child, 'Труба в ветке', 'TREE-1');
        $otherProduct = $this->product($other, 'Котёл вне ветки', 'TREE-2');
        $source = $this->source('tree-source', $root);
        $this->offer($source, $childProduct, 80, 5);
        $this->offer($source, $otherProduct, 70, 8);
        $user = $this->approvedUser([$source->id]);

        $this->actingAs($user)
            ->get(route('b2b.catalog', ['category' => $root->id]))
            ->assertOk()
            ->assertSeeText('Дымоходы')
            ->assertSeeText('Трубы моно')
            ->assertSeeText('Труба в ветке')
            ->assertDontSeeText('Котёл вне ветки');
    }

    public function test_category_tree_is_sorted_alphabetically_on_every_level(): void
    {
        $zRoot = Category::query()->create([
            'name' => 'Ящики',
            'slug' => 'b2b-alpha-z-root',
            'parent_id' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $aRoot = Category::query()->create([
            'name' => 'Арматура',
            'slug' => 'b2b-alpha-a-root',
            'parent_id' => 0,
            'sort_order' => 999,
            'is_active' => true,
        ]);
        $zChild = Category::query()->create([
            'name' => 'Фитинги',
            'slug' => 'b2b-alpha-z-child',
            'parent_id' => $aRoot->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $aChild = Category::query()->create([
            'name' => 'Краны',
            'slug' => 'b2b-alpha-a-child',
            'parent_id' => $aRoot->id,
            'sort_order' => 999,
            'is_active' => true,
        ]);

        $source = $this->source('alpha-tree-source', $aRoot);
        $settings = $source->settings;
        $settings['b2b_category_ids'] = [$aRoot->id, $zRoot->id];
        $source->update(['settings' => $settings]);
        $this->offer($source, $this->product($zRoot, 'Товар из ящиков', 'ALPHA-ROOT-Z'), 20, 1);
        $this->offer($source, $this->product($zChild, 'Фитинг тестовый', 'ALPHA-CHILD-Z'), 30, 1);
        $this->offer($source, $this->product($aChild, 'Кран тестовый', 'ALPHA-CHILD-A'), 40, 1);
        $user = $this->approvedUser([$source->id]);

        $content = $this->actingAs($user)
            ->get(route('b2b.catalog'))
            ->assertOk()
            ->getContent();
        $treeStart = strpos($content, 'id="b2b-category-tree"');

        $this->assertNotFalse($treeStart);
        $this->assertStringContainsString('class="b2b-category-leaf b2b-category-leaf--root"', $content);
        $this->assertLessThan(strpos($content, 'Ящики', $treeStart), strpos($content, 'Арматура', $treeStart));
        $this->assertLessThan(strpos($content, 'Фитинги', $treeStart), strpos($content, 'Краны', $treeStart));
    }

    public function test_search_switches_from_selected_category_to_the_whole_catalog(): void
    {
        $selectedCategory = Category::query()->create([
            'name' => 'Теплоносители',
            'slug' => 'b2b-search-selected-category',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $matchingCategory = Category::query()->create([
            'name' => 'Бани и сауны',
            'slug' => 'b2b-search-matching-category',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $selectedProduct = $this->product($selectedCategory, 'Антифриз для отопления', 'SEARCH-GROUP-1');
        $matchingProduct = $this->product($matchingCategory, 'Камни для бани', 'SEARCH-GROUP-2');
        $source = $this->source('search-switch-source', $selectedCategory);
        $settings = $source->settings;
        $settings['b2b_category_ids'] = [$selectedCategory->id, $matchingCategory->id];
        $source->update(['settings' => $settings]);
        $this->offer($source, $selectedProduct, 30, 5);
        $this->offer($source, $matchingProduct, 40, 7);
        $user = $this->approvedUser([$source->id]);

        $this->actingAs($user)
            ->get(route('b2b.catalog', [
                'category' => $selectedCategory->id,
                'q' => 'камни',
            ]))
            ->assertOk()
            ->assertSeeText('Камни для бани')
            ->assertDontSeeText('Антифриз для отопления')
            ->assertDontSee('<input type="hidden" name="category"', false);
    }

    public function test_mobile_category_photos_are_selected_by_matching_branch_name(): void
    {
        $root = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'photo-root',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $mono = Category::query()->create([
            'name' => 'Система Моно',
            'slug' => 'test-dymohody-mono',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);
        $sandwich = Category::query()->create([
            'name' => 'Система Сэндвич',
            'slug' => 'test-dymohody-sendvich',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);
        $monoProduct = $this->product($mono, 'Труба моно', 'MONO-1');
        $monoProduct->update(['images' => ['/img/test/mono.jpg']]);
        $sandwichProduct = $this->product($sandwich, 'Труба сэндвич', 'SANDWICH-1');
        $sandwichProduct->update(['images' => ['/img/test/sandwich.jpg']]);
        $source = $this->source('photo-source', $root);
        $this->offer($source, $sandwichProduct, 90, 3);
        $this->offer($source, $monoProduct, 80, 5);
        $user = $this->approvedUser([$source->id]);

        $this->actingAs($user)
            ->get(route('b2b.catalog'))
            ->assertOk()
            ->assertSeeInOrder([
                '/img/test/mono.jpg',
                'Система Моно',
                '/img/test/sandwich.jpg',
                'Система Сэндвич',
            ], false);
    }

    public function test_marketplace_uses_compact_localized_pagination(): void
    {
        $category = Category::query()->create([
            'name' => 'Пагинация',
            'slug' => 'b2b-pagination-category',
            'is_active' => true,
        ]);
        $source = $this->source('pagination-source', $category);
        for ($index = 1; $index <= 31; $index++) {
            $product = $this->product($category, 'Позиция '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'PAGE-'.$index);
            $this->offer($source, $product, 50 + $index, 3);
        }
        $user = $this->approvedUser([$source->id]);

        $this->actingAs($user)
            ->get(route('b2b.catalog'))
            ->assertOk()
            ->assertSeeText('Товары 1–30 из 31')
            ->assertSee('aria-label="Следующая страница"', false)
            ->assertDontSeeText('pagination.previous')
            ->assertDontSeeText('pagination.next');
    }

    private function product(Category $category, string $name, string $sku): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'price' => 120,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
        ]);
    }

    private function source(string $code, Category $category, ?string $partnerName = null): IntegrationSource
    {
        $supplier = Supplier::query()->create([
            'code' => $code.'-supplier',
            'name' => $partnerName ?: $code,
            'is_active' => true,
        ]);

        return IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => $code,
            'name' => $code,
            'is_active' => true,
            'settings' => [
                'b2b_enabled' => true,
                'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE,
                'b2b_category_ids' => [$category->id],
                'price_tax_mode' => 'inclusive',
                'vat_rate' => 20,
                'partner_name' => $partnerName ?: $code,
            ],
        ]);
    }

    /** @param list<int> $sourceIds */
    private function approvedUser(array $sourceIds): User
    {
        $user = User::factory()->create([
            'client_type' => 'wholesale',
            'b2b_approved' => true,
            'is_active' => true,
        ]);
        B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Тест»',
            'allowed_integration_source_ids' => $sourceIds,
        ]);

        return $user;
    }

    private function offer(IntegrationSource $source, Product $product, float $price, int $stock): void
    {
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => $source->code.'-'.$product->id,
            'name' => $product->name,
            'price' => $price,
            'price_byn' => $price,
            'stock_quantity' => $stock,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);
    }
}
