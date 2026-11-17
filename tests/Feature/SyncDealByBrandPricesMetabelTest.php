<?php

namespace Tests\Feature;

use App\Console\Commands\SyncDealByBrandPricesCommand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncDealByBrandPricesMetabelTest extends TestCase
{
    use RefreshDatabase;

    /** Shop names (sbg.by) and card or price-list names that are the same model. */
    public static function samePairs(): array
    {
        return [
            ['Печь-камин Мета-Бел Ока 6 кВт', 'Печь-камин АОТ 6.0  (в модификации "Ока")'],
            ['Печь-камин Мета-Бел Ока с плитой 6 кВт', 'Печь-камин Мета-Бел Ока с плитой'],
            ['Печь-камин Мета-Бел Енисей 2 11 кВт АОТ-11,0', 'Печь-камин АОТ 11.0  (в модификации "Енисей 2")'],
            ['Печь-камин Мета-Бел Ангара-Аква 9 кВт с водяным контуром АОТВ-9,0-02', 'Печь-камин Мета-Бел Ангара Аква'],
            ['Банная печь-каменка Мета-Бел ПБМ 20 ПС', 'Печь-каменка Мета-Бел ПБМ 20 (в модификации ПС)'],
            ['Печь-камин Мета-Бел Нарочь АОТ-07 В', 'Печь-камин АОТ 7.0-01  (в модификации "Нарочь В")'],
            ['Печь-камин Мета-Бел Ритм L+ АОТ-7,0', 'Печь-камин Мета-Бел Ритм L+'],
            ['Печь-камин Селенга 7 кВт (Мета-Бел)', 'Печь-камин Мета-Бел Селенга'],
            ['Топка каминная Мета-Бел FENIX 900 (ТКТ 11.0-01)', 'Топка каминная ТКТ 11.0-01 (в модификации "FENIX 900")'],
        ];
    }

    public function test_model_keys_join_the_same_model_and_keep_others_apart(): void
    {
        foreach (self::samePairs() as [$shop, $card]) {
            $this->assertSame(SyncDealByBrandPricesCommand::metaKeys($shop)[0], SyncDealByBrandPricesCommand::metaKeys($card)[0], "{$shop} ≠ {$card}");
        }
        $key = fn (string $name) => SyncDealByBrandPricesCommand::metaKeys($name)[0];
        $this->assertNotSame($key('Печь-камин Мета-Бел Ока 6 кВт'), $key('Печь-камин Мета-Бел Ока с плитой 6 кВт'));
        $this->assertNotSame($key('Печь-камин Мета-Бел Ритм L АОТ-7,0'), $key('Печь-камин Мета-Бел Ритм L+ АОТ-7,0'));
        $this->assertNotSame($key('Печь-камин Мета-Бел Севан 7'), $key('Печь-камин Мета-Бел Севан 7В (АОТ 7.0-05)'));
        $this->assertContains('code:дк01', SyncDealByBrandPricesCommand::metaKeys('Печная дверца со стеклом Мета-Бел Сож ДК-01'));
        $this->assertContains('code:дк01', SyncDealByBrandPricesCommand::metaKeys('Дверь каминная ДК-01'));
    }

    public function test_meta_shop_items_get_the_card_price_by_model(): void
    {
        $category = Category::query()->create(['name' => 'Печи-камины', 'slug' => 'deal-meta-test', 'is_active' => true]);
        $card = fn (string $name, string $slug, float $price) => Product::query()->create(['category_id' => $category->id, 'name' => $name, 'slug' => $slug,
            'price' => $price, 'is_active' => true, 'is_archived' => false]);
        $oka = $card('Печь-камин Мета-Бел Ока', 'deal-meta-oka', 1665);
        $okaPlate = $card('Печь-камин Мета-Бел Ока с плитой', 'deal-meta-oka-plate', 1755);
        $door = $card('Дверь каминная ДК-01', 'deal-meta-dk01', 773);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'metabel'], ['name' => 'Мета-Бел', 'currency' => 'BYN', 'is_active' => true]);
        foreach ([$oka, $okaPlate, $door] as $product) {
            SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'mb-'.$product->slug,
                'supplier_name' => $product->name, 'match_status' => 'matched']);
        }

        $this->mock(DealByClient::class, fn (MockInterface $mock) => $mock->shouldReceive('listProducts')->andReturn(['products' => [
            ['id' => 701, 'sku' => '', 'name' => 'Печь-камин Мета-Бел Ока 6 кВт', 'price' => 1590],
            ['id' => 702, 'sku' => '', 'name' => 'Печь-камин Мета-Бел Ока с плитой 6 кВт', 'price' => 1680],
            ['id' => 703, 'sku' => '', 'name' => 'Печная дверца со стеклом Мета-Бел Сож ДК-01', 'price' => 735],
        ]]));

        $this->artisan('deal:sync-brand-prices', ['--supplier' => 'metabel'])
            ->expectsOutputToContain('metric.deal_brand_prices.matched=3')
            ->expectsOutputToContain("audit.deal_brand_prices=701:{$oka->id}:metabel:159000:166500")
            ->expectsOutputToContain("audit.deal_brand_prices=702:{$okaPlate->id}:metabel:168000:175500")
            ->expectsOutputToContain("audit.deal_brand_prices=703:{$door->id}:metabel:73500:77300")
            ->assertSuccessful();
    }
}
