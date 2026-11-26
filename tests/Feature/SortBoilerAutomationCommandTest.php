<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SortBoilerAutomationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_strays_leave_regulators_and_regulators_get_their_group(): void
    {
        $section = fn (string $slug) => Category::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => 0, 'is_active' => true]);
        $regulators = $section('regulyatory');
        $parts = $section('zapchasti-dlya-kotlov');
        $fans = $section('ventilyatory-nadduva');
        $radiator = $section('radiatornaya-armatura');
        $card = fn (string $name, Category $category) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id, 'price' => 100, 'is_active' => true]);
        $head = $card('Головка термостатическая HERZ “H” m 30x1.5', $regulators);
        $fan = $card('Вентилятор наддува KG Elektronik DPS-120 (200 м³/ч)', $parts);
        $boiler = $card('Контроллер твердотопливного котла KG Elektronik SP-35 PID WiFi (насос ЦО + вентилятор, Wi-Fi)', $regulators);
        $pump = $card('Контроллер насоса ЦО KG Elektronik CS-07.NEW.WIFI', $regulators);
        $room = $card('Комнатный термостат SALUS RT310', $regulators);
        $draught = $card('Регулятор тяги ICMA', $parts);
        $pumpUnit = $card('Блок автоматики', $regulators);
        $burners = $section('pelletnye-gorelki');
        $kipi = $card('вместе с фланцем 15-70 kW', $parts);
        $hopper = $card('Бункер для пеллет ATMOS 500 л', $parts);
        $safety = $card('Термостат предохранительный FONDITAL', $parts);

        $this->artisan('catalog:sort-boiler-automation')->expectsOutputToContain('metric.status=dry_run')->assertSuccessful();
        $this->assertSame($regulators->id, $head->fresh()->category_id);

        $this->artisan('catalog:sort-boiler-automation', ['--apply' => true])
            ->expectsOutputToContain('metric.boiler_automation.moved=4')
            ->assertSuccessful();

        $this->assertSame($radiator->id, $head->fresh()->category_id);
        $this->assertSame($fans->id, $fan->fresh()->category_id);
        $this->assertSame($regulators->id, $draught->fresh()->category_id);
        $group = fn (Product $product) => DB::table('product_attribute_values')
            ->join('attribute_options', 'attribute_options.id', '=', 'product_attribute_values.option_id')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.name', 'Назначение')->where('product_id', $product->id)->value('attribute_options.name');
        $this->assertSame('Для твердотопливных котлов', $group($boiler));
        $this->assertSame('Для насосов', $group($pump));
        $this->assertSame('Комнатные термостаты и программаторы', $group($room));
        $this->assertSame('Регуляторы тяги', $group($draught));
        $this->assertSame('Для насосов', $group($pumpUnit));
        $this->assertNull($group($head));
        $this->assertSame($burners->id, $kipi->fresh()->category_id);
        $this->assertSame('Пеллетная горелка KIPI ROTARY 15-70 кВт с фланцем', $kipi->fresh()->name);
        $this->assertSame('Бункеры для пеллет', $group($hopper));
        $this->assertSame('Запчасти для газовых котлов', $group($safety));
        $this->assertSame(['Для твердотопливных котлов', 'Для котлов с автоподачей (пеллеты, уголь)', 'Для газовых котлов'], DB::table('attribute_options')
            ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')->where('attributes.name', 'Назначение')
            ->where('attributes.category_id', $regulators->id)
            ->orderBy('attribute_options.sort_order')->limit(3)->pluck('attribute_options.name')->all());
        $this->assertTrue((bool) DB::table('attributes')->where(['category_id' => $regulators->id, 'name' => 'Назначение'])->value('in_filter'));

        $this->artisan('catalog:sort-boiler-automation', ['--apply' => true])
            ->expectsOutputToContain('metric.boiler_automation.moved=0')
            ->expectsOutputToContain('metric.boiler_automation.grouped=0')
            ->assertSuccessful();
    }
}
