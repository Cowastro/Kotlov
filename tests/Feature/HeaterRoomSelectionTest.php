<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\HeaterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeaterRoomSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug, ?Category $parent = null): Category
    {
        return Category::updateOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => $parent?->id ?? 0, 'is_active' => true]);
    }

    private function product(Category $category, string $slug, array $extra = []): Product
    {
        return Product::create($extra + ['category_id' => $category->id, 'slug' => $slug, 'sku' => $slug, 'name' => $slug,
            'price' => 100, 'is_active' => true, 'is_archived' => false, 'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK]);
    }

    public function test_room_size_is_a_capacity_threshold_not_an_area_band_or_watt_estimate(): void
    {
        $root = $this->category('obogrevateli');
        $convectors = $this->category('elektricheskie-konvektoryi', $root);
        $oil = $this->category('maslyanye-obrevateli', $root);
        $large = $this->product($oil, 'ballu-bohcl-11wrn'); // 27 m²
        $medium = $this->product($convectors, 'ballu-becevu-2000'); // 25 m²
        $range = $this->product($oil, 'maslianyi-radiator-timberk-tor-211507-bc'); // 15–20 m²
        $this->product($convectors, 'ballu-becevu-500'); // 8 m²
        $partial = $this->product($convectors, 'konvektor-elektriceskii-electrolux-echb-2000-e', ['specs' => ['Площадь обогрева' => '99 м²']]);
        $unknown = $this->product($convectors, 'unknown-heater-5kw', ['specs' => ['Мощность' => '5 кВт', 'Площадь обогрева' => '50 м²']]);
        $response = $this->get('/obogrevateli?room_area=20')->assertOk();
        $ids = $response->viewData('products')->modelKeys();
        $this->assertContains($large->id, $ids);
        $this->assertContains($medium->id, $ids);
        $this->assertContains($range->id, $ids);
        $this->assertNotContains($partial->id, $ids);
        $this->assertNotContains($unknown->id, $ids);
        $response->assertSee('Комната: 20 м²')->assertSee('Сбросить площадь')->assertSee('Сбросить все фильтры')
            ->assertSee('content="noindex, follow"', false)->assertSee('data-heater-room-picker', false);
        $this->get('/obogrevateli?room_area=25,5')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$large->id]);
        $this->get('/obogrevateli?room_area=20&q=ballu&heating[power]=to-2&heating[mount]=wall&in_stock=1')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$medium->id]);
        $this->get('/obogrevateli?room_area=1000')->assertOk()->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_room_input_is_bounded_safe_and_keeps_other_filters_when_applied_or_removed(): void
    {
        foreach (['20' => 20.0, ' 25,5 ' => 25.5, '25.50' => 25.5, '1' => 1.0, '1000' => 1000.0] as $raw => $expected) {
            $this->assertSame($expected, HeaterCatalog::roomArea((string) $raw));
        }
        foreach ([null, [], ['20'], '0', '-20', '1001', 'INF', '2e2', '<script>', '20.123'] as $raw) $this->assertNull(HeaterCatalog::roomArea($raw));
        $root = $this->category('obogrevateli');
        $this->product($root, 'ballu-becevu-2000');
        $this->get('/obogrevateli?room_area[]=20')->assertOk()->assertSee('Подбор по площади не применён.')->assertViewHas('roomArea', null);
        $this->get('/obogrevateli?room_area=20&heating=invalid')->assertOk();
        $response = $this->get('/obogrevateli?room_area=20&q=ballu&heating[mount]=wall&heating[area]=to-25&page=2')->assertOk();
        $response->assertSee('name="heating[mount]" value="wall"', false)->assertSee('name="q" value="ballu"', false);
        $html = $response->getContent();
        preg_match('~<div class="mt-16" data-heater-room-picker>(.*?)</form>~s', $html, $form);
        $this->assertStringNotContainsString('name="heating[area]"', $form[1]);
        $this->assertStringNotContainsString('name="page"', $form[1]);
        $other = $this->category('radiatory');
        $this->product($other, 'unrelated-radiator');
        $this->get('/radiatory?room_area=20')->assertOk()->assertDontSee('data-heater-room-picker', false);
    }

    public function test_descriptions_contain_only_exact_verified_facts_with_no_legacy_html_or_guessed_area(): void
    {
        $category = $this->category('elektricheskie-konvektoryi');
        $catalog = app(HeaterCatalog::class);
        $product = $this->product($category, 'royal-thermo-rchcm-500', ['name' => 'Royal Thermo RCHC/M-500',
            'short_description' => '<p>Биметаллический радиатор 1026 Вт</p>', 'content' => 'Межосевое расстояние 500 мм']);
        $copy = $catalog->description($product);
        $this->assertStringNotContainsString('<p>', $copy['short_description']);
        $this->assertStringNotContainsString('1026', $copy['content']);
        $this->assertStringNotContainsString('Межосевое', $copy['content']);
        $this->assertStringContainsString('0,5 кВт', $copy['short_description']);
        $this->assertStringContainsString('до 5 м²', $copy['short_description']);
        $this->assertStringContainsString('Паспорт производителя', $copy['content']);
        $partial = $catalog->description(new Product(['slug' => 'konvektor-elektriceskii-electrolux-echb-2000-e', 'name' => 'Electrolux ECH/B-2000 E', 'category_id' => $category->id]));
        $this->assertStringContainsString('2 кВт', $partial['short_description']);
        $this->assertStringNotContainsString('м²', $partial['short_description']);
        $unknown = $catalog->description(new Product(['slug' => 'hotta-aet-24-220-v-elektriceskii-teploakkumuliator', 'name' => 'HOTTA АЭТ-2.4', 'category_id' => $category->id]));
        $this->assertStringNotContainsString('кВт', $unknown['content']);
        $this->assertStringNotContainsString('м²', $unknown['short_description']);
    }

    public function test_cleanup_backs_up_copy_preserves_other_data_and_later_manual_edits_and_can_roll_back(): void
    {
        Storage::fake('local');
        $category = $this->category('maslyanye-obrevateli');
        $legacy = ['short_description' => '<p>до 999 м²</p>', 'content' => '<p>Старое описание</p>', 'meta_description' => '999 м²'];
        $product = $this->product($category, 'ballu-bohcl-11wrn', $legacy + ['specs' => ['Цвет' => 'Белый'], 'documents' => [['label' => 'Документ', 'url' => 'https://example.com/manual.pdf']]]);
        $edited = $this->product($category, 'ballu-bohcl-05wrn', $legacy);
        $other = $this->product($this->category('radiatory'), 'other-radiator', $legacy);
        $migration = require database_path('migrations/2026_11_12_180000_clean_verified_heater_descriptions.php');
        $migration->up();
        $product->refresh();
        $this->assertStringContainsString('до 27 м²', $product->short_description);
        $this->assertSame(['Цвет' => 'Белый'], $product->specs);
        $this->assertCount(1, $product->documents);
        $this->assertSame($legacy, $other->fresh()->only(array_keys($legacy)));
        $backup = json_decode(Storage::disk('local')->get('maintenance/heater-descriptions-2026-11-12.json'), true);
        $this->assertSame($legacy, $backup[$product->id]['original']);
        $edited->update(['short_description' => 'Поздняя ручная правка']);
        $migration->up();
        $this->assertSame('Поздняя ручная правка', $edited->fresh()->short_description);
        $migration->down();
        $this->assertSame($legacy, $product->fresh()->only(array_keys($legacy)));
        $this->assertSame('Поздняя ручная правка', $edited->fresh()->short_description);
        $migration->up();
        $this->assertStringContainsString('до 27 м²', $product->fresh()->short_description);
    }
}
