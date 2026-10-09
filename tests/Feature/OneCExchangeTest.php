<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleRedirects;
use App\Models\Category;
use App\Models\IntegrationCategory;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\CommerceMlCatalogImporter;
use App\Services\Integrations\IntegrationCatalogAudit;
use App\Services\Integrations\IntegrationCatalogSummary;
use App\Services\Integrations\IntegrationCategoryAdvisor;
use App\Services\Integrations\IntegrationIssueAdvisor;
use App\Services\Integrations\IntegrationIssueDetector;
use App\Services\Integrations\IntegrationIssueTriageSummary;
use App\Services\Integrations\IntegrationManualMatchRecorder;
use App\Services\Integrations\IntegrationOperationsSummary;
use App\Services\Integrations\IntegrationProductMatchAdvisor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        if (! Schema::hasTable('orders')) {
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
                $table->string('onec_external_id')->nullable();
                $table->string('onec_status')->nullable();
                $table->timestamp('onec_status_received_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'onec_external_id')) {
                    $table->string('onec_external_id')->nullable();
                }
                if (! Schema::hasColumn('orders', 'onec_status')) {
                    $table->string('onec_status')->nullable();
                }
                if (! Schema::hasColumn('orders', 'onec_status_received_at')) {
                    $table->timestamp('onec_status_received_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('order_items')) {
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
        }

        if (! Schema::hasTable('order_status_history')) {
            Schema::create('order_status_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status_from')->nullable();
                $table->string('status_to');
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->nullable();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'sku')) {
                    $table->string('sku')->nullable();
                }
                if (! Schema::hasColumn('products', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('products', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable();
                }
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_sources')) {
            Schema::create('integration_sources', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('driver')->default('commerceml');
                $table->string('username')->nullable();
                $table->string('password_hash')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_authenticated_at')->nullable();
                $table->boolean('create_products')->default(false);
                $table->boolean('update_prices')->default(false);
                $table->boolean('update_stock')->default(false);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('integration_sources', 'last_authenticated_at')) {
            Schema::table('integration_sources', function (Blueprint $table) {
                $table->timestamp('last_authenticated_at')->nullable();
            });
        }

        if (! Schema::hasTable('integration_categories')) {
            Schema::create('integration_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_source_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('external_id');
                $table->string('parent_external_id')->nullable();
                $table->string('name');
                $table->string('path', 1024);
                $table->json('payload')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_products')) {
            Schema::create('integration_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_source_id');
                $table->unsignedBigInteger('integration_category_id')->nullable();
                $table->unsignedBigInteger('target_category_id')->nullable();
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
                $table->timestamp('last_offer_seen_at')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('integration_products', 'target_category_id')) {
            Schema::table('integration_products', function (Blueprint $table) {
                $table->unsignedBigInteger('target_category_id')->nullable();
            });
        }

        if (! Schema::hasColumn('integration_products', 'last_offer_seen_at')) {
            Schema::table('integration_products', function (Blueprint $table) {
                $table->timestamp('last_offer_seen_at')->nullable();
            });
        }

        if (! Schema::hasTable('supplier_product_mappings')) {
            Schema::create('supplier_product_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('supplier_code');
                $table->string('supplier_article');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_sku')->nullable();
                $table->string('supplier_name')->nullable();
                $table->string('confidence')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_exchange_runs')) {
            Schema::create('integration_exchange_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_source_id');
                $table->string('direction', 16);
                $table->string('operation', 32);
                $table->string('status', 24)->default('running');
                $table->string('session_key', 64)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->unsignedInteger('files_count')->default(0);
                $table->unsignedBigInteger('bytes_received')->default(0);
                $table->unsignedInteger('items_received')->default(0);
                $table->unsignedInteger('items_created')->default(0);
                $table->unsignedInteger('items_updated')->default(0);
                $table->unsignedInteger('items_skipped')->default(0);
                $table->unsignedInteger('orders_count')->default(0);
                $table->json('summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integration_issues')) {
            Schema::create('integration_issues', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_source_id')->nullable();
                $table->unsignedBigInteger('integration_product_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('assigned_to_user_id')->nullable();
                $table->string('fingerprint')->unique();
                $table->string('type', 64);
                $table->string('severity', 16)->default('warning');
                $table->string('status', 16)->default('open');
                $table->string('title');
                $table->text('message')->nullable();
                $table->json('context')->nullable();
                $table->timestamp('first_detected_at');
                $table->timestamp('last_detected_at');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::disableForeignKeyConstraints();
        foreach (['integration_issues', 'integration_exchange_runs', 'integration_products', 'integration_categories', 'integration_sources', 'supplier_product_mappings', 'order_status_history', 'order_items', 'orders', 'products', 'categories'] as $table) {
            DB::table($table)->delete();
        }
        Schema::enableForeignKeyConstraints();
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

        $this->assertNotNull(
            IntegrationSource::query()->where('code', 'onec')->firstOrFail()->last_authenticated_at
        );

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

    public function test_init_removes_files_left_by_a_previous_exchange(): void
    {
        $session = hash('sha256', 'onec-test');
        $directory = "onec-exchange/onec/{$session}";
        Storage::disk('local')->put("{$directory}/import.xml", '<old-document />');
        Storage::disk('local')->put("{$directory}/import.xml.received", 'done');

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk()
            ->assertSeeText('zip=no', false);

        Storage::disk('local')->assertMissing("{$directory}/import.xml");
        Storage::disk('local')->assertMissing("{$directory}/import.xml.received");
    }

    public function test_new_xml_replaces_a_completed_file_even_with_utf8_bom(): void
    {
        $session = hash('sha256', 'onec-test');
        $path = "onec-exchange/onec/{$session}/import.xml";
        Storage::disk('local')->put($path, '<old-document />');
        Storage::disk('local')->put($path.'.received', 'done');

        $xml = "\xEF\xBB\xBF<КоммерческаяИнформация />";
        $this->call(
            'POST',
            '/1c/exchange?type=catalog&mode=file&filename=import.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $xml
        )->assertOk();

        $this->assertSame($xml, Storage::disk('local')->get($path));
        Storage::disk('local')->assertMissing($path.'.received');
    }

    public function test_catalog_item_is_staged_and_linked_by_exact_sku(): void
    {
        $product = Product::query()->create([
            'sku' => 'BOILER-100',
            'name' => 'Котёл тестовый 100',
            'slug' => 'boiler-100',
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
        $this->assertDatabaseHas('integration_exchange_runs', [
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'files_count' => 1,
            'items_received' => 1,
        ]);
    }

    public function test_repeated_catalog_import_updates_the_same_staging_product_without_duplicates(): void
    {
        $first = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Каталог><Товары><Товар>
    <Ид>stable-onec-id</Ид>
    <Артикул>STABLE-1</Артикул>
    <Наименование>Первое название</Наименование>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;
        $second = str_replace('Первое название', 'Обновлённое название', $first);
        $importer = app(CommerceMlCatalogImporter::class);

        $firstStats = $importer->import($first);
        $secondStats = $importer->import($second);

        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $this->assertSame(1, IntegrationProduct::query()
            ->where('integration_source_id', $source->id)
            ->where('external_id', 'stable-onec-id')
            ->count());
        $this->assertDatabaseHas('integration_products', [
            'integration_source_id' => $source->id,
            'external_id' => 'stable-onec-id',
            'name' => 'Обновлённое название',
        ]);
        $this->assertSame(1, $firstStats['staging_created']);
        $this->assertSame(0, $firstStats['staging_updated']);
        $this->assertSame(0, $secondStats['staging_created']);
        $this->assertSame(1, $secondStats['staging_updated']);
    }

    public function test_retried_import_request_does_not_reprocess_the_same_uploaded_file(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Каталог><Товары><Товар>
    <Ид>retry-safe-product</Ид>
    <Артикул>RETRY-1</Артикул>
    <Наименование>Товар с повторным подтверждением</Наименование>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk();
        $this->call(
            'POST',
            '/1c/exchange?type=catalog&mode=file&filename=import.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $xml,
        )->assertOk();

        $url = '/1c/exchange?type=catalog&mode=import&filename=import.xml';
        $this->withBasicAuth('onec-test', 'secret-test')->get($url)->assertOk();
        $this->withBasicAuth('onec-test', 'secret-test')->get($url)->assertOk();

        $run = IntegrationExchangeRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $run->items_received);
        $this->assertSame(1, $run->items_created);
        $this->assertSame(0, $run->items_updated);
        $this->assertSame(1, IntegrationProduct::query()
            ->where('external_id', 'retry-safe-product')
            ->count());
    }

    public function test_catalog_exchange_journal_accumulates_all_uploaded_parts(): void
    {
        $importXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Каталог><Товары><Товар>
    <Ид>multipart-product</Ид>
    <Артикул>MULTI-1</Артикул>
    <Наименование>Многофайловый товар</Наименование>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;
        $offersXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <ПакетПредложений><Предложения><Предложение>
    <Ид>multipart-product</Ид>
    <Цены><Цена><ЦенаЗаЕдиницу>42.50</ЦенаЗаЕдиницу></Цена></Цены>
    <Количество>7</Количество>
  </Предложение></Предложения></ПакетПредложений>
</КоммерческаяИнформация>
XML;

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk();

        foreach (['import.xml' => $importXml, 'offers.xml' => $offersXml] as $filename => $xml) {
            $this->call(
                'POST',
                "/1c/exchange?type=catalog&mode=file&filename={$filename}",
                [],
                [],
                [],
                ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
                $xml,
            )->assertOk();
            $this->withBasicAuth('onec-test', 'secret-test')
                ->get("/1c/exchange?type=catalog&mode=import&filename={$filename}")
                ->assertOk();
        }

        $run = IntegrationExchangeRun::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $run->files_count);
        $this->assertSame(2, $run->items_received);
        $this->assertSame(1, $run->items_created);
        $this->assertSame(1, $run->items_updated);
        $this->assertSame(1, data_get($run->summary, 'products'));
        $this->assertSame(1, data_get($run->summary, 'offers'));
        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'multipart-product',
            'price' => 42.50,
            'stock_quantity' => 7,
        ]);
    }

    public function test_completed_catalog_snapshot_zeros_only_positive_stock_missing_from_all_offer_parts(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
            'settings' => ['zero_missing_stock_on_complete' => true],
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'missing-offer',
            'name' => 'Больше не в наличии',
            'stock_quantity' => 8,
            'last_offer_seen_at' => now()->subHour(),
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'already-zero',
            'name' => 'Уже отсутствует',
            'stock_quantity' => 0,
            'last_offer_seen_at' => now()->subHour(),
        ]);

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <ПакетПредложений><Предложения><Предложение>
    <Ид>current-offer</Ид>
    <Наименование>Есть в наличии</Наименование>
    <Цены><Цена><ЦенаЗаЕдиницу>10</ЦенаЗаЕдиницу></Цена></Цены>
    <Количество>5</Количество>
  </Предложение></Предложения></ПакетПредложений>
</КоммерческаяИнформация>
XML;
        $this->call(
            'POST',
            '/1c/exchange?type=catalog&mode=file&filename=offers.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $xml,
        )->assertOk();
        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=import&filename=offers.xml')
            ->assertOk();

        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'missing-offer',
            'stock_quantity' => 8,
        ]);

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=complete')
            ->assertOk()
            ->assertSeeText('stock_zeroed=1');

        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'missing-offer',
            'stock_quantity' => 0,
        ]);
        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'current-offer',
            'stock_quantity' => 5,
        ]);
        $run = IntegrationExchangeRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, data_get($run->summary, 'stock_snapshot.zeroed'));
    }

    public function test_catalog_complete_without_received_offers_never_zeros_stock(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
            'settings' => ['zero_missing_stock_on_complete' => true],
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'safe-without-offers',
            'stock_quantity' => 3,
            'last_offer_seen_at' => now()->subHour(),
        ]);

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk();
        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=catalog&mode=complete')
            ->assertOk();

        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'safe-without-offers',
            'stock_quantity' => 3,
        ]);
    }

    public function test_catalog_item_reads_onec_code_and_links_it_to_existing_sku(): void
    {
        $product = Product::query()->create([
            'sku' => 'БП-00001234',
            'name' => 'Существующая карточка',
            'slug' => 'existing-product',
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

    public function test_catalog_preserves_nested_onec_groups_and_assigns_products(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация xmlns="urn:1C.ru:commerceml_2">
  <Классификатор>
    <Группы>
      <Группа>
        <Ид>chimneys</Ид>
        <Наименование>Дымоходы</Наименование>
        <Группы>
          <Группа>
            <Ид>single-wall</Ид>
            <Наименование>Одностенные</Наименование>
          </Группа>
        </Группы>
      </Группа>
    </Группы>
  </Классификатор>
  <Каталог><Товары><Товар>
    <Ид>pipe-1</Ид>
    <Наименование>Труба 1 м</Наименование>
    <Группы><Ид>single-wall</Ид></Группы>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;

        $stats = app(CommerceMlCatalogImporter::class)->import($xml);
        $child = IntegrationCategory::query()->where('external_id', 'single-wall')->firstOrFail();

        $this->assertSame(2, $stats['categories']);
        $this->assertSame('Дымоходы / Одностенные', $child->path);
        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'pipe-1',
            'integration_category_id' => $child->id,
        ]);
    }

    public function test_catalog_audit_exposes_group_references_for_ungrouped_products(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'audit-source',
            'name' => 'Аудит каталога',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'ungrouped-product',
            'name' => 'Товар без найденной группы',
            'stock_quantity' => 1,
            'payload' => ['Группы' => ['Ид' => 'missing-group-id']],
        ]);

        $snapshot = app(IntegrationCatalogAudit::class)->snapshot($source);

        $this->assertSame(0, $snapshot['groups']);
        $this->assertSame(1, $snapshot['products_ungrouped']);
        $this->assertSame(['missing-group-id'], $snapshot['ungrouped_sample'][0]['group_references']);
    }

    public function test_source_inspection_distinguishes_positive_zero_and_missing_prices(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'price-audit',
            'name' => 'Аудит цен',
        ]);
        foreach ([10, 0, null] as $index => $price) {
            IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => 'price-audit-'.$index,
                'price' => $price,
                'stock_quantity' => 1,
            ]);
        }

        $this->artisan('integration:inspect-source price-audit')
            ->expectsTable(
                ['Metric', 'Count'],
                [
                    ['Supplier groups', 0],
                    ['Staged products', 3],
                    ['Distinct external IDs', 3],
                    ['Duplicate external IDs', 0],
                    ['Positive stock', 3],
                    ['Zero stock', 0],
                    ['Missing stock', 0],
                    ['Positive price', 1],
                    ['Zero price', 1],
                    ['Missing price', 1],
                ],
            )
            ->assertExitCode(0);
    }

    public function test_issue_scopes_split_open_work_by_operational_area(): void
    {
        foreach ([
            [
                'fingerprint' => 'scope-product',
                'type' => 'product_attention',
                'status' => 'open',
                'context' => ['missing_price' => true, 'unmatched' => true],
            ],
            ['fingerprint' => 'scope-order', 'type' => 'order_not_exported', 'status' => 'open'],
            ['fingerprint' => 'scope-exchange', 'type' => 'integration_stale', 'status' => 'open'],
            ['fingerprint' => 'scope-resolved', 'type' => 'product_attention', 'status' => 'resolved'],
        ] as $issue) {
            IntegrationIssue::query()->create($issue + [
                'severity' => 'warning',
                'title' => 'Проверка раздела',
                'first_detected_at' => now(),
                'last_detected_at' => now(),
            ]);
        }

        $this->assertSame(3, IntegrationIssue::query()->open()->count());
        $this->assertSame(1, IntegrationIssue::query()->open()->products()->count());
        $this->assertSame(1, IntegrationIssue::query()->open()->missingPrice()->count());
        $this->assertSame(1, IntegrationIssue::query()->open()->unmatched()->count());
        $this->assertSame(1, IntegrationIssue::query()->open()->orders()->count());
        $this->assertSame(1, IntegrationIssue::query()->open()->exchange()->count());
        $this->assertSame(3, IntegrationIssue::query()->open()->priority()->count());
        $this->assertSame(0, IntegrationIssue::query()->open()->readyToLink()->count());
    }

    public function test_issue_triage_separates_priority_ready_recommendations_and_assignee(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'triage-source',
            'name' => 'Поставщик для очереди',
        ]);
        $recommended = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'triage-recommended',
            'match_status' => 'suggested',
        ]);
        $ready = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'triage-ready',
            'match_status' => 'unmatched',
        ]);
        $userId = Schema::hasTable('users') ? User::factory()->create()->id : 77;

        foreach ([
            [
                'fingerprint' => 'triage-priority',
                'type' => 'product_attention',
                'integration_product_id' => $recommended->id,
                'context' => ['missing_price' => true, 'unmatched' => true],
            ],
            [
                'fingerprint' => 'triage-ready',
                'type' => 'product_attention',
                'integration_product_id' => $ready->id,
                'assigned_to_user_id' => $userId,
                'context' => ['missing_price' => false, 'unmatched' => true],
            ],
            [
                'fingerprint' => 'triage-order',
                'type' => 'order_not_exported',
            ],
        ] as $issue) {
            IntegrationIssue::query()->create($issue + [
                'status' => 'open',
                'severity' => 'warning',
                'title' => 'Проверка приоритета',
                'first_detected_at' => now(),
                'last_detected_at' => now(),
            ]);
        }

        $summary = app(IntegrationIssueTriageSummary::class)->snapshot($userId);

        $this->assertSame(2, $summary['priority']);
        $this->assertSame(1, $summary['missing_price']);
        $this->assertSame(1, $summary['ready_to_link']);
        $this->assertSame(1, $summary['recommended']);
        $this->assertSame(1, $summary['orders']);
        $this->assertSame(1, $summary['mine']);
    }

    public function test_product_category_override_has_priority_over_supplier_group_rule(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'category-priority',
            'name' => 'Поставщик',
        ]);
        $groupCategory = Category::query()->create([
            'name' => 'Группа по умолчанию',
            'slug' => 'group-default-'.uniqid(),
            'parent_id' => 0,
        ]);
        $targetCategory = Category::query()->create([
            'name' => 'Индивидуальная категория',
            'slug' => 'individual-target-'.uniqid(),
            'parent_id' => 0,
        ]);
        $group = IntegrationCategory::query()->create([
            'integration_source_id' => $source->id,
            'category_id' => $groupCategory->id,
            'external_id' => 'source-group',
            'name' => 'Группа поставщика',
            'path' => 'Группа поставщика',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'integration_category_id' => $group->id,
            'target_category_id' => $targetCategory->id,
            'external_id' => 'category-priority-product',
            'name' => 'Товар',
            'stock_quantity' => 1,
        ]);

        $this->assertSame($targetCategory->id, $item->resolvedSiteCategory()?->id);
        $this->assertSame('Индивидуальное назначение', $item->categoryResolutionLabel());
    }

    public function test_category_advisor_suggests_only_when_candidates_agree(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'category-advisor',
            'name' => 'Поставщик',
        ]);
        $category = Category::query()->create([
            'name' => 'Одностенные дымоходы',
            'slug' => 'advisor-category-'.uniqid(),
            'parent_id' => 0,
        ]);
        $first = Product::query()->create([
            'sku' => 'ADVISOR-1',
            'name' => 'Труба 1 м',
            'slug' => 'advisor-product-1-'.uniqid(),
            'category_id' => $category->id,
        ]);
        $second = Product::query()->create([
            'sku' => 'ADVISOR-2',
            'name' => 'Труба 0,5 м',
            'slug' => 'advisor-product-2-'.uniqid(),
            'category_id' => $category->id,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'advisor-product',
            'name' => 'Труба дымохода',
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [
                ['product_id' => $first->id, 'score' => 0.74],
                ['product_id' => $second->id, 'score' => 0.7],
            ],
        ]);

        $suggestion = app(IntegrationCategoryAdvisor::class)->suggest($item);

        $this->assertSame($category->id, $suggestion['category_id']);
        $this->assertSame('Одностенные дымоходы', $suggestion['category_name']);
        $this->assertSame(0.75, $suggestion['confidence']);
    }

    public function test_product_match_advisor_explains_a_live_candidate_without_applying_it(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'match-advisor',
            'name' => 'Поставщик',
        ]);
        $category = Category::query()->create([
            'name' => 'Крепления дымоходов',
            'slug' => 'match-advisor-category-'.uniqid(),
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'sku' => 'KOTLOV-TEST-1',
            'name' => 'Крепление универсальное D200–210',
            'slug' => 'match-advisor-product-'.uniqid(),
            'category_id' => $category->id,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'match-advisor-product',
            'name' => 'Крепление универсальное КУ D200-210',
            'stock_quantity' => 1,
            'match_status' => 'suggested',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.94,
            'candidates' => [[
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'score' => 0.94,
            ]],
        ]);

        $advice = app(IntegrationProductMatchAdvisor::class)->explain($item);

        $this->assertSame($product->id, $advice['product_id']);
        $this->assertSame($product->name, $advice['product_name']);
        $this->assertSame('KOTLOV-TEST-1', $advice['product_sku']);
        $this->assertSame('Крепления дымоходов', $advice['category_name']);
        $this->assertSame(0.94, $advice['confidence']);
        $this->assertStringContainsString('94%', $advice['reason']);
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame('suggested', $item->fresh()->match_status);

        $product->delete();
        $this->assertNull(app(IntegrationProductMatchAdvisor::class)->explain($item->fresh()));
    }

    public function test_catalog_accepts_multiple_commerceml_documents_in_one_upload(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация xmlns="urn:1C.ru:commerceml_2">
  <Классификатор><Группы><Группа>
    <Ид>chimneys</Ид><Наименование>Дымоходы</Наименование>
  </Группа></Группы></Классификатор>
</КоммерческаяИнформация>
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация xmlns="urn:1C.ru:commerceml_2">
  <Каталог><Товары><Товар>
    <Ид>pipe-2</Ид><Наименование>Труба 0,5 м</Наименование>
    <Группы><Ид>chimneys</Ид></Группы>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;

        $stats = app(CommerceMlCatalogImporter::class)->import($xml);
        $category = IntegrationCategory::query()->where('external_id', 'chimneys')->firstOrFail();

        $this->assertSame(1, $stats['categories']);
        $this->assertSame(1, $stats['products']);
        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'pipe-2',
            'integration_category_id' => $category->id,
        ]);
    }

    public function test_fuzzy_matching_rejects_a_different_diameter(): void
    {
        Product::query()->create([
            'sku' => 'PS-011.849',
            'name' => 'КПД ЧЕРНЫЙ Труба 250мм 2мм ф150',
            'slug' => 'black-pipe-250-150',
        ]);
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Каталог><Товары><Товар>
    <Ид>wrong-diameter</Ид>
    <Наименование>КПД ЧЕРНЫЙ Труба 250мм 2мм ф120</Наименование>
  </Товар></Товары></Каталог>
</КоммерческаяИнформация>
XML;

        app(CommerceMlCatalogImporter::class)->import($xml);

        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'wrong-diameter',
            'product_id' => null,
            'match_status' => 'unmatched',
        ]);
    }

    public function test_fuzzy_matching_rejects_a_missing_diameter_and_rematch_updates_old_suggestion(): void
    {
        $product = Product::query()->create([
            'sku' => 'PS-011.842',
            'name' => 'Лист потолочный Угловой разборный ЛПУР 20-45°',
            'slug' => 'ceiling-sheet-lpur',
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'missing-diameter',
            'name' => 'Лист потолочный Угловой разборный ЛПУР 20-45° D250',
            'match_status' => 'suggested',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.97,
            'candidates' => [[
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'score' => 0.97,
            ]],
        ]);

        $this->artisan('integration:rematch-source onec')->assertSuccessful();

        $this->assertDatabaseHas('integration_products', [
            'external_id' => 'missing-diameter',
            'product_id' => null,
            'match_status' => 'unmatched',
        ]);
    }

    public function test_rematch_links_only_exact_unresolved_items_and_keeps_ignored_items_untouched(): void
    {
        $product = Product::query()->create([
            'sku' => 'EXACT-REMATCH-1',
            'name' => 'Труба для повторного сопоставления',
            'slug' => 'exact-rematch-product',
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
        ]);
        $candidate = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'exact-rematch-candidate',
            'external_sku' => 'EXACT-REMATCH-1',
            'name' => 'Товар из 1С',
            'match_status' => 'unmatched',
        ]);
        $ignored = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'ignored-rematch-candidate',
            'external_sku' => 'EXACT-REMATCH-1',
            'name' => 'Не для сайта',
            'match_status' => 'ignored',
        ]);

        $stats = app(CommerceMlCatalogImporter::class)->rematchSource('onec');

        $this->assertSame([
            'matched' => 1,
            'suggested' => 0,
            'ambiguous' => 0,
            'unmatched' => 0,
        ], $stats);
        $this->assertSame($product->id, $candidate->fresh()->product_id);
        $this->assertSame('matched', $candidate->fresh()->match_status);
        $this->assertNull($ignored->fresh()->product_id);
        $this->assertSame('ignored', $ignored->fresh()->match_status);
    }

    public function test_rematch_uses_an_active_reviewed_supplier_mapping_as_an_exact_identifier(): void
    {
        $product = Product::query()->create([
            'sku' => 'KOTLOV-MAPPED-1',
            'name' => 'Карточка с ручным соответствием',
            'slug' => 'manually-mapped-product',
        ]);
        DB::table('supplier_product_mappings')->insert([
            'supplier_code' => 'reviewed-supplier',
            'supplier_article' => 'TS-MAP.123',
            'product_id' => $product->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
            'settings' => ['matching_supplier_code' => 'reviewed-supplier'],
        ]);
        $candidate = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'reviewed-mapping-candidate',
            'external_sku' => 'TS MAP 123',
            'name' => 'Товар из 1С с другим названием',
            'match_status' => 'unmatched',
        ]);

        $stats = app(CommerceMlCatalogImporter::class)->rematchSource('onec');

        $this->assertSame(1, $stats['matched']);
        $this->assertSame($product->id, $candidate->fresh()->product_id);
        $this->assertSame('article_to_reviewed_mapping', $candidate->fresh()->match_method);
        $this->assertSame(1.0, $candidate->fresh()->match_confidence);
    }

    public function test_reviewed_supplier_mappings_are_isolated_between_integration_sources(): void
    {
        $product = Product::query()->create([
            'sku' => 'KOTLOV-ISOLATED-1',
            'name' => 'Товар первого поставщика',
            'slug' => 'isolated-supplier-product',
        ]);
        DB::table('supplier_product_mappings')->insert([
            'supplier_code' => 'supplier-one',
            'supplier_article' => 'COMMON-100',
            'product_id' => $product->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'supplier-two-source',
            'name' => 'Второй источник',
            'settings' => ['matching_supplier_code' => 'supplier-two'],
        ]);
        $candidate = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'isolated-candidate',
            'external_sku' => 'COMMON-100',
            'name' => 'Другой товар с тем же артикулом',
            'match_status' => 'unmatched',
        ]);

        $stats = app(CommerceMlCatalogImporter::class)->rematchSource('supplier-two-source');

        $this->assertSame(0, $stats['matched']);
        $this->assertNull($candidate->fresh()->product_id);
    }

    public function test_manual_match_recorder_teaches_the_source_for_future_imports(): void
    {
        $product = Product::query()->create([
            'sku' => 'KOTLOV-LEARNED-1',
            'name' => 'Подтверждённая карточка',
            'slug' => 'learned-mapping-product',
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'learning-source',
            'name' => 'Обучаемый источник',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'learned-external-id',
            'external_sku' => 'LEARN-ME-100',
            'name' => 'Товар из внешней системы',
            'match_status' => 'matched',
        ]);

        $mapping = app(IntegrationManualMatchRecorder::class)->record($item);

        $this->assertNotNull($mapping);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => 'integration:learning-source',
            'supplier_article' => 'LEARN-ME-100',
            'product_id' => $product->id,
            'product_sku' => 'KOTLOV-LEARNED-1',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $futureItem = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'future-external-id',
            'external_sku' => 'LEARN ME 100',
            'name' => 'Следующая выгрузка того же артикула',
            'match_status' => 'unmatched',
        ]);

        $stats = app(CommerceMlCatalogImporter::class)->rematchSource('learning-source');

        $this->assertSame(1, $stats['matched']);
        $this->assertSame($product->id, $futureItem->fresh()->product_id);
        $this->assertSame('article_to_reviewed_mapping', $futureItem->fresh()->match_method);
    }

    public function test_catalog_summary_exposes_unique_ids_stock_price_and_matching_health(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
        ]);
        $otherSource = IntegrationSource::query()->create([
            'code' => 'supplier-two',
            'name' => 'Второй поставщик',
        ]);

        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'same-external-id',
            'name' => 'Привязанный товар',
            'price' => 100,
            'stock_quantity' => 3,
            'match_status' => 'matched',
            'last_seen_at' => now()->subMinute(),
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $otherSource->id,
            'external_id' => 'same-external-id',
            'name' => 'Товар другого источника',
            'price' => 0,
            'stock_quantity' => 0,
            'match_status' => 'ambiguous',
            'last_seen_at' => now(),
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'missing-price',
            'name' => 'Без цены',
            'price' => null,
            'stock_quantity' => 1,
            'match_status' => 'unmatched',
            'last_seen_at' => now(),
        ]);

        $summary = app(IntegrationCatalogSummary::class)->snapshot();

        $this->assertSame(3, $summary['total']);
        $this->assertSame(3, $summary['unique_external_ids']);
        $this->assertSame(0, $summary['duplicates']);
        $this->assertSame(0, $summary['identity_collision_groups']);
        $this->assertSame(0, $summary['identity_collision_products']);
        $this->assertSame(2, $summary['source_count']);
        $this->assertSame(2, $summary['in_stock']);
        $this->assertSame(1, $summary['without_stock']);
        $this->assertSame(1, $summary['positive_price']);
        $this->assertSame(1, $summary['zero_price']);
        $this->assertSame(1, $summary['missing_price']);
        $this->assertSame(1, $summary['matched']);
        $this->assertSame(1, $summary['ambiguous']);
        $this->assertSame(1, $summary['unmatched']);
        $this->assertNotNull($summary['last_seen_at']);
    }

    public function test_clear_source_can_remove_staging_and_retained_exchange_files(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
        ]);
        $category = IntegrationCategory::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'chimneys',
            'name' => 'Дымоходы',
            'path' => 'Дымоходы',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'integration_category_id' => $category->id,
            'external_id' => 'pipe-3',
            'name' => 'Труба 1 м',
        ]);
        Storage::disk('local')->put('onec-exchange/onec/session/import.xml', '<xml />');

        $this->artisan('integration:clear-source onec --force --files')
            ->assertSuccessful();

        $this->assertDatabaseMissing('integration_products', ['external_id' => 'pipe-3']);
        $this->assertDatabaseMissing('integration_categories', ['external_id' => 'chimneys']);
        Storage::disk('local')->assertMissing('onec-exchange/onec/session/import.xml');
    }

    public function test_catalog_summary_detects_reused_sku_or_barcode_only_inside_one_source(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'collision-source',
            'name' => 'Источник с дублем',
        ]);
        $otherSource = IntegrationSource::query()->create([
            'code' => 'other-collision-source',
            'name' => 'Другой источник',
        ]);

        foreach ([
            ['source' => $source, 'id' => 'old-id', 'sku' => 'ABC-100', 'barcode' => '481000000001'],
            ['source' => $source, 'id' => 'new-id', 'sku' => 'ABC 100', 'barcode' => '481000000001'],
            ['source' => $otherSource, 'id' => 'supplier-id', 'sku' => 'ABC-100', 'barcode' => '481000000001'],
        ] as $row) {
            IntegrationProduct::query()->create([
                'integration_source_id' => $row['source']->id,
                'external_id' => $row['id'],
                'external_sku' => $row['sku'],
                'barcode' => $row['barcode'],
                'name' => 'Товар '.$row['id'],
                'stock_quantity' => 1,
            ]);
        }

        $sourceSummary = app(IntegrationCatalogSummary::class)->snapshot($source->id);
        $allSummary = app(IntegrationCatalogSummary::class)->snapshot();

        $this->assertSame(1, $sourceSummary['identity_collision_groups']);
        $this->assertSame(2, $sourceSummary['identity_collision_products']);
        $this->assertSame(1, $allSummary['identity_collision_groups']);
        $this->assertSame(2, $allSummary['identity_collision_products']);
    }

    public function test_orders_are_exported_and_marked_only_after_success(): void
    {
        $product = Product::query()->create([
            'sku' => 'KOTLOV-000001',
            'name' => 'Котёл тестовый',
            'slug' => 'kotlov-test-product',
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
        $this->assertDatabaseHas('integration_exchange_runs', [
            'integration_source_id' => $source->id,
            'direction' => 'outbound',
            'operation' => 'orders',
            'status' => 'success',
            'orders_count' => 1,
        ]);
    }

    public function test_order_export_excludes_orders_created_before_source_monitoring_started(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
            'settings' => ['monitor_orders_from' => now()->subHour()->toIso8601String()],
        ]);
        $oldOrder = Order::query()->create([
            'number' => 'ORD-LEGACY-NOT-FOR-1C',
            'status' => 'new',
            'customer_name' => 'Старый покупатель',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ]);
        DB::table('orders')->where('id', $oldOrder->id)->update(['created_at' => now()->subHours(2)]);
        $currentOrder = Order::query()->create([
            'number' => 'ORD-CURRENT-FOR-1C',
            'status' => 'new',
            'customer_name' => 'Новый покупатель',
            'customer_phone' => '+375291110001',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 20,
            'total' => 20,
        ]);

        $response = $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=query');

        $response->assertOk()
            ->assertDontSee('ORD-LEGACY-NOT-FOR-1C', false)
            ->assertSee('ORD-CURRENT-FOR-1C', false);

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=success')
            ->assertOk();

        $this->assertNull($oldOrder->fresh()->onec_exported_at);
        $this->assertNotNull($currentOrder->fresh()->onec_exported_at);
        $this->assertSame(1, $source->exchangeRuns()->where('operation', 'orders')->latest()->first()->orders_count);
    }

    public function test_order_statuses_are_received_from_onec_and_added_to_timeline(): void
    {
        $order = Order::query()->create([
            'number' => 'ORD-2026-STATUS-1',
            'status' => 'new',
            'customer_name' => 'Тестовый покупатель',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'courier',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 120,
            'total' => 120,
            'onec_exported_at' => now(),
        ]);
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Документ>
    <Ид>kotlov-order-{$order->id}</Ид>
    <Номер>ORD-2026-STATUS-1</Номер>
    <ЗначенияРеквизитов>
      <ЗначениеРеквизита><Наименование>Статус заказа</Наименование><Значение>Подтверждён</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Статус оплаты</Наименование><Значение>Оплачен</Значение></ЗначениеРеквизита>
    </ЗначенияРеквизитов>
  </Документ>
</КоммерческаяИнформация>
XML;

        $this->call(
            'POST',
            '/1c/exchange?type=sale&mode=file&filename=orders.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $xml
        )->assertOk();

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=import&filename=orders.xml')
            ->assertOk()
            ->assertSeeText('success');

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('Подтверждён', $order->onec_status);
        $this->assertNotNull($order->onec_status_received_at);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status_from' => 'new',
            'status_to' => 'confirmed',
            'comment' => 'Статус получен из 1С',
        ]);
        $this->assertDatabaseHas('integration_exchange_runs', [
            'operation' => 'order_statuses',
            'direction' => 'inbound',
            'status' => 'success',
            'orders_count' => 1,
        ]);
    }

    public function test_stale_onec_status_cannot_roll_an_order_back_and_opens_a_resolvable_issue(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec',
            'name' => '1С',
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-2026-STATUS-CONFLICT',
            'status' => 'delivered',
            'customer_name' => 'Тестовый покупатель',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'courier',
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 120,
            'total' => 120,
            'onec_exported_at' => now(),
        ]);

        $staleXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Документ>
    <Ид>kotlov-order-{$order->id}</Ид>
    <Номер>ORD-2026-STATUS-CONFLICT</Номер>
    <ЗначенияРеквизитов>
      <ЗначениеРеквизита><Наименование>Статус заказа</Наименование><Значение>Новый</Значение></ЗначениеРеквизита>
      <ЗначениеРеквизита><Наименование>Статус оплаты</Наименование><Значение>Ожидает оплаты</Значение></ЗначениеРеквизита>
    </ЗначенияРеквизитов>
  </Документ>
</КоммерческаяИнформация>
XML;

        $this->call(
            'POST',
            '/1c/exchange?type=sale&mode=file&filename=stale-status.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $staleXml
        )->assertOk();

        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=import&filename=stale-status.xml')
            ->assertOk()
            ->assertSeeText('success');

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('Новый', $order->onec_status);
        $this->assertDatabaseMissing('order_status_history', [
            'order_id' => $order->id,
            'status_from' => 'delivered',
            'status_to' => 'new',
        ]);
        $this->assertDatabaseHas('integration_issues', [
            'integration_source_id' => $source->id,
            'order_id' => $order->id,
            'type' => 'order_status_conflict',
            'status' => 'open',
            'severity' => 'danger',
        ]);

        $run = IntegrationExchangeRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, (int) data_get($run->summary, 'conflicts'));
        $this->assertSame(1, $run->items_skipped);
        $this->assertSame(0, $run->items_updated);

        $withoutStatusesXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <Документ>
    <Ид>kotlov-order-{$order->id}</Ид>
    <Номер>ORD-2026-STATUS-CONFLICT</Номер>
  </Документ>
</КоммерческаяИнформация>
XML;
        $this->call(
            'POST',
            '/1c/exchange?type=sale&mode=file&filename=without-statuses.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $withoutStatusesXml
        )->assertOk();
        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=import&filename=without-statuses.xml')
            ->assertOk();
        $this->assertDatabaseHas('integration_issues', [
            'order_id' => $order->id,
            'type' => 'order_status_conflict',
            'status' => 'open',
        ]);

        $currentXml = str_replace(
            ['<Значение>Новый</Значение>', '<Значение>Ожидает оплаты</Значение>'],
            ['<Значение>Доставлен</Значение>', '<Значение>Оплачен</Значение>'],
            $staleXml,
        );
        $this->call(
            'POST',
            '/1c/exchange?type=sale&mode=file&filename=current-status.xml',
            [],
            [],
            [],
            ['PHP_AUTH_USER' => 'onec-test', 'PHP_AUTH_PW' => 'secret-test'],
            $currentXml
        )->assertOk();
        $this->withBasicAuth('onec-test', 'secret-test')
            ->get('/1c/exchange?type=sale&mode=import&filename=current-status.xml')
            ->assertOk();

        $this->assertDatabaseHas('integration_issues', [
            'order_id' => $order->id,
            'type' => 'order_status_conflict',
            'status' => 'resolved',
        ]);
    }

    public function test_operations_summary_reports_healthy_exchange_and_actionable_counts(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'summary-onec',
            'name' => 'Тестовая 1С',
            'is_active' => true,
        ]);

        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'attention-product',
            'name' => 'Товар без привязки',
            'stock_quantity' => 3,
            'match_status' => 'unmatched',
        ]);

        Order::query()->create([
            'number' => 'SUMMARY-ORDER-1',
            'status' => 'new',
            'customer_name' => 'Тест',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ]);

        $summary = app(IntegrationOperationsSummary::class)->snapshot();

        $this->assertSame('healthy', $summary['health']);
        $this->assertSame(1, $summary['active_sources']);
        $this->assertSame(1, $summary['attention_products']);
        $this->assertSame(1, $summary['awaiting_orders']);
        $this->assertSame(0, $summary['failed_runs_24h']);
    }

    public function test_operations_summary_reports_stale_and_failed_exchanges(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'summary-failure',
            'name' => 'Тестовая 1С',
            'is_active' => true,
        ]);

        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinutes(40),
            'finished_at' => now()->subMinutes(39),
        ]);

        $service = app(IntegrationOperationsSummary::class);
        $this->assertSame('stale', $service->snapshot()['health']);

        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'outbound',
            'operation' => 'orders',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => 'Connection failed',
        ]);

        $summary = $service->snapshot();
        $this->assertSame('failed', $summary['health']);
        $this->assertSame(1, $summary['failed_runs_24h']);
    }

    public function test_integration_source_exposes_schedule_and_latest_successful_exchange(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'scheduled-onec',
            'name' => 'Регламентная 1С',
            'settings' => [
                'order_interval_minutes' => 3,
                'catalog_interval_minutes' => 8,
                'stale_after_minutes' => 20,
            ],
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
        ]);

        $source->refresh()->load('latestSuccessfulExchangeRun');

        $this->assertSame(3, $source->orderIntervalMinutes());
        $this->assertSame(8, $source->catalogIntervalMinutes());
        $this->assertSame(20, $source->staleAfterMinutes());
        $this->assertSame('Заказы 3 мин · цены/остатки 8 мин', $source->scheduleLabel());
        $this->assertSame('catalog', $source->latestSuccessfulExchangeRun->operation);
    }

    public function test_operations_summary_excludes_orders_before_integration_monitoring_started(): void
    {
        IntegrationSource::query()->create([
            'code' => 'summary-new-integration',
            'name' => 'Новая 1С',
            'is_active' => true,
            'settings' => ['monitor_orders_from' => now()->subHour()->toIso8601String()],
        ]);

        $oldOrder = Order::query()->create([
            'number' => 'SUMMARY-LEGACY-ORDER',
            'status' => 'new',
            'customer_name' => 'Старый заказ',
            'customer_phone' => '+375290000010',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ]);
        DB::table('orders')->where('id', $oldOrder->id)->update(['created_at' => now()->subHours(2)]);

        Order::query()->create([
            'number' => 'SUMMARY-CURRENT-ORDER',
            'status' => 'new',
            'customer_name' => 'Новый заказ',
            'customer_phone' => '+375290000011',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ]);

        $this->assertSame(1, app(IntegrationOperationsSummary::class)->snapshot()['awaiting_orders']);
    }

    public function test_issue_detector_deduplicates_and_auto_resolves_current_problems(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'issue-source',
            'name' => 'Поставщик с проблемами',
            'is_active' => true,
            'settings' => ['monitor_orders_from' => now()->subDay()->toIso8601String()],
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $product = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'issue-product',
            'name' => 'Проблемный товар',
            'price' => 0,
            'stock_quantity' => 2,
            'match_status' => 'unmatched',
        ]);
        $order = Order::query()->create([
            'number' => 'ISSUE-ORDER-1',
            'status' => 'new',
            'customer_name' => 'Тест',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subMinutes(20)]);

        $detector = app(IntegrationIssueDetector::class);
        $first = $detector->scan();
        $second = $detector->scan();

        $this->assertSame(2, $first['detected']);
        $this->assertCount(2, $first['opened_issue_ids']);
        $this->assertSame(2, IntegrationIssue::query()->where('status', 'open')->count());
        $this->assertSame(2, $second['detected']);
        $this->assertSame([], $second['opened_issue_ids']);
        $this->assertSame(2, IntegrationIssue::query()->count());
        $productIssue = IntegrationIssue::query()->where('type', 'product_attention')->firstOrFail();
        $this->assertTrue($productIssue->context['missing_price']);
        $this->assertTrue($productIssue->context['unmatched']);
        $this->assertTrue($productIssue->context['missing_category']);
        $this->assertDatabaseHas('integration_issues', ['type' => 'order_not_exported']);

        $product->update(['match_status' => 'ignored']);
        $order->update(['onec_exported_at' => now(), 'onec_status_received_at' => now()]);
        $resolved = $detector->scan();

        $this->assertSame(2, $resolved['resolved']);
        $this->assertSame(0, IntegrationIssue::query()->where('status', 'open')->count());
        $this->assertSame(2, IntegrationIssue::query()->where('status', 'resolved')->count());
    }

    public function test_issue_advisor_explains_product_and_order_actions_without_mutation(): void
    {
        $productIssue = new IntegrationIssue([
            'type' => 'product_attention',
            'context' => [
                'missing_price' => true,
                'unmatched' => true,
                'missing_category' => true,
            ],
        ]);
        $orderIssue = new IntegrationIssue(['type' => 'order_not_exported']);
        $advisor = app(IntegrationIssueAdvisor::class);

        $productAdvice = $advisor->advise($productIssue);
        $orderAdvice = $advisor->advise($orderIssue);

        $this->assertSame('Сначала привязать товар и получить цену', $productAdvice['title']);
        $this->assertCount(3, $productAdvice['steps']);
        $this->assertStringContainsString('не публикуется', $productAdvice['steps'][2]);
        $this->assertSame('Передать заказ в 1С', $orderAdvice['title']);
        $this->assertStringContainsString('success', $orderAdvice['note']);
        $this->assertFalse($productIssue->exists);
    }

    public function test_issue_detector_opens_and_auto_resolves_possible_identity_duplicate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'duplicate-identity-source',
            'name' => 'Источник с повторным артикулом',
            'is_active' => true,
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        $old = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'old-1c-id',
            'external_sku' => 'DUP-100',
            'name' => 'Старая позиция',
            'stock_quantity' => 0,
        ]);
        $current = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'new-1c-id',
            'external_sku' => 'DUP 100',
            'name' => 'Новая позиция',
            'stock_quantity' => 0,
        ]);

        $detector = app(IntegrationIssueDetector::class);
        $first = $detector->scan();
        $second = $detector->scan();

        $this->assertSame(1, $first['detected']);
        $this->assertSame(1, $second['detected']);
        $this->assertSame(1, IntegrationIssue::query()->possibleDuplicates()->count());
        $issue = IntegrationIssue::query()->possibleDuplicates()->firstOrFail();
        $this->assertSame([$old->id, $current->id], $issue->context['product_ids']);
        $this->assertStringContainsString('DUP-100', $issue->message);

        $current->update(['external_sku' => 'UNIQUE-200']);
        $resolved = $detector->scan();

        $this->assertSame(1, $resolved['resolved']);
        $this->assertSame(0, IntegrationIssue::query()->open()->possibleDuplicates()->count());
    }

    public function test_issue_advisor_explains_possible_identity_duplicate_without_auto_merge(): void
    {
        $issue = new IntegrationIssue(['type' => 'product_identity_collision']);

        $advice = app(IntegrationIssueAdvisor::class)->advise($issue);

        $this->assertSame('Проверить возможный дубль из 1С', $advice['title']);
        $this->assertCount(3, $advice['steps']);
        $this->assertStringContainsString('не удаляет', $advice['note']);
    }

    public function test_issue_detector_ignores_orders_created_before_integration_monitoring_started(): void
    {
        IntegrationSource::query()->create([
            'code' => 'new-integration',
            'name' => 'Новая интеграция',
            'is_active' => true,
            'settings' => ['monitor_orders_from' => now()->subHour()->toIso8601String()],
        ]);

        $order = Order::query()->create([
            'number' => 'LEGACY-ORDER-1',
            'status' => 'new',
            'customer_name' => 'Старый заказ',
            'customer_phone' => '+375290000001',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subHours(2)]);

        app(IntegrationIssueDetector::class)->scan();

        $this->assertDatabaseMissing('integration_issues', [
            'type' => 'order_not_exported',
            'order_id' => $order->id,
        ]);
    }
}
