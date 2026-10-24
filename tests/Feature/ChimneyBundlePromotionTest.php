<?php

namespace Tests\Feature;

use App\Http\Controllers\ChimneySelectorController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartCouponService;
use App\Services\ChimneyBundlePromotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChimneyBundlePromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_and_chimney_receive_separate_non_stacking_discounts(): void
    {
        [$equipment, $chimney] = $this->products();
        $cart = [
            $equipment->id => $this->cartItem($equipment, 1000),
            $chimney->id => $this->cartItem($chimney, 200),
        ];

        $quote = app(CartCouponService::class)->quoteForCart('ONLINE3', $cart);

        $this->assertTrue($quote['bundle']['active']);
        $this->assertSame(200.0, $quote['bundle']['eligible_subtotal']);
        $this->assertSame(20.0, $quote['bundle_discount']);
        $this->assertSame(30.0, $quote['coupon_discount']);
        $this->assertSame(50.0, $quote['discount']);
        $this->assertSame(1150.0, $quote['total']);
    }

    public function test_bundle_discount_remains_without_online_coupon_and_requires_both_sides(): void
    {
        [$equipment, $chimney] = $this->products();
        $service = app(CartCouponService::class);

        $equipmentOnly = $service->quoteForCart(null, [$equipment->id => $this->cartItem($equipment, 1000)]);
        $chimneyOnly = $service->quoteForCart(null, [$chimney->id => $this->cartItem($chimney, 200)]);
        $bundle = $service->quoteForCart(null, [
            $equipment->id => $this->cartItem($equipment, 1000),
            $chimney->id => $this->cartItem($chimney, 200),
        ]);

        $this->assertSame(0.0, $equipmentOnly['bundle_discount']);
        $this->assertSame(0.0, $chimneyOnly['bundle_discount']);
        $this->assertSame(20.0, $bundle['bundle_discount']);
        $this->assertSame(1180.0, $bundle['total']);
    }

    public function test_b2b_and_active_sale_chimneys_are_not_discounted(): void
    {
        [$equipment, $chimney] = $this->products();
        $service = app(ChimneyBundlePromotion::class);

        $b2b = $service->quote([
            $equipment->id => $this->cartItem($equipment, 1000),
            $chimney->id => $this->cartItem($chimney, 200, 'b2b'),
        ]);
        $chimney->update([
            'is_sale' => true,
            'sale_campaign' => 'supplier-sale',
            'sale_ends_at' => now()->addDay(),
        ]);
        $sale = $service->quote([
            $equipment->id => $this->cartItem($equipment, 1000),
            $chimney->id => $this->cartItem($chimney, 200),
        ]);

        $this->assertFalse($b2b['active']);
        $this->assertFalse($sale['active']);
    }

    public function test_selector_page_explains_parameters_discount_and_manager_check(): void
    {
        [$equipment, $chimney] = $this->products();
        $chimney->update([
            'name' => 'Труба сэндвич AISI 304 Ø115 мм',
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $tisBrand = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $darcoBrand = Brand::query()->firstOrCreate(['slug' => 'darco'], ['name' => 'DARCO']);
        $metrePipe = Product::query()->create([
            'category_id' => $chimney->category_id,
            'brand_id' => $tisBrand->id,
            'name' => 'Труба Теплов и Сухов моно ТМ-Р L1000, 430, 0.8, Ø 120',
            'slug' => 'test-l1000-430-08-d120',
            'sku' => 'TEST-L1000-430-08-D120',
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $monoCategory = Category::query()->firstOrCreate(
            ['slug' => 'truby-mono'],
            ['name' => 'Трубы моно', 'parent_id' => $chimney->category->parent_id]
        );
        $darcoPipe = Product::query()->create([
            'category_id' => $monoCategory->id,
            'brand_id' => $darcoBrand->id,
            'name' => 'DARCO Труба RP120/1000-CZ2 (ML)',
            'slug' => 'test-darco-rp120-1000-cz2',
            'sku' => 'TEST-DARCO-RP120-1000',
            'price' => 134,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $mcBlackPipe = Product::query()->create([
            'category_id' => $monoCategory->id,
            'brand_id' => $tisBrand->id,
            'name' => 'Теплов и Сухов Труба L250 ТМ-Р (430-0,8) MC Black D115',
            'slug' => 'test-tis-mc-black-l250-d115',
            'sku' => 'TEST-TIS-MCB-L250-D115',
            'price' => 80,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $tankCategory = Category::query()->create(['name' => 'Баки для воды', 'slug' => 'test-water-tanks', 'parent_id' => 0]);
        $mcBlackDamper = Product::query()->create([
            'category_id' => $tankCategory->id,
            'brand_id' => $tisBrand->id,
            'name' => "Теплов и Сухов Шибер-задвижка (430-0,8) MC\u{00A0}Black D115",
            'slug' => 'test-tis-mc-black-damper-d115',
            'sku' => 'TEST-TIS-MCB-DAMPER-D115',
            'price' => 130,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $tank = Product::query()->create([
            'category_id' => $tankCategory->id,
            'brand_id' => $tisBrand->id,
            'name' => 'Бак печной овальный ТиС Феррит 50, D115/115 (430)',
            'slug' => 'test-tis-water-tank',
            'sku' => 'TEST-TIS-TANK',
            'price' => 350,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $response = $this->get(route('chimney-selector', ['equipment' => $equipment->id]));
        $response
            ->assertOk()
            ->assertSeeText('Соберите дымоход под своё оборудование')
            ->assertSeeText('Марка стали')
            ->assertSeeText('Толщина стали трубы')
            ->assertSeeText('Материал моно-участка')
            ->assertSeeText('Чёрная сталь DARCO, 2 мм')
            ->assertSeeText('Окрашенная система «Теплов и Сухов» MC Black')
            ->assertSeeText('0,25 м')
            ->assertSee('<option value="430" selected>AISI 430</option>', false)
            ->assertSee('<option value="0.8" selected>0,8 мм</option>', false)
            ->assertSeeText('Моно + утеплённый')
            ->assertSeeText('Длина моно-участка')
            ->assertSeeText('Адаптер на печь')
            ->assertSeeText('Диаметр трассы после адаптера')
            ->assertSeeText('Для патрубка Ø130 можно выбрать адаптер на Ø120 или Ø150.')
            ->assertSee('settings.chimneyDiameter', false)
            ->assertSee('data-row-decrease', false)
            ->assertSee('data-row-increase', false)
            ->assertSee('data-row-remove', false)
            ->assertSee('"id":'.$metrePipe->id, false)
            ->assertSee('test-l1000-430-08-d120', false)
            ->assertSee('"diameter":"120"', false)
            ->assertSee('"thickness":"0.8"', false)
            ->assertSee('"length_mm":1000', false)
            ->assertSeeText('Шибер')
            ->assertSeeText('Проходы через перекрытие (ППУ)')
            ->assertSeeText('Обычные соединительные хомуты')
            ->assertSeeText('Растяжки для высокой мачты')
            ->assertSeeText('Зонт')
            ->assertSeeText('Схема и высота')
            ->assertSeeText('Угол колен для смещения')
            ->assertSeeText('Теплов и Сухов')
            ->assertSeeText('−10%')
            ->assertSeeText('Передайте схему менеджеру')
            ->assertSee('data-addon-toggle', false)
            ->assertSee('Умный поиск: теплообменник, бак, шибер…', false)
            ->assertSee('test-chimney-bundle-pipe', false);

        preg_match('/var catalog = (\[.*?\]);\s*var unlocked/s', $response->getContent(), $catalogJson);
        $catalog = collect(json_decode($catalogJson[1] ?? '[]', true));
        $darcoItem = $catalog->firstWhere('id', $darcoPipe->id);
        $mcBlackItem = $catalog->firstWhere('id', $mcBlackPipe->id);
        $mcBlackDamperItem = $catalog->firstWhere('id', $mcBlackDamper->id);
        $tankItem = $catalog->firstWhere('id', $tank->id);
        $this->assertSame('120', $darcoItem['diameter'] ?? null);
        $this->assertSame(1000, $darcoItem['length_mm'] ?? null);
        $this->assertSame('2', $darcoItem['thickness'] ?? null);
        $this->assertSame('black_steel', $darcoItem['material'] ?? null);
        $this->assertSame('darco', $darcoItem['brand_slug'] ?? null);
        $this->assertSame('mono', $darcoItem['system'] ?? null);
        $this->assertTrue($darcoItem['discount_eligible'] ?? false);
        $this->assertSame('115', $mcBlackItem['diameter'] ?? null);
        $this->assertSame(250, $mcBlackItem['length_mm'] ?? null);
        $this->assertSame('0.8', $mcBlackItem['thickness'] ?? null);
        $this->assertSame('mc_black', $mcBlackItem['material'] ?? null);
        $this->assertSame('damper', $mcBlackDamperItem['kind'] ?? null);
        $this->assertSame('mono', $mcBlackDamperItem['system'] ?? null);
        $this->assertSame('115', $mcBlackDamperItem['diameter'] ?? null);
        $this->assertTrue($tankItem['preferred'] ?? false);
        $this->assertFalse($tankItem['discount_eligible'] ?? true);
    }

    public function test_selector_distinguishes_regular_clamps_from_guy_wire_hardware(): void
    {
        $controller = app(ChimneySelectorController::class);
        $method = new \ReflectionMethod(ChimneySelectorController::class, 'kind');

        $this->assertSame('clamp', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут трубный КНТ-Р Ø180'));
        $this->assertSame('guy_wire', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут растяжки Ø180'));
        $this->assertSame('elbow', $method->invoke($controller, 'kolena-sendvich', 'Отвод Термо 45° с хомутом'));
        $this->assertSame('mount', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут штанги регулируемой ХШР d200'));
        $this->assertSame('adapter', $method->invoke($controller, 'perehody-adaptery-dymohod', 'Площадка монтажная термо ПМТ-Р D120/180'));

        $diameter = new \ReflectionMethod(ChimneySelectorController::class, 'diameterFromText');
        $this->assertSame('120', $diameter->invoke($controller, 'Шибер Darco SZK120-CZ2(ML) короткая ручка'));
        $this->assertSame('120', $diameter->invoke($controller, 'Колено поворотное Darco KNS120/45-CZ2 (ML)'));
        $this->assertSame('150', $diameter->invoke($controller, 'Колено фиксированное KS150/90-CZ2'));
        $this->assertSame('210', $diameter->invoke($controller, 'Хомут трубный на болте D210'));
    }

    /** @return array{Product, Product} */
    private function products(): array
    {
        $equipmentRoot = Category::query()->create(['name' => 'Печи', 'slug' => 'pechi', 'parent_id' => 0]);
        $equipmentCategory = Category::query()->create(['name' => 'Печи для дома', 'slug' => 'test-wood-stoves', 'parent_id' => $equipmentRoot->id]);
        $chimneyRoot = Category::query()->create(['name' => 'Дымоходы', 'slug' => 'dymohody', 'parent_id' => 0]);
        $chimneyCategory = Category::query()->create(['name' => 'Трубы сэндвич', 'slug' => 'truby-sendvich', 'parent_id' => $chimneyRoot->id]);

        $equipment = Product::query()->create([
            'category_id' => $equipmentCategory->id,
            'name' => 'Печь тестовая',
            'slug' => 'test-stove-for-chimney-bundle',
            'sku' => 'STOVE-BUNDLE',
            'price' => 1000,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'specs' => ['Диаметр дымохода' => '115 мм'],
        ]);
        $chimney = Product::query()->create([
            'category_id' => $chimneyCategory->id,
            'name' => 'Труба сэндвич Ø115 мм',
            'slug' => 'test-chimney-bundle-pipe',
            'sku' => 'CHIMNEY-BUNDLE',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        return [$equipment->load('category'), $chimney->load('category')];
    }

    private function cartItem(Product $product, float $price, string $pricingType = 'retail'): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'price' => $price,
            'quantity' => 1,
            'category_slug' => $product->category->slug,
            'pricing_type' => $pricingType,
        ];
    }
}
