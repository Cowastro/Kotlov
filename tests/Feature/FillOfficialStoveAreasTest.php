<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\FireplaceSpecifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillOfficialStoveAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_replaces_an_estimate_in_the_catalog_and_preserves_existing_specs(): void
    {
        $stoves = Category::create(['name' => 'Печи', 'slug' => 'pechki', 'is_active' => true]);
        $fireplaces = Category::create(['name' => 'Печи-камины', 'slug' => 'pechi-kaminy', 'parent_id' => $stoves->id, 'is_active' => true]);
        $other = Category::create(['name' => 'Прочее', 'slug' => 'other', 'is_active' => true]);
        $create = fn (Category $category, string $slug, array $specs) => Product::create([
            'category_id' => $category->id, 'slug' => $slug, 'sku' => $slug, 'name' => $slug, 'price' => 100,
            'is_active' => true, 'availability_status' => Product::AVAILABILITY_CHECK, 'specs' => $specs,
        ]);

        $volume = ['key' => 'Объём обогрева', 'value' => '150 м³'];
        $product = $create($fireplaces, 'pech-kamin-meta-bel-ritm-aot-7', [
            ['key' => 'Площадь отапливаемого помещения', 'value' => 'до 60 м² (при высоте потолка 2,5 м)'],
            $volume,
        ]);
        $known = $create($fireplaces, 'pech-kamin-meta-bel-enisej', [['key' => 'Площадь обогрева', 'value' => '60–110 м²']]);
        $moved = $create($other, 'pech-kamin-meta-bel-luara', [$volume]);
        $originalSpecs = $product->fresh()->specs;
        $knownSpecs = $known->fresh()->specs;
        $movedSpecs = $moved->fresh()->specs;

        $this->artisan('catalog:fill-official-stove-areas')
            ->expectsOutput('metric.stove_areas.filled=1')->assertSuccessful();
        $this->assertSame($originalSpecs, $product->fresh()->specs);

        $this->artisan('catalog:fill-official-stove-areas --apply')
            ->expectsOutput('metric.stove_areas.filled=1')
            ->expectsOutput('metric.stove_areas.estimates_replaced=1')
            ->expectsOutput('metric.stove_areas.already_present=1')
            ->assertSuccessful();
        $product->refresh();
        $this->assertSame(['Площадь обогрева' => '30–60 м²'], FireplaceSpecifications::catalogCard($product));
        $this->assertSame($originalSpecs, array_slice($product->specs, 1));
        $this->assertStringStartsWith('https://metabel.by/', $product->specs[0]['source_url']);
        $this->assertSame($knownSpecs, $known->fresh()->specs);
        $this->assertSame($movedSpecs, $moved->fresh()->specs);

        $this->artisan('catalog:fill-official-stove-areas --apply')
            ->expectsOutput('metric.stove_areas.filled=0')->assertSuccessful();
    }
}
