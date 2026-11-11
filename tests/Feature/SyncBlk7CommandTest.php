<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\Blk7Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncBlk7CommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_lavoro_stock_and_prices_link_to_cards_and_new_cards_are_made(): void
    {
        $item = fn (string $slug, string $name, string $article, ?int $stock, string $retail, string $wholesale) => '<div class="product-layout product-list col-xs-12"><div class="product-thumb">'
            .'<div class="image"><a href="https://b2b.blk7.by/'.$slug.'"><img src="https://b2b.blk7.by/image/cache/catalog/test/'.$slug.'-1-228x228.png" /></a></div>'
            .'<div class="caption"><h4><a href="https://b2b.blk7.by/'.$slug.'">'.$name.'</a></h4>'
            .($stock === null ? '<b>Под заказ</b><p class="price"><span class="price-old">'.$retail.' руб.</span><span class="price-new">Оптовая цена: '.$wholesale.' руб.</span></p>Артикул:  <b>'.$article.'</b>'
                : '<p class="price"><b>Наличие на складе: '.$stock.'  шт.</b> <br> Артикул:  <b>'.$article.'</b> <span class="price-old">'.$retail.' руб.</span><span class="price-new">Оптовая цена: '.$wholesale.' руб.</span></p>')
            .'</div></div></div>';
        $list = '<html><body>'
            .$item('tverdotoplivnyj-kotel-lavoro-eco-l-12-bez-avtomatiki', 'Твердотопливный котел Lavoro Eco L-12 (без автоматики)', 'BLK0073753', null, '2400.00', '2040.00')
            .$item('tverdotoplivnyj-kotel-lavoro-eco-m-15', 'Твердотопливный котел Lavoro Eco M-15', 'BLK0073707', 3, '2525.00', '2146.25')
            .$item('tverdotoplivnyj-kotel-lavoro-eco-m-25-dvukhkonturnyj', 'Твердотопливный котел Lavoro Eco M-25 (двухконтурный)', 'BLK0078865', 4, '3050.00', '2592.50')
            .$item('pelletnyj-kotel-lavoro-eco-lf-16', 'Пеллетный котел Lavoro Eco LF-16', 'BLK0073731', 3, '10260.00', '8721.00')
            .'</body></html>';
        $page = '<div id="tab-description">Описание<br>Площадь отопления, м2: 150<br>Колосниковая решетка: Чугунная<br>Тип котла: Пеллетный</div>'
            .'<img src="https://b2b.blk7.by/image/cache/catalog/test/pelletnyj-kotel-lavoro-eco-lf-16-1-500x500.png">';
        $image = imagecreatetruecolor(500, 500);
        imagefilledrectangle($image, 80, 40, 420, 460, imagecolorallocate($image, 90, 200, 60));
        imagefilledrectangle($image, 0, 0, 500, 30, imagecolorallocate($image, 30, 30, 30));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        Http::fake([
            'b2b.blk7.by/kotly-tverdotoplivnye/lavoro/*' => Http::response($list),
            'b2b.blk7.by/image/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            'b2b.blk7.by/pelletnyj-kotel-lavoro-eco-lf-16' => Http::response($page),
            '*' => Http::response('', 404),
        ]);

        $solid = Category::query()->firstOrCreate(['slug' => 'tverdotoplivnye'], ['name' => 'Твердотопливные', 'parent_id' => 0, 'is_active' => true]);
        $pellet = Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], ['name' => 'Котлы на пеллетах', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Lavoro', 'slug' => 'lavoro', 'is_active' => true]);
        $card = fn (string $name, float $price) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $solid->id,
            'brand_id' => $brand->id, 'price' => $price, 'is_active' => true]);
        $l12 = $card('Твердотопливный котел Lavoro Eco L-12 (без автоматики)', 2300);
        $m15 = $card('Твердотопливный котел Lavoro Eco M-15', 2400);

        $this->artisan('supplier:sync-blk7', ['--apply' => true])
            ->expectsOutputToContain('metric.blk7.rows=4')
            ->expectsOutputToContain('metric.blk7.model=2')
            ->expectsOutputToContain('metric.blk7.unlinked=2')
            ->assertSuccessful();
        $supplier = Supplier::query()->where('code', 'blk7')->sole();
        $offer = fn (Product $p) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $p->id)->sole();
        $this->assertSame(['preorder', 2040.0], [$offer($l12)->stock_status, (float) $offer($l12)->price_byn]);
        $this->assertSame(['in_stock', 3, 2146.25], [$offer($m15)->stock_status, $offer($m15)->stock_quantity, (float) $offer($m15)->price_byn]);
        $this->assertSame([2400.0, 2525.0], [(float) $l12->fresh()->price, (float) $m15->fresh()->price]);

        // M-25 (двухконтурный) is not M-25; the pellet LF-16 goes to the pellet boilers with the photo of its page.
        $this->artisan('supplier:blk7-create', ['--apply' => true])
            ->expectsOutputToContain('metric.blk7_create.created=2')
            ->assertSuccessful();
        $lf16 = Product::query()->where('sku', 'BLK7-BLK0073731')->sole();
        $this->assertSame(['Пеллетный котел Lavoro Eco LF-16', $pellet->id, 10260.0, true], [$lf16->name, $lf16->category_id, (float) $lf16->price, (bool) $lf16->is_active]);
        $this->assertSame($solid->id, Product::query()->where('sku', 'BLK7-BLK0078865')->value('category_id'));
        foreach ((array) $lf16->images as $path) {
            @unlink(public_path($path));
        }
    }

    public function test_model_key(): void
    {
        $this->assertSame('l 12 bez', Blk7Catalog::modelKey('Твердотопливный котел Lavoro Eco L-12 (без автоматики)'));
        $this->assertSame('m 25 dk', Blk7Catalog::modelKey('Lavoro Eco M-25 (двухконтурный)'));
        $this->assertSame('lcmega 12', Blk7Catalog::modelKey('Твердотопливный котел Lavoro Carbone LC Mega-12'));
        $this->assertSame('k 12', Blk7Catalog::modelKey('Твердотопливный котел Lavoro Eco К-12'));
        $this->assertSame('lf 120', Blk7Catalog::modelKey('Пеллетный котел Lavoro Eco LF-120'));
    }
}
