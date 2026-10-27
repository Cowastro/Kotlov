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

    private function source(string $code, Category $category): IntegrationSource
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
            ],
        ]);
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
