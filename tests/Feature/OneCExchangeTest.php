<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleRedirects;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Integrations\CommerceMlCatalogImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OneCExchangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleRedirects::class);

        config()->set('onec.exchange.username', 'onec-test');
        config()->set('onec.exchange.password', 'secret-test');
        config()->set('onec.exchange.storage_disk', 'local');
        config()->set('onec.exchange.storage_path', 'onec-exchange');
        Cache::flush();
        Storage::fake('local');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status')->default('new');
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('delivery_type')->default('courier');
            $table->string('delivery_region')->nullable();
            $table->string('delivery_city')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('payment_type')->default('cash');
            $table->string('payment_status')->default('pending');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('comment')->nullable();
            $table->timestamp('onec_exported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('driver')->default('commerceml');
            $table->string('username')->nullable();
            $table->string('password_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('create_products')->default(false);
            $table->boolean('update_prices')->default(false);
            $table->boolean('update_stock')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_source_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('external_id');
            $table->string('external_code')->nullable();
            $table->string('external_sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('stock_quantity', 12, 3)->nullable();
            $table->string('match_status')->default('unmatched');
            $table->string('match_method')->nullable();
            $table->decimal('match_confidence', 5, 4)->nullable();
            $table->json('candidates')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_exchange_requires_authentication(): void
    {
        $this->get('/1c/exchange?type=catalog&mode=checkauth')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_checkauth_and_init_follow_onec_protocol(): void
    {
        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=checkauth')
            ->assertOk()
            ->assertSeeText("success\nonec_exchange\n", false);

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk()
            ->assertSeeText('zip=no', false)
            ->assertSeeText('file_limit=10485760', false);
    }

    public function test_exchange_can_use_hashed_credentials_from_integration_source(): void
    {
        config()->set('onec.exchange.username', null);
        config()->set('onec.exchange.password', null);
        IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
            'username' => 'onec-admin-config',
            'password_hash' => Hash::make('source-secret'),
        ]);

        $this->withBasicAuth('onec-admin-config', 'source-secret')
            ->get('/1c/exchange?type=catalog&mode=checkauth')
            ->assertOk()
            ->assertSeeText("success\nonec_exchange\n", false);
    }

    public function test_each_supplier_has_an_isolated_exchange_path_and_credentials(): void
    {
        $supplierA = IntegrationSource::query()->create([
            'code' => 'supplier-a',
            'name' => 'Поставщик A',
            'username' => 'supplier-a-user',
            'password_hash' => Hash::make('supplier-a-secret'),
        ]);
        $supplierB = IntegrationSource::query()->create([
            'code' => 'supplier-b',
            'name' => 'Поставщик B',
            'username' => 'supplier-b-user',
            'password_hash' => Hash::make('supplier-b-secret'),
        ]);

        $this->withBasicAuth('supplier-a-user', 'supplier-a-secret')
            ->get('/1c/exchange/supplier-a?type=catalog&mode=checkauth')
            ->assertOk();

        $this->withBasicAuth('supplier-a-user', 'supplier-a-secret')
            ->get('/1c/exchange/supplier-b?type=catalog&mode=checkauth')
            ->assertUnauthorized();

        $xml = '<?xml version="1.0" encoding="UTF-8"?><КоммерческаяИнформация><Каталог><Товары><Товар><Ид>supplier-product-1</Ид><Наименование>Товар поставщика</Наименование></Товар></Товары></Каталог></КоммерческаяИнформация>';

        $this->call(
            'POST',
            '/1c/exchange/supplier-a?type=catalog&mode=file&filename=import.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'supplier-a-user', 'PHP_AUTH_PW' => 'supplier-a-secret'],
            $xml
        )->assertOk();

        $session = hash('sha256', 'supplier-a-user');
        Storage::disk('local')->assertExists("onec-exchange/supplier-a/{$session}/import.xml");

        $this->withBasicAuth('supplier-a-user', 'supplier-a-secret')
            ->get('/1c/exchange/supplier-a?type=catalog&mode=import&filename=import.xml')
            ->assertOk();

        $this->assertDatabaseHas('integration_products', [
            'integration_source_id' => $supplierA->id,
            'external_id' => 'supplier-product-1',
        ]);
        $this->assertDatabaseMissing('integration_products', [
            'integration_source_id' => $supplierB->id,
            'external_id' => 'supplier-product-1',
        ]);
    }

    public function test_catalog_file_is_received_without_mutating_products(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><КоммерческаяИнформация />';

        $this->call(
            'POST',
            '/1c/exchange?type=catalog&mode=file&filename=import.xml',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/xml',
                'PHP_AUTH_USER' => 'onec-test',
                'PHP_AUTH_PW' => 'secret-test',
            ],
            $xml
        )
            ->assertOk()
            ->assertSeeText('success');

        $session = hash('sha256', 'onec-test');
        Storage::disk('local')->assertExists("onec-exchange/onec/{$session}/import.xml");
        $this->assertSame(0, Product::query()->count());
    }

    public function test_catalog_item_is_staged_and_linked_by_exact_sku(): void
    {
        $product = Product::query()->create([
            'sku' => 'BOILER-100',
            'name' => 'Котёл тестовый 100',
        ]);
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Каталог><Товары><Товар>
    <Ид>11111111-1111-1111-1111-111111111111</Ид>
    <Артикул>BOILER-100</Артикул>
    <Наименование>Котёл из 1С</Наименование>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;

        $this->call(
            'POST',
            '/1c/exchange?type=catalog&mode=file&filename=import.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $xml
        )->assertOk();

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=import&filename=import.xml')
            ->assertOk()
            ->assertSeeText('success');

        $this->assertDatabaseHas('integration_products', [
            'external_id' => '11111111-1111-1111-1111-111111111111',
            'product_id' => $product->id,
            'match_status' => 'matched',
            'match_method' => 'article_to_sku',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Котёл тестовый 100',
        ]);
    }

    public function test_catalog_item_reads_onec_code_and_links_it_to_existing_sku(): void
    {
        $product = Product::query()->create([
            'sku' => 'БП-00001234',
            'name' => 'Существующая карточка',
        ]);
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация xmlns="urn:1C.ru:commerceml_2">
  <Каталог><Товары><Товар>
    <Ид>22222222-2222-2222-2222-222222222222</Ид>
    <Артикул />
    <Наименование>Название из 1С</Наименование>
    <ЗначенияРеквизитов><ЗначениеРеквизита>
      <Наименование>Код</Наименование><Значение>БП-00001234</Значение>
    </ЗначениеРеквизита></ЗначенияРеквизитов>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;

        app(CommerceMlCatalogImporter::class)->import($xml);

        $this->assertDatabaseHas('integration_products', [
            'external_id' => '22222222-2222-2222-2222-222222222222',
            'external_code' => 'БП-00001234',
            'product_id' => $product->id,
            'match_status' => 'matched',
            'match_method' => 'onec_code_to_sku',
        ]);
    }

    public function test_orders_are_exported_and_marked_only_after_success(): void
    {
        $product = Product::query()->create([
            'sku' => 'KOTLOV-000001',
            'name' => 'Котёл тестовый',
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'onec-product-guid-1',
            'external_sku' => 'KOTLOV-000001',
            'name' => 'Котёл тестовый',
            'match_status' => 'matched',
        ]);

        $order = Order::query()->create([
            'number' => 'ORD-2026-0001',
            'status' => 'new',
            'customer_name' => 'Тестовый покупатель',
            'customer_phone' => '+375291112233',
            'customer_email' => 'buyer@example.test',
            'delivery_type' => 'courier',
            'delivery_city' => 'Минск',
            'delivery_address' => 'Тестовая, 1',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 120,
            'total' => 120,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Котёл тестовый',
            'product_sku' => 'KOTLOV-000001',
            'price' => 120,
            'quantity' => 1,
            'total' => 120,
        ]);

        $response = $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=query');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('ORD-2026-0001', false)
            ->assertSee('KOTLOV-000001', false)
            ->assertSee('onec-product-guid-1', false);

        $this->assertNull($order->fresh()->onec_exported_at);

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=success')
            ->assertOk()
            ->assertSeeText('success');

        $this->assertNotNull($order->fresh()->onec_exported_at);
    }
}
