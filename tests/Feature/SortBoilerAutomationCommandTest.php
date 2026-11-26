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
        $kipi->forceFill(['meta_title' => 'вместе с фланцем 15-70 kW — купить в %city%'])->save();
        $hopper = $card('Бункер для пеллет ATMOS 500 л', $parts);
        $safety = $card('Термостат предохранительный FONDITAL', $parts);
        $sensors = $section('datchiki');
        $gauges = $section('manometry-i-termometry');
        $outdoor = $card('Датчик уличной температуры (QAC 34), BAXI', $sensors);
        $cylinder = $card('Датчик температуры бойлера Buderus AS1', $sensors);
        $thermometer = $card('Термометр Watts F+R801 d=63 (10005800)', $sensors);
        $glass = $card('Термометр стеклянный технический 100 (0+150°C) СП-2П №3 НЧ', $gauges);
        $valve = $card('Кран шаровый для манометра ММ Ру 40 Ду 15хM20 (вода) ручка флажок LD', $gauges);
        $radio = $card('Радиомодуль LoRaWAN CВC-02 НОРМА', $gauges);
        $meters = $section('schetchiki-vody-i-tepla');
        $woltman = $card('Счетчик холодной воды Вольтмана турбинный WPD-i Ду 80 с импульсным выходом +датчик', $meters);
        $hot = $card('Счетчик горячей воды ETW-м-N Ду 15 Qn 1.6 с датчиком импульсов БелЦЕННЕР арт. 54405', $meters);
        $gas = $section('schetchiki-gaza');
        $hoses = $section('gazovye-shlangi');
        $kata = $card('Счётчик газа БелОМО КАТА – G6 RK – 2 (NB-IoT)', $gas);
        $sgd = $card('Счётчик газа БелОМО СГД-3Т-1-1-G4 (левый), межосевое расстояние 200 мм', $gas);
        $hose = $card('Шланг для газа ADVIXON Г-Ш 1/2"-1/2" (150см)', $gas);
        $steelHose = $card('Газовый шланг ZURFLEX DN16, гайка/штуцер, 0,6 м', $hoses);
        $insert = $card('Вставка диэлектрическая 1/2" ш/ш', $hoses);
        $mixing = $section('smesitelnye-klapany-i-uzly');
        $drainage = $section('drenajnyie');
        $fecal = $card('Насос погружной «ФЕКАЛЬНИК» 115/5', $mixing);
        $tsv = $card('Термостатический клапан Regulus TSV3 65°C', $mixing);
        $barberi = $card('Трехходовой клапан Barberi V07M250 AA', $mixing);
        $vta = $card('Термостатический смесительный клапан ESBE VTA 321(t35-60*c)', $mixing);
        $cvlit = $card('Клапан радиаторный термостат. Ду 15 (прямой) Цветлит', $radiator);
        $kit = $card('Набор термостатический угловой Royal Thermo Design 1/2"М30х1,5 (Черный)', $radiator);
        $bracket = $card('Кронштейн угловой для радиатора белый', $radiator);

        $this->artisan('catalog:sort-boiler-automation')->expectsOutputToContain('metric.status=dry_run')->assertSuccessful();
        $this->assertSame($regulators->id, $head->fresh()->category_id);

        $this->artisan('catalog:sort-boiler-automation', ['--apply' => true])
            ->expectsOutputToContain('metric.boiler_automation.moved=8')
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
        $this->assertSame('Пеллетная горелка KIPI ROTARY 15-70 кВт с фланцем — купить в %city%', $kipi->fresh()->meta_title);
        $this->assertSame('Бункеры для пеллет', $group($hopper));
        $this->assertSame('Запчасти для газовых котлов', $group($safety));
        $this->assertSame('Уличные (наружные) датчики', $group($outdoor));
        $this->assertSame('Датчики бойлера и ГВС', $group($cylinder));
        $this->assertSame($gauges->id, $thermometer->fresh()->category_id);
        $this->assertSame('Термометры стрелочные (биметаллические)', $group($thermometer));
        $this->assertSame('Термометры стеклянные', $group($glass));
        $this->assertSame('Краны для манометров', $group($valve));
        $this->assertSame($meters->id, $radio->fresh()->category_id);
        $this->assertSame('Турбинные счётчики (Вольтмана)', $group($woltman));
        $this->assertSame('Счётчики горячей воды', $group($hot));
        $this->assertSame('Радиомодули', $group($radio));
        $value = fn (string $filter, Product $product) => DB::table('product_attribute_values')
            ->join('attribute_options', 'attribute_options.id', '=', 'product_attribute_values.option_id')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.name', $filter)->where('product_id', $product->id)->value('attribute_options.name');
        $diameter = fn (Product $product) => $value('Диаметр (Ду)', $product);
        $this->assertSame('Ду 80', $diameter($woltman));
        $this->assertSame('Ду 15', $diameter($hot));
        $this->assertNull($diameter($radio));
        $this->assertSame('БелОМО КАТА', $value('Серия', $kata));
        $this->assertSame('G6', $value('Типоразмер', $kata));
        $this->assertSame('Дистанционная (NB-IoT)', $value('Передача данных', $kata));
        $this->assertSame('БелОМО СГД', $value('Серия', $sgd));
        $this->assertSame('G4', $value('Типоразмер', $sgd));
        $this->assertSame('Без дистанционной передачи', $value('Передача данных', $sgd));
        $this->assertSame($hoses->id, $hose->fresh()->category_id);
        $this->assertSame('Резиновые и полимерные', $value('Тип', $hose));
        $this->assertSame('1,2–1,5 м', $value('Длина', $hose));
        $this->assertSame('Металлические (нержавеющая сталь)', $value('Тип', $steelHose));
        $this->assertSame('До 1 м', $value('Длина', $steelHose));
        $this->assertSame('Диэлектрические вставки', $value('Тип', $insert));
        $this->assertNull($value('Длина', $insert));
        $this->assertSame($drainage->id, $fecal->fresh()->category_id);
        $this->assertSame($mixing->id, $tsv->fresh()->category_id);
        $this->assertSame('Термостатические клапаны для котлов (антиконденсат)', $value('Тип', $tsv));
        $this->assertSame('Трёхходовые клапаны', $value('Тип', $barberi));
        $this->assertSame('Термостатические смесители (ГВС, тёплый пол)', $value('Тип', $vta));
        $this->assertSame('Термостатические клапаны', $value('Тип', $cvlit));
        $this->assertSame('Прямой', $value('Исполнение', $cvlit));
        $this->assertSame('Комплекты и наборы', $value('Тип', $kit));
        $this->assertSame('Угловой', $value('Исполнение', $kit));
        $this->assertSame('Чёрный', $value('Цвет', $kit));
        $this->assertSame('Кронштейны и крепления', $value('Тип', $bracket));
        $this->assertNull($value('Исполнение', $bracket));
        $this->assertSame('Белый', $value('Цвет', $bracket));
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
