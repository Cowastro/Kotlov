<?php

namespace Tests\Feature;

use App\Console\Commands\ApplyEcokaminOctoberSaleCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcokaminOctoberSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_prices_mirror_the_manufacturer_and_end_by_themselves(): void
    {
        Carbon::setTestNow('2026-10-22 12:00:00');
        $category = Category::query()->firstOrCreate(['slug' => 'kaminy'], ['name' => 'Камины', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->updateOrCreate(['slug' => 'ecokamin'], ['name' => 'ЭкоКамин', 'is_active' => true]);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], ['name' => 'ООО «СанБизнесГруп»', 'currency' => 'BYN', 'is_active' => true]);
        $make = function (string $name, float $price, ?string $bitrix) use ($category, $brand, $supplier): Product {
            $product = Product::query()->create([
                'category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true,
                'name' => $name, 'slug' => Str::slug($name), 'price' => $price,
                'in_stock' => true, 'availability_status' => 'in_stock',
            ]);
            if ($bitrix) {
                DB::table('supplier_products')->insert([
                    'supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'A'.$bitrix,
                    'source_url' => "https://ecokamin.ru/catalog/kaminy/x/{$bitrix}/", 'price' => 1, 'currency' => 'RUB',
                    'price_byn' => $price * 0.7, 'match_status' => 'matched', 'match_confidence' => 'official_source',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $product;
        };
        // Our cards already carry the manufacturer's promotional price × 0.0361 (synced from ecokamin.ru).
        $rio = $make('Камин ЭкоКамин Рио КР206', 7941.71, '17944');
        $delta1400 = $make('Каминная топка ЭкоКамин Дельта 1400V', 28879.64, '19157');
        $munich = $make('Камин ЭкоКамин МЮНХЕН ГИГАНТ черный', 5062.74, '14732');
        $delta800 = $make('Каминная топка ЭкоКамин ДЕЛЬТА 800 три стекла', 12389.24, '5543');
        $krit = $make('Камин КРИТ черный', 6353.42, '12680');
        $other = $make('Каминная топка ЭкоКамин Альфа 01/100', 2000, null);
        // A leftover of the first run that discounted twice: price_old holds the real card price.
        $rio->forceFill(['price' => 6353.37, 'price_old' => 7941.71, 'is_sale' => true, 'sale_campaign' => ApplyEcokaminOctoberSaleCommand::CAMPAIGN, 'sale_ends_at' => '2026-10-31 20:59:59'])->save();
        $delta1400->forceFill(['price' => 23103.71, 'price_old' => 28879.64, 'is_sale' => true, 'sale_campaign' => ApplyEcokaminOctoberSaleCommand::CAMPAIGN, 'sale_ends_at' => '2026-10-31 20:59:59'])->save();

        $listing = fn (array $items): string => collect($items)->map(fn ($p, $id) => '<div class="item"><a href="/catalog/kaminy/x/'.$id.'/" class="dark_link"><span>Камин</span></a>'
            .'<div class="price" data-currency="RUB" data-value="'.$p[0].'"></div>'
            .($p[1] ? '<div class="price discount" data-currency="RUB" data-value="'.$p[1].'"></div>' : '').'</div>')->implode('');
        $filler = collect(range(1, 20))->mapWithKeys(fn ($i) => [(string) (90000 + $i) => [100000, 125000]])->all();
        Http::fake(fn ($request) => Http::response(match (true) {
            str_contains($request->url(), 'api.nbrb.by') => ['Cur_OfficialRate' => 3.61, 'Cur_Scale' => 100],
            str_contains($request->url(), 'PAGEN_') => '',
            str_contains($request->url(), 'kaminnye_topki/delta') => $listing(['19157' => [799990, null], '5543' => [343192, 428990]] + $filler),
            str_contains($request->url(), 'kamin_krit') => $listing(['12680' => [175992, 219990]]),
            str_contains($request->url(), 'kaminy_rio') => $listing(['17944' => [219992, 274990]]),
            str_contains($request->url(), 'myunkhen') => $listing(['14732' => [140242, 164990]]),
            default => '',
        }));

        $this->artisan('catalog:apply-ecokamin-october-sale')->expectsOutputToContain('metric.ecokamin_sale.promo=4')->assertSuccessful();
        $this->assertSame('6353.37', (string) $rio->fresh()->price);

        $this->artisan('catalog:apply-ecokamin-october-sale --apply')->assertSuccessful();
        $this->artisan('catalog:apply-ecokamin-october-sale --apply')->expectsOutputToContain('metric.ecokamin_sale.changed=0')->assertSuccessful();

        $rio = $rio->fresh();
        $this->assertSame(['7941.71', '9927.14'], [(string) $rio->price, (string) $rio->price_old]);
        $this->assertSame(20, $rio->discount_percent);
        $munich = $munich->fresh();
        $this->assertSame(['5062.74', '5956.14'], [(string) $munich->price, (string) $munich->price_old]);
        $this->assertTrue($munich->isPublicSale());
        $delta1400 = $delta1400->fresh();
        $this->assertSame('28879.64', (string) $delta1400->price);
        $this->assertNull($delta1400->sale_campaign);
        $this->assertFalse($delta1400->is_sale);
        $this->assertFalse((bool) $other->fresh()->is_sale);
        $this->assertSame(4, Product::query()->inActiveSaleCampaign(ApplyEcokaminOctoberSaleCommand::CAMPAIGN)->count());

        // The online-order 3 % does not stack with the promotion.
        $this->assertFalse($rio->qualifiesForOnlineOrderDiscount());
        $this->assertTrue($other->fresh()->qualifiesForOnlineOrderDiscount());
        $this->assertSame(2000.0, app(\App\Services\CartCouponService::class)->onlineOrderDiscountSubtotal([
            ['id' => $rio->id, 'price' => 7941.71, 'quantity' => 1, 'category_slug' => 'kaminy'],
            ['id' => $other->id, 'price' => 2000, 'quantity' => 1, 'category_slug' => 'kaminy'],
        ]));

        Cache::flush();
        $this->get('/akcii/ecokamin-skidki')->assertOk()->assertSee('img/promotions/ecokamin-2026-10/delta.webp', false)->assertSee($rio->name)->assertDontSee('Альфа 01/100');
        $this->get('/akcii')->assertOk()->assertSee('Акция ЭкоКамин');
        $this->get('/')->assertOk()
            ->assertSee('eco-promo-slide__product', false)
            ->assertSee('Дельта 800 три стекла')
            ->assertSee('/akcii/ecokamin-skidki#delta', false)
            ->assertSee('/akcii/ecokamin-skidki#kaminy', false)
            ->assertSee('Акция ЭкоКамин — скидки до 20%');

        Carbon::setTestNow('2026-11-01 00:05:00');
        $this->assertFalse($rio->fresh()->isPublicSale());
        $this->artisan('catalog:expire-sale-campaigns --apply')->expectsOutputToContain('metric.sale_campaigns.restored=4')->assertSuccessful();
        $rio = $rio->fresh();
        $this->assertSame('9927.14', (string) $rio->price);
        $this->assertNull($rio->price_old);
        $this->assertFalse($rio->is_sale);
        $this->assertTrue($rio->qualifiesForOnlineOrderDiscount());
        Cache::flush();
        $this->get('/akcii/ecokamin-skidki')->assertRedirect('/akcii');
        $this->get('/')->assertOk()->assertDontSee('/akcii/ecokamin-skidki#delta', false)->assertDontSee('eco-promo-slide__product', false);
    }
}
