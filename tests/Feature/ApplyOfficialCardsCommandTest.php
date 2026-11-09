<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplyOfficialCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_dataset_fills_cards_and_numeric_filters(): void
    {
        $data = json_decode((string) file_get_contents(resource_path('data/official/sten-2026-11.json')), true);
        $boiler = collect($data)->firstWhere('slug', 'elektricheskiy-kotel-sten-standart-plus-6');
        $this->assertNotNull($boiler);
        $tenko = json_decode((string) file_get_contents(resource_path('data/official/tenko-2026-11.json')), true);
        $convector = collect($tenko)->firstWhere('slug', 'konvektor-elektricheskiy-tenko-enkh-1000');
        $ten = collect($tenko)->firstWhere('slug', 'ten-trubcatyi-9000-vt380v-kreplenie-gaika-latunnaia-2-l420mm');

        $boilers = Category::query()->create(['name' => 'Электрокотлы', 'slug' => 'official-test-boilers', 'is_active' => true]);
        $power = DB::table('attributes')->insertGetId(['category_id' => $boilers->id, 'type' => 'value', 'name' => 'Мощность', 'suffix' => 'кВт', 'created_at' => now(), 'updated_at' => now()]);
        $area = DB::table('attributes')->insertGetId(['category_id' => $boilers->id, 'type' => 'value', 'name' => 'Площадь обогрева', 'suffix' => 'кв.м.', 'created_at' => now(), 'updated_at' => now()]);
        $convectors = Category::query()->create(['name' => 'Конвекторы', 'slug' => 'official-test-convectors', 'is_active' => true]);
        $watts = DB::table('attributes')->insertGetId(['category_id' => $convectors->id, 'type' => 'value', 'name' => 'Мощность', 'suffix' => 'Вт', 'created_at' => now(), 'updated_at' => now()]);

        $card = fn (Category $c, string $slug, array $images) => Product::query()->create([
            'category_id' => $c->id, 'name' => $slug, 'slug' => $slug, 'price' => 100, 'is_active' => true, 'is_archived' => false,
            'images' => $images, 'content' => 'старое',
        ]);
        $b = $card($boilers, $boiler['slug'], ['img/products/bania/old.png', 'img/products/manual-fix/p1-manual.jpg']);
        $c = $card($convectors, $convector['slug'], []);
        $t = $card($boilers, $ten['slug'], ['img/products/source-enrichment/thermokraft-1.jpg']);

        $this->artisan('catalog:apply-official-cards sten-2026-11 --apply')->assertSuccessful();
        $this->artisan('catalog:apply-official-cards tenko-2026-11 --apply')->assertSuccessful();

        $b = $b->fresh();
        $this->assertSame([...$boiler['images'], 'img/products/manual-fix/p1-manual.jpg'], $b->imagePaths());
        $this->assertCount(count($boiler['specs']), $b->specs);
        $this->assertStringContainsString('<h2>Технические данные</h2>', $b->content);
        $this->assertSame('6', DB::table('product_attribute_values')->where('product_id', $b->id)->where('attribute_id', $power)->value('value'));
        $this->assertSame('60', DB::table('product_attribute_values')->where('product_id', $b->id)->where('attribute_id', $area)->value('value'));
        $this->assertSame('1000', DB::table('product_attribute_values')->where('product_id', $c->id)->where('attribute_id', $watts)->value('value'));
        $this->assertSame(['img/products/source-enrichment/thermokraft-1.jpg'], $t->fresh()->imagePaths());
        $this->assertSame('9', DB::table('product_attribute_values')->where('product_id', $t->id)->where('attribute_id', $power)->value('value'));
    }
}
