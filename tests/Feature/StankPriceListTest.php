<?php

namespace Tests\Feature;

use App\Console\Commands\CreateStankCardsCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\StankPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StankPriceListTest extends TestCase
{
    use RefreshDatabase;

    /** The prices the sync carried before the list moved to the JSON file. */
    private const OLD = ['BER-150' => 1828.80, 'BER-200' => 2048.40, 'BER-300' => 3182.40, 'BER-400' => 4582.80, 'BER-500' => 5180.40, 'BER-750' => 6775.20, 'BER-1000' => 8064.00, 'AT-200' => 1591.20, 'AT-300' => 1713.60, 'AT-500' => 1951.20, 'AT-750' => 2358.00, 'AT-1000' => 2718.00, 'AT-1200' => 3848.40, 'AT-1500' => 4096.80, 'AT-2000' => 6426.00, 'AT-3000' => 10130.40, 'AT-5000' => 14724.00, 'ET-200' => 1591.20, 'ET-300' => 1713.60, 'ET-500' => 1951.20, 'ET-750' => 2358.00, 'ET-1000' => 2718.00, 'ET-1200' => 3848.40, 'ET-1500' => 4096.80, 'ET-2000' => 6426.00, 'ET-3000' => 10130.40, 'ET-5000' => 14724.00, 'SOLARSS-150' => 3758.00, 'SOLARSS-200' => 4019.00, 'SOLARSS-300' => 5666.00, 'SOLARSS-500' => 7394.00, 'SOLARSS-750' => 8968.00, 'SOLARSS-1000' => 10598.00, 'SOLARSS-1200' => 12582.00, 'SOLARSS-1500' => 12971.00, 'SOLARSS-2000' => 16438.00, 'SOLARSS-3000' => null, 'HFWT-300' => 2152.80, 'HFWT-500' => 2919.60, 'HFWT-750' => 3510.00, 'HFWT-1000' => 4176.00, 'HFWT-1200' => 5504.40, 'HFWT-1500' => 6080.40, 'HFWT-2000' => 7927.20, 'HFWT-3000' => null, 'FRESH-200' => 1954.80, ];

    public function test_model_keys_of_card_names_and_price_rows(): void
    {
        foreach ([
            'Буферная ёмкость S-TANK AT-300' => 'AT-300',
            'Буферная емкость S-TANK Prestige AT-1200' => 'AT-1200',
            'TA90/TA line 500' => 'AT-500',
            'Бак косвенного нагрева S-TANK Solar SS 150' => 'SOLARSS-150',
            'SOLAR SS DUO-200' => 'SOLARSSDUO-200',
            'SS-HP -1000' => 'SSHP-1000',
            'Бак косвенного нагрева S-TANK BER-150' => 'BER-150',
            'BER2-750' => 'BER2-750',
            'FLAT 600' => 'FLAT-600',
            'AT ETRO MONO 200' => 'ATETROMONO-200',
            'SS ETRO 300' => 'SSETRO-300',
            'SS CT 200' => 'SSCT-200',
            'CT 200' => 'CT-200',
            'HFWT DUO 500' => 'HFWTDUO-500',
            'Косвенный водонагреватель S-TANK HFWT 300' => 'HFWT-300',
            'S-TANK FRESH 200' => 'FRESH-200',
            'Буферная ёмкость S-TANK ET-500' => 'ET-500',
            'SS 200' => 'SS-200',
            'P Turbo-150' => 'PTURBO-150',
            'P easy -160' => 'PEASY-160',
            'PW-200' => 'PW-200',
        ] as $name => $key) {
            $this->assertSame($key, StankPriceList::key($name), $name);
        }
    }

    public function test_the_json_keeps_the_prices_the_sync_had(): void
    {
        foreach (self::OLD as $key => $price) {
            $this->assertSame($price, StankPriceList::price($key), $key);
        }
        $this->assertSame(158, count(StankPriceList::rows()));
    }

    public function test_titles_name_the_kind_of_tank_and_meet_the_key(): void
    {
        foreach (StankPriceList::rows() as $key => $row) {
            $this->assertSame($key, StankPriceList::key(CreateStankCardsCommand::cardTitle($row)), $key);
        }
        $this->assertSame('Буферная ёмкость S-TANK FLAT 600', CreateStankCardsCommand::cardTitle(StankPriceList::row('FLAT-600')));
        $this->assertSame('Бак косвенного нагрева S-TANK SS-HP-300 (нерж. сталь)', CreateStankCardsCommand::cardTitle(StankPriceList::row('SSHP-300')));
    }

    public function test_a_created_card_is_linked_and_priced_by_the_sync(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        $brand = Brand::create(['name' => 'S-TANK', 'slug' => 's-tank']);
        $buffers = Category::query()->create(['name' => 'Буферные ёмкости', 'slug' => 'bufernye-emkosti', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->create(['name' => 'Косвенные', 'slug' => 'kosvennye', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->create(['name' => 'Электрические', 'slug' => 'electric', 'parent_id' => 0, 'is_active' => true]);
        Supplier::query()->create(['code' => 'stank', 'name' => 'S-TANK', 'is_active' => true]);
        Product::query()->create(['category_id' => $buffers->id, 'brand_id' => $brand->id, 'name' => 'Буферная ёмкость S-TANK AT-500', 'slug' => 'at-500', 'sku' => 'AT500', 'price' => 1, 'is_active' => true, 'is_archived' => false]);

        $this->artisan('supplier:stank-create', ['--only' => ['FLAT-600', 'AT-500'], '--apply' => true])->assertSuccessful();

        $card = Product::query()->where('sku', 'STANK-FLAT-600')->firstOrFail();
        $this->assertSame('Буферная ёмкость S-TANK FLAT 600', $card->name);
        $this->assertSame($buffers->id, $card->category_id);
        $this->assertFalse((bool) $card->is_active);
        $this->assertSame(5432.4, (float) $card->price);
        $this->assertSame('FLAT-600', SupplierProduct::query()->where('product_id', $card->id)->value('supplier_article'));
        $this->assertSame(1, Product::query()->where('name', 'like', '%AT-500%')->count());

        $this->artisan('supplier:sync-stank')->assertSuccessful();
        $this->assertSame(1951.2, (float) Product::query()->where('sku', 'AT500')->value('price'));
    }
}
