<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Actions\CreateCommercialDocumentFromOrderAction;
use App\Models\Category;
use App\Models\CommercialDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCommercialDocumentPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('commercial_documents', 'retail_quick')) {
            Schema::table('commercial_documents', fn (Blueprint $table) => $table->boolean('retail_quick')->default(false));
        }
    }

    public function test_manager_can_prepare_invoice_from_exact_order_snapshot(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Печи', 'slug' => 'order-document-stoves', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Текущая карточка товара',
            'slug' => 'order-document-product',
            'sku' => 'CURRENT-SKU',
            'price' => 2100,
            'unit' => 'шт.',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-TEST-DOC',
            'status' => 'confirmed',
            'customer_name' => 'Иван Иванов',
            'customer_phone' => '+375291112233',
            'customer_email' => 'ivan@example.com',
            'company_name' => 'ООО «Клиент»',
            'company_unp' => '193900001',
            'company_address' => 'г. Минск',
            'delivery_type' => 'transport',
            'delivery_address' => 'г. Гродно',
            'delivery_price' => 25,
            'payment_type' => 'invoice',
            'discount' => 100,
            'subtotal' => 3600,
            'total' => 3525,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Название на момент заказа',
            'product_sku' => 'ORDER-SKU',
            'price' => 1800,
            'quantity' => 2,
            'total' => 3600,
        ]);

        $this->actingAs($manager);
        $response = CreateCommercialDocumentFromOrderAction::prepare($order, 'invoice');

        $document = CommercialDocument::query()->sole();
        $this->assertSame('/admin/commercial-documents/'.$document->id.'/edit', parse_url($response->getTargetUrl(), PHP_URL_PATH));
        $this->assertSame('invoice', $document->type);
        $this->assertSame('ООО «Клиент»', $document->client_company);
        $this->assertSame('ORD-TEST-DOC', str($document->notes)->after('заказа ')->before('.')->toString());
        $this->assertSame('Название на момент заказа', $document->items->first()->name);
        $this->assertSame('ORDER-SKU', $document->items->first()->sku);
        $this->assertSame('1800.00', $document->items->first()->unit_price);
        $this->assertSame('2.000', $document->items->first()->quantity);
        $this->assertSame('3525.00', $document->total);
        $this->assertFalse((bool) $document->retail_quick);
    }

    public function test_individual_order_uses_quick_retail_mode_without_purchase_checks(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Розница', 'slug' => 'retail-document', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Розничный товар',
            'slug' => 'retail-document-product',
            'sku' => 'RETAIL-SKU',
            'price' => 100,
            'unit' => 'шт.',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-RETAIL-DOC',
            'status' => 'confirmed',
            'customer_name' => 'Розничный покупатель',
            'customer_phone' => '+375291234567',
            'customer_email' => 'retail@example.com',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'subtotal' => 100,
            'total' => 100,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Розничный товар',
            'product_sku' => 'RETAIL-SKU',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
        ]);

        $this->actingAs($manager);
        $response = CreateCommercialDocumentFromOrderAction::prepare($order, 'invoice');

        $document = CommercialDocument::query()->sole();
        $this->assertSame('/admin/commercial-documents/'.$document->id, parse_url($response->getTargetUrl(), PHP_URL_PATH));
        $this->assertTrue($document->retail_quick);
        $this->assertTrue($document->allow_unconfirmed_purchase_price);
        $this->assertSame([], app(CommercialDocumentReadiness::class)->issues($document));
        $this->assertSame([], app(CommercialDocumentReadiness::class)->warnings($document));
    }
}
