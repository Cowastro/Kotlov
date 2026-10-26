<?php

namespace Tests\Feature;

use App\Console\Commands\ApplyMaitekCatalogFiltersCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplyMaitekCatalogFiltersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_innowarm_titles_give_specs(): void
    {
        $this->assertSame([
            'Серия' => 'HP015',
            'Мощность' => '300 Вт',
            'Напряжение' => '230 В',
            'Длина' => '350 мм',
            'Цвет' => 'Хром',
            'Назначение' => 'Полотенцесушитель',
        ], ApplyMaitekCatalogFiltersCommand::innowarmSpecs('INNOWARM HP015 ТЭН для полотенцесушителя 300W/230V/L:350mm, Хром'));

        $this->assertSame([
            'Мощность' => '3 кВт',
            'Напряжение' => '220 В',
            'Длина' => '250 мм',
            'Крепление' => 'Гайка',
            'Резьба крепления' => '1 1/4"',
        ], ApplyMaitekCatalogFiltersCommand::innowarmSpecs('INNOWARM ТЭН трубчатый 3000 Вт/220В крепление гайка латунная 1 1/4" L=250мм'));

        $this->assertSame(['Мощность' => '1.5 кВт', 'Крепление' => 'Фланец', 'Форма' => 'Гнутый', 'Посадка под анод' => 'M6'],
            ApplyMaitekCatalogFiltersCommand::innowarmSpecs('INNOWARM ТЭН 1,5 кВт, фланец, гнутый, под анод М6'));
        $this->assertSame(7.5, ApplyMaitekCatalogFiltersCommand::powerKw('Электрический котел ЭВПМ-7.5 Сангай (ТЭН нерж)'));
        $this->assertSame(4.0, ApplyMaitekCatalogFiltersCommand::powerKw('СТЭН Котел "СТЭН ЭВПМ 4" 220/380'));
        $this->assertSame(4.5, ApplyMaitekCatalogFiltersCommand::powerKw('СТЭН ТЭНБ - 4,5 - G2" - Ч с колпаком'));
        $this->assertSame(4.8, ApplyMaitekCatalogFiltersCommand::powerKw('ТЭНБ-П-4.8 Сангай'));
    }

    public function test_cards_get_brand_power_and_area_filter_options(): void
    {
        $supplierId = DB::table('suppliers')->insertGetId(['code' => 'maitek-group', 'name' => 'Майтек Групп', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $boilers = Category::query()->create(['name' => 'Электрические', 'slug' => 'elektricheskie', 'is_active' => true]);
        $tens = Category::query()->create(['name' => 'ТЭНы', 'slug' => 'teny', 'is_active' => true]);
        $maker = $this->filter($boilers->id, 'Производитель', ['ЭВАН']);
        $power = $this->filter($boilers->id, 'Мощность, кВт', ['до 6 кВт', '6-12 кВт', '12-24 кВт', 'свыше 24 кВт']);
        $area = $this->filter($boilers->id, 'Обогреваемая площадь, м2', ['до 90 м2', '90-180 м2', 'свыше 180 м2']);
        $tenPower = $this->filter($tens->id, 'Мощность', ['1.5', '3.0']);

        $hotex = Brand::query()->create(['name' => 'HOTEX', 'slug' => 'hotex', 'is_active' => true]);
        $innowarm = Brand::query()->create(['name' => 'INNOWARM', 'slug' => 'innowarm', 'is_active' => true]);
        $boiler = $this->card($supplierId, $boilers->id, $hotex->id, 'Электрический котел HOTEX BE 9.5 кВт c Wi-Fi',
            [['key' => 'Мощность обогрева', 'value' => '9.5 кВт'], ['key' => 'Площадь обогрева', 'value' => '95 м2']]);
        $heater = $this->card($supplierId, $tens->id, $innowarm->id, 'INNOWARM ТЭН 1,5 кВт, гайка, гнутый, под анод М6', []);
        $towel = $this->card($supplierId, $tens->id, $innowarm->id, 'INNOWARM HP015 ТЭН для полотенцесушителя 300W/230V/L:350mm, Хром', []);

        // Saving a card already copies its specs into value attributes; the dry run adds nothing.
        $before = DB::table('product_attribute_values')->count();
        $this->artisan('catalog:maitek-filters')->assertSuccessful();
        $this->assertSame($before, DB::table('product_attribute_values')->count());

        $this->artisan('catalog:maitek-filters --apply')->assertSuccessful();

        $option = fn (Product $p, int $attribute) => DB::table('attribute_options')->where('id',
            DB::table('product_attribute_values')->where('product_id', $p->id)->where('attribute_id', $attribute)->value('option_id'))->value('name');
        $this->assertSame('HOTEX', $option($boiler, $maker));
        $this->assertSame('6-12 кВт', $option($boiler, $power));
        $this->assertSame('90-180 м2', $option($boiler, $area));
        $this->assertSame('1.5', $option($heater, $tenPower));
        $this->assertSame('0.3', $option($towel, $tenPower));
        $this->assertContains(['key' => 'Мощность', 'value' => '300 Вт'], $towel->fresh()->specs);

        // A second run adds nothing.
        $rows = DB::table('product_attribute_values')->count();
        $this->artisan('catalog:maitek-filters --apply')->assertSuccessful();
        $this->assertSame($rows, DB::table('product_attribute_values')->count());
    }

    /** @param  array<int,string>  $options */
    private function filter(int $categoryId, string $name, array $options): int
    {
        $id = DB::table('attributes')->insertGetId([
            'category_id' => $categoryId, 'group_id' => 0, 'sort_order' => 0, 'type' => 'select', 'name' => $name,
            'in_filter' => true, 'in_sort' => false, 'in_product' => false, 'in_brief' => false, 'is_comparable' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($options as $i => $option) {
            DB::table('attribute_options')->insert(['attribute_id' => $id, 'name' => $option, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function card(int $supplierId, int $categoryId, int $brandId, string $name, array $specs): Product
    {
        $product = Product::query()->create([
            'category_id' => $categoryId, 'brand_id' => $brandId, 'name' => $name,
            'slug' => 'mt-'.md5($name), 'sku' => 'MT-'.substr(md5($name), 0, 8), 'price' => 100, 'specs' => $specs, 'is_active' => true,
        ]);
        DB::table('supplier_products')->insert([
            'supplier_id' => $supplierId, 'product_id' => $product->id, 'supplier_article' => $name,
            'supplier_article_normalized' => mb_strtoupper($name), 'supplier_name' => $name, 'match_status' => 'matched',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $product;
    }
}
