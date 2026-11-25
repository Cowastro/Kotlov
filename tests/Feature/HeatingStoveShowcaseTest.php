<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatingStoveShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_heating_sections_use_current_offers_and_prioritize_available_fireplaces(): void
    {
        $hub = Category::updateOrCreate(['slug' => 'pechki'], ['name' => 'Печи для дачи', 'is_active' => true]);
        $supplier = Supplier::create(['code' => 'showcase-test', 'name' => 'Склад', 'is_active' => true]);
        $models = [
            'pechnoe-i-kaminnoe-lite' => ['chugunnaya-dvertsa' => ['Дверца чугунная', 80, 50]],
            'burzhuiki-pechi' => [
                'pech-otopitelnaya-avoska-tmf' => ['Печь отопительная Авоська ТМФ', 445, 1],
                'pech-otopitelnaya-tsar-pechi-potapyich' => ['Царь-Печи Потапыч', 297, 1],
            ],
            'pechi-kaminy' => [
                'drugaya-pech' => ['Другая печь', 900, 5],
                'pech-kamin-ecokamin-bavariya-prizmatik-pristenno-uglovoy' => ['ЭкоКамин Бавария Призматик пристенно-угловая', 3424.05, 29],
                'pech-kamin-ecokamin-bavariya-uglovaya' => ['ЭкоКамин Бавария угловая', 2054.31, 36],
                'pech-otopitelnaya-termokraft-ognivo-2k' => ['ТермоКрафт Огниво 2К', 980, 1],
                'pech-kamin-ecokamin-bavariya-optima' => ['ЭкоКамин Бавария Оптима', 2054.31, 43],
            ],
        ];
        foreach ($models as $section => $items) {
            $category = Category::updateOrCreate(['slug' => $section], ['name' => $section, 'parent_id' => $hub->id, 'is_active' => true]);
            foreach ($items as $slug => [$name, $price, $stock]) {
                $product = Product::create(['category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'sku' => $slug,
                    'price' => $price, 'in_stock' => true, 'is_active' => true]);
                $product->supplierProducts()->create(['supplier_id' => $supplier->id, 'supplier_article' => $slug,
                    'stock_quantity' => $stock, 'in_stock' => true, 'price_byn' => $price, 'last_stock_synced_at' => now()]);
            }
        }

        $hubPage = $this->get('https://kotlov.by/pechki')->assertOk();
        $hubPage->assertSeeInOrder(['id="heating-group-0"', 'Царь-Печи Потапыч', '297,00 BYN', 'В наличии: 1 шт.',
            'Авоська ТМФ', 'id="heating-group-1"', 'Огниво 2К', '980,00 BYN', 'Бавария Оптима', 'В наличии: 43 шт.'], false)
            ->assertViewHas('products', fn ($products) => $products->last()->slug === 'chugunnaya-dvertsa');
        $fireplaces = $this->get('https://kotlov.by/pechi-kaminy')->assertOk();
        $expected = array_reverse(array_slice(array_keys($models['pechi-kaminy']), 1));
        $fireplaces->assertViewHas('products', fn ($products) => $products->take(4)->pluck('slug')->all() === $expected);
        Product::where('slug', $expected[0])->update(['requires_price_confirmation' => true]);
        $this->get('https://kotlov.by/pechi-kaminy')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->first()->slug === $expected[1]);
    }
}
