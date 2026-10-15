<?php

namespace Tests\Feature;

use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Models\Category;
use App\Models\CommercialDocument;
use App\Models\CrmCounterparty;
use App\Models\IntegrationSource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use App\Services\Orders\OrderItemSupplyContextResolver;
use App\Services\Pricing\ConfirmedPurchasePriceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ConfirmedPurchasePriceSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_invoice_becomes_the_purchase_price_source_for_margin_and_readiness(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->product(['requires_price_confirmation' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'person',
            'classification' => 'retail_client',
            'business_unit' => 'retail',
            'status' => 'active',
            'name' => 'Покупатель',
            'source' => 'manual',
        ]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'crm_counterparty_id' => $counterparty->id,
            'client_name' => 'Покупатель',
            'client_email' => 'client@example.com',
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '3 рабочих дня',
        ]);
        $document->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 8063,
        ]);

        $source = app(ConfirmedPurchasePriceService::class)->create($product, [
            'supplier_name' => 'ООО «АвтоТрейдИнвест»',
            'document_number' => '9558',
            'document_date' => '2026-10-15',
            'valid_until' => '2026-10-20',
            'source_price' => 5460.85,
            'currency' => 'BYN',
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'vat_rate' => 20,
        ], $manager);

        $this->assertSame('6553.02', $source->price_byn);
        $this->assertSame('Счёт поставщика №9558 от 15.10.2026', $source->documentLabel());
        $this->assertFalse($product->fresh()->requires_price_confirmation);

        $item = new OrderItem(['product_id' => $product->id, 'price' => 8063, 'quantity' => 1]);
        $item->setRelation('product', $product->fresh());
        $context = app(OrderItemSupplyContextResolver::class)->resolve($item);

        $this->assertSame('supplier_invoice', $context['channel']);
        $this->assertSame(6553.02, $context['wholesale_price']);
        $this->assertSame('ООО «АвтоТрейдИнвест»', $context['supplier_name']);
        $this->assertSame('Счёт поставщика №9558 от 15.10.2026', $context['source_label']);
        $this->assertSame(1509.98, $context['margin_unit']);
        $this->assertSame([], app(CommercialDocumentReadiness::class)->issues($document->fresh()));
    }

    public function test_expired_invoice_is_not_used_as_a_current_purchase_price(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->product(['manual_purchase_price_byn' => 700]);

        app(ConfirmedPurchasePriceService::class)->create($product, [
            'supplier_name' => 'Просроченный поставщик',
            'document_number' => '1',
            'document_date' => today()->subDays(10)->toDateString(),
            'valid_until' => today()->subDay()->toDateString(),
            'source_price' => 600,
            'currency' => 'BYN',
            'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
            'vat_rate' => 20,
        ], $manager);

        $item = new OrderItem(['product_id' => $product->id, 'price' => 900, 'quantity' => 1]);
        $item->setRelation('product', $product->fresh());
        $context = app(OrderItemSupplyContextResolver::class)->resolve($item);

        $this->assertSame('manual', $context['channel']);
        $this->assertSame(700.0, $context['wholesale_price']);
    }

    public function test_manager_can_download_an_optional_private_invoice_attachment(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->product();
        $path = 'supplier-price-evidence/invoice-9558.pdf';
        Storage::disk('local')->put($path, 'invoice');

        $source = app(ConfirmedPurchasePriceService::class)->create($product, [
            'supplier_name' => 'ООО «АвтоТрейдИнвест»',
            'document_number' => '9558',
            'document_date' => today()->toDateString(),
            'source_price' => 6553.02,
            'currency' => 'BYN',
            'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
            'vat_rate' => 20,
            'attachment_path' => $path,
        ], $manager);

        $this->actingAs($manager)
            ->get(route('admin.purchase-price-sources.attachment', $source))
            ->assertOk()
            ->assertDownload('invoice-9558.pdf');
    }

    public function test_manager_can_add_a_supplier_invoice_from_a_commercial_document(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->product(['requires_price_confirmation' => true]);
        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_name' => 'Покупатель',
            'client_email' => 'client@example.com',
            'payment_terms' => '100% предоплата',
            'delivery_terms' => '3 рабочих дня',
        ]);
        $item = $document->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 8063,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewCommercialDocument::class, ['record' => $document->getRouteKey()])
            ->callAction('confirm_purchase_price', [
                'commercial_document_item_id' => $item->id,
                'supplier_name' => 'ООО «АвтоТрейдИнвест»',
                'document_number' => '9558',
                'document_date' => '2026-10-15',
                'valid_until' => '2026-10-20',
                'source_price' => 6553.02,
                'currency' => 'BYN',
                'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
                'vat_rate' => 20,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('product_purchase_price_sources', [
            'product_id' => $product->id,
            'document_number' => '9558',
            'price_byn' => 6553.02,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function product(array $attributes = []): Product
    {
        $category = Category::query()->firstOrCreate([
            'slug' => 'confirmed-purchase-source-test',
        ], [
            'name' => 'Подтверждённые закупки',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Электрический котёл ЭВАН Practic 72',
            'slug' => 'evan-practic-72-confirmed-source',
            'sku' => 'PS-010.366',
            'price' => 8063,
            'currency' => 'BYN',
            'is_active' => true,
        ], $attributes));
    }
}
