<?php

namespace Tests\Feature;

use App\Console\Commands\ChistyBeregPhotosCommand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\WebProductSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChistyBeregPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_is_taken_only_when_it_names_this_very_item(): void
    {
        $page = fn (string $title, array $specs = []) => ['title' => $title, 'specs' => $specs];
        $this->assertTrue(ChistyBeregPhotosCommand::samePage('Муфта 20', 'PRO AQUA', 'PA-12020', 'https://shop.by/p/1', $page('Муфта', [['key' => 'Артикул', 'value' => 'PA12020']])));
        $this->assertTrue(ChistyBeregPhotosCommand::samePage('П/п труба PN20 20х3,4 PRO AQUA', 'PRO AQUA', '', 'https://shop.by/p/1', $page('Труба PRO AQUA PN20 20x3.4')));
        $this->assertFalse(ChistyBeregPhotosCommand::samePage('П/п труба PN20 20х3,4 PRO AQUA', 'PRO AQUA', '', 'https://shop.by/p/1', $page('Труба PRO AQUA PN20 25x4.2')));
        $this->assertFalse(ChistyBeregPhotosCommand::samePage('Муфта 20', '', '', 'https://shop.by/p/1', $page('Муфта 20')));
        $this->assertTrue(ChistyBeregPhotosCommand::samePage('Вентиль запорный чугунный фланцевый 15кч19п Ру 16 Ду 25', '', '', 'https://shop.by/p/1', $page('Вентиль 15кч19п Ду 25 Ру 16')));
        $this->assertFalse(ChistyBeregPhotosCommand::samePage('Вентиль запорный чугунный фланцевый 15кч19п Ру 16 Ду 25', '', '', 'https://shop.by/p/1', $page('Вентиль 15кч19п Ду 32 Ру 16')));
    }

    public function test_the_hidden_card_gets_the_photo_of_the_matching_page_once(): void
    {
        $category = Category::query()->create(['name' => 'Фитинги', 'slug' => 'rezbovye-fitingi', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Муфта 1/2" VALTEC', 'slug' => 'mufta-valtec', 'sku' => 'BEREG-00100001',
            'price' => 3, 'images' => [], 'is_active' => false, 'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS]);
        $supplier = Supplier::query()->where('code', 'chisty_bereg')->firstOrFail();
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'supplier_article' => '00100001', 'supplier_name' => 'Муфта 1/2" VALTEC Италия',
            'product_id' => $card->id, 'match_status' => 'matched', 'raw' => ['brand' => 'VALTEC', 'maker_code' => 'VTr.240.N.0004']]);
        $this->mock(WebProductSources::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('search')->andReturn([
                'https://other.by/mufta' => 'other.by · Муфта',
                'https://shop.by/valtec-vtr-240-n-0004' => 'shop.by · Муфта VALTEC',
            ]);
        });
        $image = 'https://shop.by/upload/mufta.jpg';
        Http::fake([
            'other.by/*' => Http::response('<html><h1>Муфта 3/4 другая</h1><img src="https://other.by/upload/x.jpg"></html>'),
            'shop.by/valtec*' => Http::response('<html><h1>Муфта VALTEC VTr.240.N.0004</h1><img src="'.$image.'"></html>'),
            'shop.by/upload/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
            '*' => Http::response(''),
        ]);

        $this->artisan('supplier:chisty-bereg-photos')
            ->expectsOutputToContain("audit.chisty_bereg_photos.card={$card->id}:mufta-valtec:shopby")
            ->assertSuccessful();
        $this->assertSame([], (array) $card->fresh()->images);

        $this->artisan('supplier:chisty-bereg-photos', ['--apply' => true])
            ->expectsOutputToContain('metric.chisty_bereg_photos.saved=1')
            ->assertSuccessful();
        $images = array_filter((array) $card->fresh()->images);
        $this->assertNotSame([], $images);
        foreach ($images as $path) {
            @unlink(public_path($path));
        }

        $this->artisan('supplier:chisty-bereg-photos')->expectsOutputToContain('metric.chisty_bereg_photos.due=0')->assertSuccessful();
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }
}
