<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\CreateCommercialDocument;
use App\Models\Category;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialDocumentPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_mail_product_is_prefilled_in_new_commercial_offer(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Котлы для быстрого КП',
            'slug' => 'quick-commercial-offer-prefill',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Твердотопливный котёл Татра КТ30-НЛ1',
            'slug' => 'tatra-kt30-prefill',
            'sku' => 'PS-011.367',
            'price' => 3154,
            'currency' => 'BYN',
            'unit' => 'шт.',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $this->actingAs($user);
        session()->put('commercial_document_prefill', [
            'type' => 'quote',
            'client_company' => 'ОАО «Колпень-Агро»',
            'client_default_discount_percent' => 10,
        ]);
        session()->put('commercial_document_product_ids', [$product->id]);
        session()->put('commercial_document_product_quantities', [$product->id => 1]);

        $component = Livewire::test(CreateCommercialDocument::class)->assertSuccessful();
        $items = collect($component->get('data.items'));

        $this->assertCount(1, $items);
        $this->assertSame($product->id, (int) $items->first()['product_id']);
        $this->assertSame($product->name, $items->first()['name']);
        $this->assertSame(3154.0, (float) $items->first()['unit_price']);
        $this->assertSame(10.0, (float) $items->first()['discount_percent']);
        $this->assertSame(2838.6, (float) $items->first()['line_total']);

        $component->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseHas('commercial_document_items', [
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3154,
            'discount_percent' => 10,
            'line_total' => 2838.6,
        ]);
        $this->assertDatabaseHas('crm_counterparties', [
            'name' => 'ОАО «Колпень-Агро»',
            'classification' => 'b2b_prospect',
            'status' => 'lead',
        ]);
        $counterparty = CrmCounterparty::query()->where('name', 'ОАО «Колпень-Агро»')->firstOrFail();
        $this->assertDatabaseHas('commercial_documents', [
            'client_company' => 'ОАО «Колпень-Агро»',
            'crm_counterparty_id' => $counterparty->id,
        ]);
    }
}
