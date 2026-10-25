<?php

namespace Tests\Feature;

use App\Http\Controllers\ChimneySelectorController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\CartCouponService;
use App\Services\ChimneyBundlePromotion;
use App\Support\KnowledgeBase;
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

    public function test_self_selection_gets_five_percent_and_equipment_upgrades_it_to_ten(): void
    {
        [$equipment, $chimney] = $this->products();
        $service = app(CartCouponService::class);

        $equipmentOnly = $service->quoteForCart(null, [$equipment->id => $this->cartItem($equipment, 1000)]);
        $chimneyOnly = $service->quoteForCart(null, [$chimney->id => $this->cartItem($chimney, 200)]);
        $selfSelectedItem = $this->cartItem($chimney, 200) + ['chimney_selector_selected' => true];
        $selfSelected = $service->quoteForCart('ONLINE3', [$chimney->id => $selfSelectedItem]);
        $bundle = $service->quoteForCart(null, [
            $equipment->id => $this->cartItem($equipment, 1000),
            $chimney->id => $selfSelectedItem,
        ]);

        $this->assertSame(0.0, $equipmentOnly['bundle_discount']);
        $this->assertSame(0.0, $chimneyOnly['bundle_discount']);
        $this->assertSame('self_selection', $selfSelected['bundle']['mode']);
        $this->assertSame(5.0, $selfSelected['bundle']['percent']);
        $this->assertSame(10.0, $selfSelected['bundle_discount']);
        $this->assertSame(0.0, $selfSelected['coupon_discount']);
        $this->assertSame(190.0, $selfSelected['total']);
        $this->assertSame('equipment_bundle', $bundle['bundle']['mode']);
        $this->assertSame(10.0, $bundle['bundle']['percent']);
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
        $firewayBrand = Brand::query()->firstOrCreate(['slug' => 'fireway'], ['name' => 'Fireway']);
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
        $heatExchanger = Product::query()->create([
            'category_id' => $tankCategory->id,
            'brand_id' => $tisBrand->id,
            'name' => 'Теплообменник-регистр Теплов и Сухов D115',
            'slug' => 'test-tis-heat-exchanger-d115',
            'sku' => 'TEST-TIS-HEAT-EXCHANGER',
            'price' => 290,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $masterFlash = Product::query()->create([
            'category_id' => $chimney->category_id,
            'brand_id' => $firewayBrand->id,
            'name' => 'Master Flash №8 прямой (178–330 мм) Fireway',
            'slug' => 'test-fireway-master-flash-8',
            'sku' => 'TEST-FIREWAY-MASTER-FLASH-8',
            'price' => 85,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $adjustableMount = Product::query()->create([
            'category_id' => $chimney->category_id,
            'brand_id' => $tisBrand->id,
            'name' => 'Крепление регулируемое Теплов и Сухов КР 1000',
            'slug' => 'test-adjustable-wall-mount-1000',
            'sku' => 'TEST-WALL-MOUNT-1000',
            'price' => 120,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $response = $this->get(route('chimney-selector', ['equipment' => $equipment->id]));
        $response
            ->assertOk()
            ->assertSeeText('Соберите дымоход под своё оборудование')
            ->assertSeeText('за самостоятельный подбор комплекта')
            ->assertSeeText('Бесплатно проверить')
            ->assertSee('data-bs-target="#chimneyCheckModal"', false)
            ->assertSee('data-cs-request-feedback', false)
            ->assertSeeText('Создать заявку на проверку')
            ->assertSee("fetch(managerForm.action", false)
            ->assertSeeText('Марка стали')
            ->assertSeeText('Толщина стали трубы')
            ->assertSeeText('Материал моно-участка')
            ->assertSeeText('Чёрная сталь DARCO, 2 мм')
            ->assertSeeText('Окрашенная система «Теплов и Сухов» MC Black')
            ->assertSeeText('0,25 м')
            ->assertSee('<option value="auto" selected>Рекомендовать автоматически</option>', false)
            ->assertSeeText('Моно + утеплённый')
            ->assertSeeText('Длина моно-участка')
            ->assertSeeText('Адаптер на печь')
            ->assertSeeText('Диаметр трассы после адаптера')
            ->assertSeeText('Нагрев воды')
            ->assertSeeText('Бак на трубе')
            ->assertSeeText('Теплообменник / регистр для выносного бака')
            ->assertSeeText('альтернативные решения')
            ->assertSeeText('Для патрубка Ø130 можно выбрать адаптер на Ø120 или Ø150.')
            ->assertSee('settings.chimneyDiameter', false)
            ->assertSee('data-row-decrease', false)
            ->assertSee('data-row-increase', false)
            ->assertSee('data-row-remove', false)
            ->assertSee('data-cart-feedback', false)
            ->assertSee('Добавляем ', false)
            ->assertSee("add.textContent = 'Открыть корзину · '", false)
            ->assertSee('data-route-schematic', false)
            ->assertSee('viewBox="0 0 440 620"', false)
            ->assertSee('data-chimney-schematic', false)
            ->assertSee('data-download-schematic', false)
            ->assertSee('data-diagram-row', false)
            ->assertSee('cs-schematic--assembly', false)
            ->assertSeeText('Комплектующие и последовательность сборки')
            ->assertSee('СХЕМА СБОРКИ МОДУЛЬНОГО ДЫМОХОДА', false)
            ->assertSee('assemblySequenceRank', false)
            ->assertSee("system === 'sandwich' ? 20 : 50", false)
            ->assertSee("kind:'water_tank'", false)
            ->assertSee("kind:'heat_exchanger'", false)
            ->assertSee('addWaterHeatingNeed', false)
            ->assertSeeText('Скачать монтажную схему SVG')
            ->assertSeeText('Комплект пересчитывается автоматически')
            ->assertSeeText('Очистить')
            ->assertSee('data-clear-calculation', false)
            ->assertSee('Расчёт очищен', false)
            ->assertSee('calculationActive = false', false)
            ->assertSeeText('Параметры изменены. Нажмите «Рассчитать и показать комплект».')
            ->assertSee('form.requestSubmit();', false)
            ->assertSeeText('Перейти в корзину и продолжить')
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
            ->assertSeeText('Горизонтальный участок через стену')
            ->assertSeeText('Проход через стену (ППУ)')
            ->assertSeeText('Вылет трубы от стены')
            ->assertSeeText('Длина участка между коленами')
            ->assertSeeText('Стеновые крепления')
            ->assertSeeText('Свободная высота над последним креплением')
            ->assertSeeText('Расстояние от трубы до конька')
            ->assertSee('data-share-kit', false)
            ->assertSee('recommendMaterial', false)
            ->assertSee('chimney_selector_token', false)
            ->assertSee("kind:'support'", false)
            ->assertSee("kind:'tee', quantity:1, system:teeSystem, angle:'87'", false)
            ->assertSee("kind:'wall_mount'", false)
            ->assertSee("kind:'roof_flashing'", false)
            ->assertSee("sharedParams.get('items')", false)
            ->assertSeeText('Теплов и Сухов')
            ->assertSeeText('−10%')
            ->assertSeeText('Передайте схему менеджеру')
            ->assertSee('data-addon-toggle', false)
            ->assertSee('.cs-addon__toggle{display:flex;width:100%;height:48px', false)
            ->assertSee('Умный поиск: теплообменник, бак, шибер…', false)
            ->assertSee('test-chimney-bundle-pipe', false);

        preg_match('/var catalog = (\[.*?\]);\s*var unlocked/s', $response->getContent(), $catalogJson);
        $catalog = collect(json_decode($catalogJson[1] ?? '[]', true));
        $darcoItem = $catalog->firstWhere('id', $darcoPipe->id);
        $mcBlackItem = $catalog->firstWhere('id', $mcBlackPipe->id);
        $mcBlackDamperItem = $catalog->firstWhere('id', $mcBlackDamper->id);
        $tankItem = $catalog->firstWhere('id', $tank->id);
        $heatExchangerItem = $catalog->firstWhere('id', $heatExchanger->id);
        $masterFlashItem = $catalog->firstWhere('id', $masterFlash->id);
        $adjustableMountItem = $catalog->firstWhere('id', $adjustableMount->id);
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
        $this->assertSame('water_tank', $tankItem['kind'] ?? null);
        $this->assertSame('mono', $tankItem['system'] ?? null);
        $this->assertFalse($tankItem['discount_eligible'] ?? true);
        $this->assertSame('heat_exchanger', $heatExchangerItem['kind'] ?? null);
        $this->assertSame('115', $heatExchangerItem['diameter'] ?? null);
        $this->assertSame('mono', $heatExchangerItem['system'] ?? null);
        $this->assertSame('roof_flashing', $masterFlashItem['kind'] ?? null);
        $this->assertSame(['178', '330'], $masterFlashItem['diameter_pair'] ?? null);
        $this->assertSame('wall_mount', $adjustableMountItem['kind'] ?? null);
        $this->assertSame(1000, $adjustableMountItem['mount_length_mm'] ?? null);
    }

    public function test_chimney_selector_is_listed_in_the_existing_knowledge_base(): void
    {
        $this->get(KnowledgeBase::URL)
            ->assertOk()
            ->assertSeeText('Как подобрать дымоход')
            ->assertSee('/podbor-dymohoda', false);
    }

    public function test_cart_accepts_self_selection_only_with_the_session_selector_token(): void
    {
        [, $chimney] = $this->products();
        $supplier = Supplier::query()->create([
            'code' => 'chimney-selector-test',
            'name' => 'Поставщик теста подбора',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $chimney->id,
            'supplier_article' => 'SELECTOR-PIPE',
            'price_byn' => 150,
            'in_stock' => false,
            'stock_status' => 'unknown',
        ]);

        $this->get(route('chimney-selector'))->assertOk();
        $token = session('chimney_selector_token');
        $this->assertIsString($token);

        $this->postJson(route('cart.add'), [
            'product_id' => $chimney->id,
            'chimney_selector_token' => 'invalid-token',
        ])->assertOk()->assertJsonPath('bundle_discount', 0);
        $this->assertFalse(session('cart')[$chimney->id]['chimney_selector_selected']);

        $this->postJson(route('cart.clear'))->assertOk();
        $this->postJson(route('cart.add'), [
            'product_id' => $chimney->id,
            'chimney_selector_token' => $token,
        ])->assertOk()->assertJsonPath('bundle_discount', 10);
        $this->assertTrue(session('cart')[$chimney->id]['chimney_selector_selected']);
    }

    public function test_selector_distinguishes_regular_clamps_from_guy_wire_hardware(): void
    {
        $controller = app(ChimneySelectorController::class);
        $method = new \ReflectionMethod(ChimneySelectorController::class, 'kind');

        $this->assertSame('clamp', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут трубный КНТ-Р Ø180'));
        $this->assertSame('guy_wire', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут растяжки Ø180'));
        $this->assertSame('elbow', $method->invoke($controller, 'kolena-sendvich', 'Отвод Термо 45° с хомутом'));
        $this->assertSame('mount', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут штанги регулируемой ХШР d200'));
        $this->assertSame('support', $method->invoke($controller, 'perehody-adaptery-dymohod', 'Площадка монтажная термо ПМТ-Р D120/180'));
        $this->assertSame('wall_mount', $method->invoke($controller, 'krepleniya-dymohod', 'Крепление настенное Ø180'));
        $this->assertSame('roof_flashing', $method->invoke($controller, 'zaglushki-dymohod', 'Фартук D180'));
        $this->assertSame('roof_flashing', $method->invoke($controller, 'krepleniya-dymohod', 'Мастер-флеш 178–330 мм'));

        $diameter = new \ReflectionMethod(ChimneySelectorController::class, 'diameterFromText');
        $this->assertSame('120', $diameter->invoke($controller, 'Шибер Darco SZK120-CZ2(ML) короткая ручка'));
        $this->assertSame('120', $diameter->invoke($controller, 'Колено поворотное Darco KNS120/45-CZ2 (ML)'));
        $this->assertSame('150', $diameter->invoke($controller, 'Колено фиксированное KS150/90-CZ2'));
        $this->assertSame('210', $diameter->invoke($controller, 'Хомут трубный на болте D210'));
    }

    /** @return array{Product, Product} */
    private function products(): array
    {
        $equipmentRoot = Category::query()->firstOrCreate(['slug' => 'pechi'], ['name' => 'Печи', 'parent_id' => 0]);
        $equipmentCategory = Category::query()->firstOrCreate(['slug' => 'test-wood-stoves'], ['name' => 'Печи для дома', 'parent_id' => $equipmentRoot->id]);
        $chimneyRoot = Category::query()->firstOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0]);
        $chimneyCategory = Category::query()->firstOrCreate(['slug' => 'truby-sendvich'], ['name' => 'Трубы сэндвич', 'parent_id' => $chimneyRoot->id]);

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
