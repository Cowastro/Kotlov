<?php

namespace Tests\Feature;

use App\Console\Commands\ApplyEcokaminOctoberSaleCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EcokaminOctoberSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_discounts_the_promotion_series_once_and_restores_prices_after_the_end(): void
    {
        Carbon::setTestNow('2026-10-22 12:00:00');
        $category = Category::query()->firstOrCreate(['slug' => 'kaminy'], ['name' => 'Камины', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->updateOrCreate(['slug' => 'ecokamin'], ['name' => 'ЭкоКамин', 'is_active' => true]);
        $make = fn (string $name, float $price) => Product::query()->create([
            'category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true,
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'price' => $price,
        ]);
        $delta = $make('Каминная топка ЭкоКамин Дельта 01/150 угловая', 3000);
        $rio = $make('Камин ЭкоКамин Рио КР206', 2500);
        $munich = $make('Камин ЭкоКамин Мюнхен', 4000);
        $glass = $make('Стекло для топки ЭкоКамин Дельта', 300);
        $other = $make('Каминная топка ЭкоКамин Альфа 01/100', 2000);
        $priority = $make('Камин ЭкоКамин Приоритет', 1000);
        $alphaGlass = $make('Каминная топка ЭкоКамин Альфа 700 три стекла подовая', 5700);

        $this->artisan('catalog:apply-ecokamin-october-sale')->expectsOutputToContain('metric.ecokamin_sale.to_discount=3')->assertSuccessful();
        $this->assertSame('3000.00', (string) $delta->fresh()->price);

        $this->artisan('catalog:apply-ecokamin-october-sale --apply')->assertSuccessful();
        $this->artisan('catalog:apply-ecokamin-october-sale --apply')->expectsOutputToContain('metric.ecokamin_sale.already_in_campaign=3')->assertSuccessful();

        $this->assertSame(['2400.00', '3000.00'], [(string) $delta->fresh()->price, (string) $delta->fresh()->price_old]);
        $this->assertSame('2000.00', (string) $rio->fresh()->price);
        $this->assertSame('3400.00', (string) $munich->fresh()->price);
        $this->assertTrue($delta->fresh()->isPublicSale());
        $this->assertSame(20, $delta->fresh()->discount_percent);
        foreach ([$glass, $other, $priority, $alphaGlass] as $untouched) {
            $this->assertFalse((bool) $untouched->fresh()->is_sale);
        }
        $this->assertSame(3, Product::query()->inActiveSaleCampaign(ApplyEcokaminOctoberSaleCommand::CAMPAIGN)->count());

        // The online-order 3 % does not stack with the promotion.
        $this->assertFalse($delta->fresh()->qualifiesForOnlineOrderDiscount());
        $this->assertTrue($other->fresh()->qualifiesForOnlineOrderDiscount());
        $this->assertSame(2000.0, app(\App\Services\CartCouponService::class)->onlineOrderDiscountSubtotal([
            ['id' => $delta->id, 'price' => 2400, 'quantity' => 1, 'category_slug' => 'kaminy'],
            ['id' => $other->id, 'price' => 2000, 'quantity' => 1, 'category_slug' => 'kaminy'],
        ]));

        \Illuminate\Support\Facades\Cache::flush();
        $this->get('/akcii/ecokamin-skidki')->assertOk()
            ->assertSee('Топки ЭкоКамин Дельта')
            ->assertSee('img/promotions/ecokamin-2026-10/delta.webp', false)
            ->assertSee('Каминная топка ЭкоКамин Дельта 01/150 угловая')
            ->assertDontSee('Альфа 01/100');
        $this->get('/akcii')->assertOk()->assertSee('Акция ЭкоКамин');
        $this->get('/')->assertOk()->assertSee('/akcii/ecokamin-skidki#delta', false);

        Carbon::setTestNow('2026-11-01 00:05:00');
        $this->assertFalse($delta->fresh()->isPublicSale());
        $this->artisan('catalog:expire-sale-campaigns --apply')->expectsOutputToContain('metric.sale_campaigns.restored=3')->assertSuccessful();
        $delta = $delta->fresh();
        $this->assertSame('3000.00', (string) $delta->price);
        $this->assertNull($delta->price_old);
        $this->assertFalse($delta->is_sale);
        $this->assertNull($delta->sale_campaign);
        $this->assertTrue($delta->qualifiesForOnlineOrderDiscount());
        \Illuminate\Support\Facades\Cache::flush();
        $this->get('/akcii/ecokamin-skidki')->assertRedirect('/akcii');
        $this->get('/')->assertOk()->assertDontSee('/akcii/ecokamin-skidki#delta', false);
    }
}
