<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\User;
use App\Services\B2bAnalogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bAnalogsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
    }

    private function card(Category $category, string $name, float $price, ?Brand $brand = null): Product
    {
        static $n = 0;
        $n++;

        return Product::create(['category_id' => $category->id, 'brand_id' => $brand?->id, 'name' => $name, 'slug' => 'analog-'.$n, 'price' => $price, 'is_active' => true, 'is_archived' => false]);
    }

    public function test_a_chimney_part_gets_the_same_element_and_diameter_closest_first(): void
    {
        $category = Category::create(['name' => 'Отводы аналоги', 'slug' => 'analog-elbows', 'is_active' => true]);
        $source = $this->card($category, 'Отвод Моно Аналогов ОМ-Р 45°, 430-0,8, Ø 150', 50);
        $best = $this->card($category, 'Отвод Моно Другов ОМ-Р 45°, 430-0,8, Ø 150', 55);
        $thinner = $this->card($category, 'Отвод Моно Другов ОМ-Р 45°, 430-0,5, Ø 150', 40);
        $darco = $this->card($category, 'Колено Другов Моно КМ 45°, AISI 430 0,8, Ø 150', 60);
        $otherAngle = $this->card($category, 'Отвод Моно Другов ОМ-Р 87°, 430-0,8, Ø 150', 50);
        $this->card($category, 'Отвод Моно Другов ОМ-Р 45°, 430-0,8, Ø 120', 45);
        $this->card($category, 'Тройник Моно Другов ТРМ-Р 87°, 430-0,8, Ø 150', 70);
        $this->card($category, 'Отвод Термо Другов ОТ-Р 45°, 430-0,8/430, Ø 150/210', 150);

        $rows = $this->actingAs($this->manager())->getJson(route('b2b.workspace.analogs', $source))->assertOk()->json('items');
        $ids = array_column($rows, 'id');

        $this->assertSame($best->id, $ids[0]);
        $this->assertSame(['сталь', 'толщина', 'угол', 'покрытие'], $rows[0]['matches']);
        // «Колено» is the same element as «Отвод»; another diameter, element or system is no analog.
        $this->assertEqualsCanonicalizing([$best->id, $thinner->id, $darco->id, $otherAngle->id], $ids);
        $this->assertSame($otherAngle->id, end($ids));
    }

    public function test_any_section_gets_items_of_the_same_power_and_attributes(): void
    {
        $category = Category::create(['name' => 'Котлы аналоги', 'slug' => 'analog-boilers', 'is_active' => true]);
        $fuel = Attribute::create(['category_id' => $category->id, 'name' => 'Тип топлива', 'type' => 'select', 'in_filter' => true]);
        $wood = AttributeOption::create(['attribute_id' => $fuel->id, 'name' => 'Дрова']);
        $pellet = AttributeOption::create(['attribute_id' => $fuel->id, 'name' => 'Пеллеты']);
        $brand = Brand::create(['name' => 'Аналог-Бренд', 'slug' => 'analog-brand']);
        $fuelOf = fn (Product $product, AttributeOption $option) => ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $fuel->id, 'option_id' => $option->id]);
        $source = $this->card($category, 'Котёл твердотопливный Аналог 25 кВт', 3000, $brand);
        $fuelOf($source, $wood);
        $same = $this->card($category, 'Котёл твердотопливный Другой 25 кВт', 3200);
        $fuelOf($same, $wood);
        $near = $this->card($category, 'Котёл твердотопливный Третий 22 кВт', 2800);
        $fuelOf($near, $pellet);
        $this->card($category, 'Котёл твердотопливный Большой 40 кВт', 3100);
        $this->card($category, 'Котёл твердотопливный Малый 12 кВт', 1500);

        $rows = $this->actingAs($this->manager())->getJson(route('b2b.workspace.analogs', $source))->assertOk()->json('items');

        // ±30 % of the power at most; the same fuel ranks first.
        $this->assertSame([$same->id, $near->id], array_column($rows, 'id'));
        $this->assertSame(['кВт', 'тип топлива'], $rows[0]['matches']);
        $this->assertSame(['кВт ≈'], $rows[1]['matches']);
    }

    public function test_a_size_kept_in_the_card_attributes_counts_like_one_in_the_name(): void
    {
        $category = Category::create(['name' => 'Бойлеры аналоги', 'slug' => 'analog-boilers-water', 'is_active' => true]);
        $volume = Attribute::create(['category_id' => $category->id, 'name' => 'Объём, л', 'type' => 'value', 'in_product' => true]);
        $volumeOf = fn (Product $product, string $value) => ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $volume->id, 'value' => $value]);
        $source = $this->card($category, 'Бойлер косвенного нагрева AQUASTAR INOX 100 напольный', 900);
        $volumeOf($source, '100');
        $same = $this->card($category, 'Водонагреватель косвенный Drazice OKC 100 NTR', 950);
        $volumeOf($same, '100');
        $unknown = $this->card($category, 'Водонагреватель косвенный Другой', 900);
        $volumeOf($this->card($category, 'Водонагреватель косвенный Drazice OKC 250 NTR', 1500), '250');

        $rows = $this->actingAs($this->manager())->getJson(route('b2b.workspace.analogs', $source))->assertOk()->json('items');

        // 250 л is another boiler; one of unknown volume comes after the same volume.
        $this->assertSame([$same->id, $unknown->id], array_column($rows, 'id'));
        $this->assertSame(['объём, л'], $rows[0]['matches']);
    }

    public function test_units_are_read_from_names(): void
    {
        $analogs = app(B2bAnalogs::class);
        $this->assertSame(['квт' => 25.0], $analogs->units('Котёл Atmos DC25GS 25 кВт'));
        $this->assertSame(['л' => 100.0, 'квт' => 1.5], $analogs->units('Бойлер Drazice OKC 100 л, ТЭН 1,5 kW'));
        $this->assertSame(['вт' => 2000.0], $analogs->units('Конвектор Ballu BEC/EVU-2000 2000 Вт'));
        $this->assertSame([], $analogs->units('Отвод Моно ОМ-Р 45°, 430-0,8, Ø 150'));
        $this->assertSame(['секц' => 4.0], $analogs->units('Радиатор биметаллический Royal Thermo MONOBLOCK B 500 - 4 секц.'));
    }

    public function test_only_the_staff_workspace_sees_analogs(): void
    {
        $category = Category::create(['name' => 'Разное', 'slug' => 'analog-misc', 'is_active' => true]);
        $product = $this->card($category, 'Насос Аналог 25-60', 300);
        $this->getJson(route('b2b.workspace.analogs', $product))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]))->getJson(route('b2b.workspace.analogs', $product))->assertForbidden();
        $this->actingAs($this->manager())->get(route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference', 'q' => 'Насос Аналог']))->assertOk()
            ->assertSee('b2b-analogs-open', false)->assertSee('b2b-analogs.js', false);
    }
}
