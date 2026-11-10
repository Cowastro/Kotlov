<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\FireplaceSpecifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillOfficialFireplaceSpecsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_missing_fields_are_saved_and_the_import_can_be_repeated(): void
    {
        $category = Category::create(['name' => 'Печи-камины', 'slug' => 'pechi-kaminy', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Другая категория', 'slug' => 'other', 'is_active' => true]);
        $existing = [
            ['key' => 'Номинальная мощность', 'value' => '12', 'unit' => 'кВт'],
            ['key' => 'Диаметр дымохода', 'value' => '120', 'unit' => 'мм'],
        ];
        $product = Product::create([
            'category_id' => $category->id, 'slug' => 'fireway-pec-otopitelno-varocnaia-dacha-ii',
            'sku' => 'FIREWAY-TEST', 'name' => 'Fireway Dacha II', 'price' => 100,
            'is_active' => true, 'availability_status' => Product::AVAILABILITY_CHECK, 'specs' => $existing,
        ]);
        $other = Product::create([
            'category_id' => $otherCategory->id, 'slug' => 'pech-kamin-meta-bel-ritm-aot-7',
            'sku' => 'META-TEST', 'name' => 'Мета-Бел Ритм', 'price' => 100,
            'is_active' => true, 'availability_status' => Product::AVAILABILITY_CHECK, 'specs' => [],
        ]);

        $this->artisan('catalog:fill-official-fireplace-specs')->expectsOutput('metric.fireplace_specs.fields=1')->assertSuccessful();
        $this->assertSame($existing, $product->fresh()->specs);

        $this->artisan('catalog:fill-official-fireplace-specs --apply')->expectsOutput('metric.fireplace_specs.fields=1')->assertSuccessful();
        $product->refresh();
        $this->assertSame(['Мощность' => '12 кВт', 'Материал' => 'Чугун'], FireplaceSpecifications::card($product));
        $this->assertSame($existing, array_slice($product->specs, 1));
        $this->assertSame('https://fireway.pro/dacha-ll.html', $product->specs[0]['source_url']);
        $this->assertSame([], $other->fresh()->specs);

        $this->artisan('catalog:fill-official-fireplace-specs --apply')->expectsOutput('metric.fireplace_specs.fields=0')->assertSuccessful();
        $this->assertSame($product->specs, $product->fresh()->specs);
    }
}
