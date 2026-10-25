<?php

namespace Tests\Feature;

use App\Console\Commands\FillSpecsFromNameCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FillSpecsFromNameCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_cable_wellhead_and_hose_specs_from_the_name(): void
    {
        $command = new FillSpecsFromNameCommand;

        $this->assertSame([
            'Марка кабеля' => 'КВВ-П', 'Тип кабеля' => 'Плоский', 'Количество жил, шт' => '4', 'Сечение жилы, мм²' => '1.5', 'Длина бухты, м' => '50',
        ], $command->specs('Кабель КВВ-п 4*1,5 (бухта 50 м)'));
        $this->assertSame([
            'Диаметр обсадной трубы, мм' => '152', 'Диаметр водоподъёмной трубы, мм' => '40', 'Материал корпуса' => 'Чугун',
        ], $command->specs('Оголовок скважинный АКВАРОБОТ АОС 152-40 (с) чугун'));
        $this->assertSame(['Диаметр обсадной трубы, мм' => '133', 'Диаметр водоподъёмной трубы, мм' => '40'], $command->specs('Оголовок скважинный АКВАРОБОТ АОС 133-40'));
        $this->assertSame(['Тип шланга' => 'Растягивающийся (телескопический)', 'Длина, м' => '15'], $command->specs('Шланг поливочный ROLL TELESCOPE 15 м'));
        $this->assertSame([], $command->specs('Реле давления UNIPUMP PM5'));
    }

    public function test_only_cards_without_specs_are_filled(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'komplektuyushchie-dlya-nasosov'], ['name' => 'Комплектующие для насосов', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'UNIPUMP', 'slug' => 'unipump', 'is_active' => true]);
        $cable = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true, 'price' => 90, 'name' => 'Кабель КВВ 3*1,5 (бухта 100 м)', 'slug' => 'kvv-3-15']);

        $this->artisan('catalog:fill-specs-from-name', ['--brand' => 'unipump'])->assertSuccessful();
        $this->assertSame(0, DB::table('product_attribute_values')->where('product_id', $cable->id)->count());

        $this->artisan('catalog:fill-specs-from-name', ['--brand' => 'unipump', '--apply' => true])->assertSuccessful();
        $this->assertSame(5, DB::table('product_attribute_values')->where('product_id', $cable->id)->count());

        $this->artisan('catalog:fill-specs-from-name', ['--brand' => 'unipump', '--apply' => true])
            ->expectsOutputToContain('metric.name_specs.cards=0')
            ->assertSuccessful();
    }
}
