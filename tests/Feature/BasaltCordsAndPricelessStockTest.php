<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BasaltCordsAndPricelessStockTest extends TestCase
{
    use RefreshDatabase;

    private const SAFE = '/^(metric\.priceless_stock\.[a-z_]+=[0-9]+|audit\.priceless_stock\.row=[a-z0-9-]+:[a-z0-9-]+:[0-9]+:[0-9]+:[0-9]+\.[0-9]{2}:[01])$/';

    public function test_the_basalt_cords_get_their_photo_brand_and_go_on_sale(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'uplotnitelnye-shnury'], ['name' => 'Уплотнительные шнуры', 'is_active' => true]);
        foreach ([6, 8, 10] as $mm) {
            Product::query()->create(['category_id' => $category->id, 'name' => "Шнур базальтовый {$mm} мм, 25 м.п.", 'slug' => "snur-bazaltovyi-{$mm}-mm-25-mp",
                'price' => 50, 'is_active' => false, 'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS]);
        }

        (require database_path('migrations/2026_11_20_160000_publish_basalt_cords.php'))->up();

        $six = Product::query()->where('slug', 'snur-bazaltovyi-6-mm-25-mp')->firstOrFail();
        $ten = Product::query()->where('slug', 'snur-bazaltovyi-10-mm-25-mp')->firstOrFail();
        $this->assertSame('Шнур базальтовый Везувий 6 мм, 25 м', $six->name);
        $this->assertSame('Везувий', $six->brand?->name);
        $this->assertSame('Литком', $ten->brand?->name);
        $this->assertSame(['img/products/bazalt-shnur/vezuvii-6-mm-25-m.jpg'], $six->images);
        $this->assertTrue((bool) $six->is_active);
        $this->assertNull($six->created_via);
        $this->assertSame('4610094711346', collect($six->specs)->pluck('value', 'key')['Штрихкод']);
        $this->assertFileExists(public_path('img/products/bazalt-shnur/litkom-10-mm-25-m.jpg'));
    }

    public function test_goods_in_stock_without_a_price_are_listed_with_their_card(): void
    {
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $category = Category::query()->firstOrCreate(['slug' => 'kotly'], ['name' => 'Котлы', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Шнур', 'slug' => 'snur', 'price' => 0]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'a', 'external_code' => 'УТ-00000001',
            'name' => 'Шнур без цены', 'stock_quantity' => 2, 'price' => 0, 'product_id' => $card->id, 'match_status' => 'matched']);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'b', 'external_code' => 'УТ-00000002',
            'name' => 'С ценой', 'stock_quantity' => 2, 'price' => 10, 'match_status' => 'unmatched']);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'c', 'external_code' => 'УТ-00000003',
            'name' => 'Нет на складе', 'stock_quantity' => 0, 'price' => 0, 'match_status' => 'unmatched']);

        Artisan::call('integration:priceless-stock');
        $lines = array_values(array_filter(preg_split('/\R/', trim(Artisan::output()))));

        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression(self::SAFE, $line);
        }
        $output = implode("\n", $lines);
        $this->assertStringContainsString('audit.priceless_stock.row=ut-00000001:snur-bez-ceny:2:'.$card->id.':0.00:1', $output);
        $this->assertStringNotContainsString('ut-00000002', $output);
        $this->assertStringNotContainsString('ut-00000003', $output);
        $this->assertStringContainsString('metric.priceless_stock.card_without_retail=1', $output);
    }
}
