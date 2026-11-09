<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrichUnivatOfficialCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_get_official_photos_characteristics_description_and_filters(): void
    {
        $category = Category::query()->create(['name' => 'Электрические котлы', 'slug' => 'univat-test-electric', 'is_active' => true]);
        $power = DB::table('attributes')->insertGetId(['category_id' => $category->id, 'type' => 'value', 'name' => 'Мощность', 'suffix' => 'кВт', 'in_filter' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $area = DB::table('attributes')->insertGetId(['category_id' => $category->id, 'type' => 'value', 'name' => 'Площадь обогрева', 'suffix' => 'кв.м.', 'in_filter' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $mount = DB::table('attributes')->insertGetId(['category_id' => $category->id, 'type' => 'select', 'name' => 'Установка', 'in_filter' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $wall = DB::table('attribute_options')->insertGetId(['attribute_id' => $mount, 'name' => 'настенный', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $voltage = DB::table('attributes')->insertGetId(['category_id' => $category->id, 'type' => 'select', 'name' => 'Напряжение В', 'in_filter' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $one = DB::table('attribute_options')->insertGetId(['attribute_id' => $voltage, 'name' => 'однофазная (220/230)', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attribute_options')->insert(['attribute_id' => $voltage, 'name' => 'трехфазная (380/400)', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $donor = $this->card($category, 'univat-econom-9-400', 'UNIVAT Econom 9-400', ['img/products/old.jpg']);
        DB::table('product_attribute_values')->insert(['product_id' => $donor->id, 'attribute_id' => $mount, 'option_id' => $wall, 'created_at' => now(), 'updated_at' => now()]);
        $small = $this->card($category, 'univat-econom-45-230400', 'UNIVAT Econom 4,5-230/400', []);

        $this->artisan('catalog:enrich-univat-official')
            ->expectsOutputToContain('metric.univat_official.found=2')
            ->assertSuccessful();
        $this->assertSame([], $small->fresh()->imagePaths());

        $this->artisan('catalog:enrich-univat-official --apply')
            ->expectsOutputToContain('metric.univat_official.updated=2')
            ->expectsOutputToContain('metric.univat_official.missing=30')
            ->assertSuccessful();

        $small = $small->fresh();
        $this->assertSame('img/products/univat/econom/univat-econom-1.webp', $small->imagePaths()[0]);
        $this->assertCount(5, $small->imagePaths());
        $this->assertCount(38, $small->specs);
        $specs = collect($small->specs)->pluck('value', 'key');
        $this->assertSame('4,5 кВт', $specs['Номинальная мощность']);
        $this->assertSame('230 В — 2,5 мм², 400 В — 2,5 мм²', $specs['Сечение медного кабеля питания']);
        $this->assertStringContainsString('<h2>Подключение к сети</h2>', $small->content);
        $this->assertStringNotContainsString('комплектующее', $small->content);

        $values = DB::table('product_attribute_values')->where('product_id', $small->id)->get()->keyBy('attribute_id');
        $this->assertSame('4.5', $values[$power]->value);
        $this->assertSame('45', $values[$area]->value);
        $this->assertSame($wall, (int) $values[$mount]->option_id);
        $this->assertSame($one, (int) $values[$voltage]->option_id);
    }

    private function card(Category $category, string $slug, string $name, array $images): Product
    {
        return Product::query()->create([
            'category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'sku' => strtoupper($slug),
            'price' => 700, 'is_active' => true, 'is_archived' => false, 'images' => $images,
            'content' => 'UNIVAT — комплектующее для инженерной системы.',
        ]);
    }
}
