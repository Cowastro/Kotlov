<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\User;
use App\Services\ContactRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bMarketplaceTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSeeText('Оптовый каталог')
            ->assertSeeText('Труба со склада')
            ->assertDontSeeText('Чужой прайс');

        $this->actingAs($user)
            ->postJson(route('cart.add'), ['product_id' => $hiddenProduct->id, 'quantity' => 1])
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
        $this->assertStringContainsString('ООО «Монтаж»', (string) \App\Models\ContactRequest::query()->value('message'));

        $this->actingAs($user)
            ->postJson(route('b2b.price-requests.store'), [
                'product_id' => $product->id,
                'quantity' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('contact_requests', 1);

        \App\Models\ContactRequest::query()->update([
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
        return IntegrationSource::query()->create([
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
