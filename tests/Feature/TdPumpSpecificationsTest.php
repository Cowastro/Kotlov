<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Support\Catalog\PumpCatalog;
use App\Support\Catalog\TdPumpSpecifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TdPumpSpecificationsTest extends TestCase
{
    use RefreshDatabase;

    private function pump(): array
    {
        $category = Category::create(['name' => 'Скважинные насосы', 'slug' => 'skvajinnye-nasosy', 'parent_id' => 0, 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'ТД Комплект', 'code' => 'td_komplekt', 'is_active' => true]);
        $product = Product::create(['name' => 'AGELESS-3-3600/87-4/28-A A&P', 'slug' => 'ageless-test', 'sku' => 'TDK-1643433', 'category_id' => $category->id, 'supplier_id' => $supplier->id, 'price' => 100, 'is_active' => true]);
        $row = ['name' => $product->name, 'vendor_code' => 'AP01A20', 'brand' => 'A&P',
            'identity_vendor_code' => 'AP01A20', 'identity_brand' => 'A&P',
            'description' => "Тип насоса: центробежный, многоступенчатый\nМощность двигателя: 1500 Вт\nМакс. высота напора: 115 м\nМакс. глубина погружения: 75 м\nМакс. производительность: 5,4 м3ч\nПрисоединительное отверстие: 1 14\nРасшифровка модели насоса на примере: AGELESS-3-210023-28\nМакс. высота напора: 999 м\nGOOD WORK - другая серия"];
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'AP01A20', 'supplier_name' => $product->name, 'match_status' => 'matched', 'raw' => $row]);

        return [$product, $offer];
    }

    public function test_supplier_specs_are_visible_with_provenance_without_model_examples_or_invented_thread(): void
    {
        [$product] = $this->pump();
        $this->get('https://kotlov.by/skvajinnye-nasosy/'.$product->slug)->assertOk()
            ->assertViewHas('pumpSupplierSpecs', fn ($facts) => $facts['Макс. высота напора'] === '115 м')
            ->assertSee('Скважинный насос для подачи воды из скважины')
            ->assertSee('Особенности модели')->assertSee('Что учесть при выборе')
            ->assertDontSee('Источник характеристик — выгрузка ТД Комплект')
            ->assertDontSee('Проверка по паспорту производителя')
            ->assertSee('1500 Вт')->assertSee('75 м')->assertSee('5,4 м³/ч')
            ->assertDontSee('999 м')->assertDontSee('Присоединительное отверстие')
            ->assertDontSee('Указаны подтверждённые параметры этого исполнения');
        $facts = (new PumpCatalog)->productFacts($product, 'skvajinnye-nasosy', []);
        $this->assertSame([], $facts['numbers']);
        $this->assertSame(['no'], $facts['options']['verified']);
    }

    public function test_identity_conflicts_and_renames_do_not_attach_supplier_facts(): void
    {
        [$product, $offer] = $this->pump();
        $raw = $offer->raw;
        $raw['identity_vendor_code'] = 'OTHER-MODEL';
        $offer->update(['raw' => $raw]);
        $this->assertSame([], TdPumpSpecifications::details($product));
        $raw['identity_vendor_code'] = 'AP01A20';
        $offer->update(['raw' => $raw]);
        $product->update(['name' => 'Different pump']);
        $this->assertSame([], TdPumpSpecifications::details($product));
    }

    public function test_external_cards_do_not_inherit_supplier_owned_specs(): void
    {
        [$product] = $this->pump();
        $product->update(['supplier_id' => null]);
        $this->assertSame([], TdPumpSpecifications::details($product));
    }

    public function test_eco_and_asilak_drainage_labels_are_visible_without_promoting_them_to_passport_data(): void
    {
        [$product, $offer] = $this->pump();
        $category = Category::create(['name' => 'Дренажные насосы', 'slug' => 'drenajnyie', 'parent_id' => 0, 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'name' => 'Насос погружной ECO DP-601']);
        $raw = $offer->raw;
        $raw['name'] = $product->name;
        $raw['description'] = "Параметры сети: 230 В, 50 Гц\nПотребляемая мощность: 600 Вт\nПроизводительность: 10000 л/ч\nМаксимальный напор: 7 м\nМаксимальная глубина погружения: 5 м\nМаксимальный размер твердыx частиц: 35 мм\nМаксимальная температура перекачиваемой воды: 35 °C\nДлина электрического кабеля: 10 м\nМасса нетто: 4,5 кг\nГарантийный срок: 12 месяцев\nНасадка для чистой воды: пример другого режима";
        $offer->update(['raw' => $raw]);
        $this->get('https://kotlov.by/drenajnyie/'.$product->slug)->assertOk()
            ->assertViewHas('pumpSupplierSpecs', fn ($facts) => $facts['Максимальный напор'] === '7 м'
                && $facts['Потребляемая мощность'] === '600 Вт' && $facts['Производительность'] === '10000 л/ч')
            ->assertSee('Дренажный насос для откачивания')
            ->assertDontSee('Источник характеристик — выгрузка ТД Комплект')
            ->assertSee('35 мм')->assertSee('10 м')->assertDontSee('пример другого режима');
        $facts = (new PumpCatalog)->productFacts($product, 'drenajnyie', []);
        $this->assertSame([], $facts['numbers']);
        $this->assertSame(['no'], $facts['options']['verified']);
    }

    public function test_other_td_pump_families_get_buyer_copy_and_only_explicit_model_facts(): void
    {
        [$product, $offer] = $this->pump();
        foreach (['tsirkulyatsionnyie', 'nasosy-dlya-kolodtsa', 'fekalnye-nasosy', 'kanalizatsionnye-nasosy', 'nasosy-povysheniya-davleniya'] as $slug) {
            $category = Category::create(['name' => $slug, 'slug' => $slug, 'parent_id' => 0, 'is_active' => true]);
            $name = 'Насос модели TEST-'.$slug;
            $product->update(['name' => $name, 'category_id' => $category->id, 'content' => '<p>Пример другой модели: 999 м</p>']);
            $raw = $offer->raw;
            $raw['name'] = $name;
            $raw['description'] = "Мощность, Вт: 750\nМаксимальный напор: 12 м\nМаксимальная производительность: 100 л/мин\nПример: другая модель\nМаксимальный напор: 999 м";
            $offer->update(['raw' => $raw]);
            $this->get('https://kotlov.by/'.$slug.'/'.$product->slug)->assertOk()
                ->assertViewHas('pumpCopy', fn ($copy) => $copy['intro'] !== '' && str_contains($copy['parameters'], '750 Вт'))
                ->assertSee('Особенности модели')->assertSee('12 м')
                ->assertDontSee('999 м')->assertDontSee('выгрузка ТД Комплект');
        }
    }

    public function test_speed_values_are_not_promoted_to_maxima_and_empty_source_does_not_decode_the_title(): void
    {
        [$product, $offer] = $this->pump();
        $category = Category::create(['name' => 'Циркуляционные', 'slug' => 'tsirkulyatsionnyie', 'parent_id' => 0, 'is_active' => true]);
        $product->update(['name' => 'Насос NOC 65/12 DM', 'category_id' => $category->id]);
        $raw = $offer->raw;
        $raw['name'] = $product->name;
        $raw['description'] = "Мощность, Вт: III 100, II 70, I 55\nНапор (высота подъема), м: III 6, II 5, I 3.5";
        $offer->update(['raw' => $raw]);
        $this->get('https://kotlov.by/tsirkulyatsionnyie/'.$product->slug)->assertOk()
            ->assertSee('III 100, II 70, I 55')
            ->assertViewHas('pumpCopy', fn ($copy) => $copy['parameters'] === '');
        $raw['description'] = '';
        $offer->update(['raw' => $raw]);
        $this->get('https://kotlov.by/tsirkulyatsionnyie/'.$product->slug)->assertOk()
            ->assertViewHas('pumpCopy', fn ($copy) => $copy['parameters'] === '')
            ->assertDontSee('id="specifications"', false);
    }

    public function test_reviewed_external_wilo_uses_manufacturer_facts_instead_of_unsupported_editorial_promises(): void
    {
        [$product, $offer] = $this->pump();
        $category = Category::create(['name' => 'Циркуляционные', 'slug' => 'tsirkulyatsionnyie', 'parent_id' => 0, 'is_active' => true]);
        $product->update(['name' => 'Насос WILO atmos PICO 25/1-6', 'category_id' => $category->id,
            'supplier_id' => null, 'content' => '<p>Обещание бесперебойной работы и универсальное применение для ГВС</p>']);
        $raw = $offer->raw;
        $raw['name'] = 'WILO Atmos PICO 25/1-6';
        $raw['vendor_code'] = $raw['identity_vendor_code'] = '4232694';
        $raw['brand'] = $raw['identity_brand'] = 'WILO';
        $raw['description'] = 'Максимальный напор: 999 м';
        $offer->update(['supplier_article' => '4232694', 'raw' => $raw]);
        $this->get('https://kotlov.by/tsirkulyatsionnyie/'.$product->slug)->assertOk()
            ->assertSee('6,1 м')->assertSee('180 мм')
            ->assertDontSee('999 м')->assertDontSee('Обещание бесперебойной работы')->assertDontSee('универсальное применение для ГВС');
    }
}
