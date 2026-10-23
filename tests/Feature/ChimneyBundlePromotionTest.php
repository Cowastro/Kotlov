<?php

namespace Tests\Feature;

use App\Http\Controllers\ChimneySelectorController;
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
        $metrePipe = Product::query()->create([
            'category_id' => $chimney->category_id,
            'name' => 'Труба Теплов и Сухов моно ТМ-Р L1000, 430, 0.8, Ø 120',
            'slug' => 'test-l1000-430-08-d120',
            'sku' => 'TEST-L1000-430-08-D120',
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $this->get(route('chimney-selector', ['equipment' => $equipment->id]))
            ->assertOk()
            ->assertSeeText('Соберите дымоход под своё оборудование')
            ->assertSeeText('Марка стали')
            ->assertSeeText('Толщина стали трубы')
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
            ->assertSeeText('Теплов и Сухов')
            ->assertSeeText('−10%')
            ->assertSeeText('Передайте схему менеджеру')
            ->assertSee('test-chimney-bundle-pipe', false);
    }

    public function test_selector_distinguishes_regular_clamps_from_guy_wire_hardware(): void
    {
        $method = new \ReflectionMethod(ChimneySelectorController::class, 'kind');
        $controller = app(ChimneySelectorController::class);

        $this->assertSame('clamp', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут трубный КНТ-Р Ø180'));
        $this->assertSame('guy_wire', $method->invoke($controller, 'krepleniya-dymohod', 'Хомут растяжки Ø180'));
        $this->assertSame('elbow', $method->invoke($controller, 'kolena-sendvich', 'Отвод Термо 45° с хомутом'));
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
