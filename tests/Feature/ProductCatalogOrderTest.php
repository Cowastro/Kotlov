<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogOrderProduct extends Product
{
    protected $table = 'catalog_order_products';
}

class ProductCatalogOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('catalog_order_products');
        Schema::create('catalog_order_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('in_stock')->default(false);
            $table->string('availability_status')->nullable();
            $table->decimal('price', 12, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->decimal('rating', 3, 2)->default(0);
        });

        Schema::dropIfExists('catalog_order_attribute_values');
        Schema::create('catalog_order_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('option_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('catalog_order_attribute_values');
        Schema::dropIfExists('catalog_order_products');

        parent::tearDown();
    }

    public function test_default_catalog_order_keeps_priority_brand_first(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'brand_id' => 10, 'is_featured' => true, 'rating' => 5],
            ['id' => 2, 'brand_id' => 20, 'is_featured' => false, 'rating' => 3],
            ['id' => 3, 'brand_id' => 20, 'is_featured' => true, 'rating' => 4],
            ['id' => 4, 'brand_id' => 10, 'is_featured' => true, 'rating' => 4],
        ]);

        $orderedIds = CatalogOrderProduct::query()
            ->catalogDefaultOrder(20)
            ->pluck('id')
            ->all();

        $this->assertSame([3, 2, 1, 4], $orderedIds);
    }

    public function test_orderable_scope_keeps_legacy_in_stock_products_with_empty_status(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'in_stock' => true, 'availability_status' => null],
            ['id' => 2, 'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK],
            ['id' => 3, 'in_stock' => false, 'availability_status' => Product::AVAILABILITY_CHECK],
            ['id' => 4, 'in_stock' => true, 'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK],
            ['id' => 5, 'in_stock' => false, 'availability_status' => null],
        ]);

        $this->assertSame(
            [1, 2, 3],
            CatalogOrderProduct::query()->orderable()->orderBy('id')->pluck('id')->all()
        );
    }

    public function test_default_catalog_order_is_unchanged_without_priority_brand(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'brand_id' => 10, 'is_featured' => false, 'rating' => 5],
            ['id' => 2, 'brand_id' => 20, 'is_featured' => true, 'rating' => 3],
            ['id' => 3, 'brand_id' => 30, 'is_featured' => true, 'rating' => 4],
        ]);

        $orderedIds = CatalogOrderProduct::query()
            ->catalogDefaultOrder()
            ->pluck('id')
            ->all();

        $this->assertSame([3, 2, 1], $orderedIds);
    }

    public function test_default_catalog_order_can_prioritize_available_products(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'brand_id' => 10, 'in_stock' => false, 'is_featured' => true, 'rating' => 5],
            ['id' => 2, 'brand_id' => 20, 'in_stock' => true, 'is_featured' => false, 'rating' => 3],
            ['id' => 3, 'brand_id' => 30, 'in_stock' => true, 'is_featured' => true, 'rating' => 4],
        ]);

        $orderedIds = CatalogOrderProduct::query()
            ->catalogDefaultOrder(null, true)
            ->pluck('id')
            ->all();

        $this->assertSame([3, 2, 1], $orderedIds);
    }

    public function test_default_catalog_order_combines_priority_brand_and_availability(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'brand_id' => 10, 'in_stock' => true, 'is_featured' => true, 'rating' => 5],
            ['id' => 2, 'brand_id' => 20, 'in_stock' => false, 'is_featured' => true, 'rating' => 5],
            ['id' => 3, 'brand_id' => 20, 'in_stock' => true, 'is_featured' => false, 'rating' => 3],
            ['id' => 4, 'brand_id' => 20, 'in_stock' => true, 'is_featured' => true, 'rating' => 4],
        ]);

        $orderedIds = CatalogOrderProduct::query()
            ->catalogDefaultOrder(20, true)
            ->pluck('id')
            ->all();

        $this->assertSame([4, 3, 2, 1], $orderedIds);
    }

    public function test_attribute_option_priority_keeps_residential_products_before_industrial_products(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'brand_id' => 10, 'in_stock' => true, 'is_featured' => true, 'rating' => 5],
            ['id' => 2, 'brand_id' => 20, 'in_stock' => true, 'is_featured' => false, 'rating' => 3],
            ['id' => 3, 'brand_id' => 30, 'in_stock' => true, 'is_featured' => false, 'rating' => 4],
        ]);
        DB::table('catalog_order_attribute_values')->insert([
            ['product_id' => 2, 'option_id' => 1367],
            ['product_id' => 3, 'option_id' => 1368],
        ]);

        $orderedIds = CatalogOrderProduct::query()
            ->prioritizeAttributeOptions([1367, 1368], 'catalog_order_attribute_values')
            ->catalogDefaultOrder(null, true)
            ->pluck('id')
            ->all();

        $this->assertSame([3, 2, 1], $orderedIds);
    }

    public function test_product_name_priority_keeps_stoves_before_unrelated_accessories(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'name' => 'Костровая чаша', 'in_stock' => true, 'is_featured' => true, 'rating' => 5],
            ['id' => 2, 'name' => 'Печь-камин для дома', 'in_stock' => true, 'is_featured' => false, 'rating' => 3],
            ['id' => 3, 'name' => 'Плита на твердом топливе', 'in_stock' => true, 'is_featured' => false, 'rating' => 4],
        ]);

        $orderedIds = CatalogOrderProduct::query()
            ->prioritizeNamePatterns(['%Печь%', '%печь%', '%Плита на твердом топливе%'])
            ->catalogDefaultOrder()
            ->pluck('id')
            ->all();

        $this->assertSame([3, 2, 1], $orderedIds);
    }

    public function test_accessory_categories_are_moved_below_main_equipment(): void
    {
        DB::table('catalog_order_products')->insert([
            ['id' => 1, 'category_id' => 90, 'is_featured' => false, 'rating' => 3],
            ['id' => 2, 'category_id' => 128, 'is_featured' => true, 'rating' => 5],
            ['id' => 3, 'category_id' => 104, 'is_featured' => false, 'rating' => 4],
        ]);

        $orderedIds = CatalogOrderProduct::query()
            ->deprioritizeCategories([128])
            ->catalogDefaultOrder()
            ->pluck('id')
            ->all();

        $this->assertSame([3, 1, 2], $orderedIds);
    }

    public function test_kotlov_heat_pump_catalog_meta_uses_product_specs(): void
    {
        $product = new Product([
            'specs' => [
                ['key' => 'Хладагент', 'value' => 'R290'],
                ['key' => 'Мощность', 'value' => '12,8 кВт'],
                ['key' => 'Температура воды', 'value' => 'ГВС до 80°C, отопление до 75°C'],
            ],
        ]);
        $product->setRelation('category', new Category(['slug' => 'teplovyie-nasosyi']));
        $product->setRelation('brand', new Brand(['slug' => 'kotlov-ge']));

        $this->assertSame([
            'chips' => ['R290', '12,8 кВт', 'до 75 °C'],
            'purpose' => 'Радиаторы и горячая вода',
        ], $product->heatPumpCatalogMeta());
    }

    public function test_catalog_meta_is_not_added_to_other_brands(): void
    {
        $product = new Product([
            'specs' => [
                ['key' => 'Хладагент', 'value' => 'R32'],
            ],
        ]);
        $product->setRelation('category', new Category(['slug' => 'teplovyie-nasosyi']));
        $product->setRelation('brand', new Brand(['slug' => 'other-brand']));

        $this->assertNull($product->heatPumpCatalogMeta());
    }
}
