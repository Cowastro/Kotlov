<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCatalogOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('products');
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->decimal('rating', 3, 2)->default(0);
        });
    }

    public function test_default_catalog_order_keeps_priority_brand_first(): void
    {
        DB::table('products')->insert([
            ['id' => 1, 'brand_id' => 10, 'is_featured' => true, 'rating' => 5],
            ['id' => 2, 'brand_id' => 20, 'is_featured' => false, 'rating' => 3],
            ['id' => 3, 'brand_id' => 20, 'is_featured' => true, 'rating' => 4],
            ['id' => 4, 'brand_id' => 10, 'is_featured' => true, 'rating' => 4],
        ]);

        $orderedIds = Product::query()
            ->catalogDefaultOrder(20)
            ->pluck('id')
            ->all();

        $this->assertSame([3, 2, 1, 4], $orderedIds);
    }

    public function test_default_catalog_order_is_unchanged_without_priority_brand(): void
    {
        DB::table('products')->insert([
            ['id' => 1, 'brand_id' => 10, 'is_featured' => false, 'rating' => 5],
            ['id' => 2, 'brand_id' => 20, 'is_featured' => true, 'rating' => 3],
            ['id' => 3, 'brand_id' => 30, 'is_featured' => true, 'rating' => 4],
        ]);

        $orderedIds = Product::query()
            ->catalogDefaultOrder()
            ->pluck('id')
            ->all();

        $this->assertSame([3, 2, 1], $orderedIds);
    }
}
