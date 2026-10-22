<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTskNasosyMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_brandless_cards_and_spelling_variants_are_linked(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'nasosnye-stancii'], ['name' => 'Насосные станции', 'parent_id' => 0, 'is_active' => true]);
        Brand::query()->create(['name' => 'UNIPUMP', 'slug' => 'unipump', 'is_active' => true]);
        $make = fn (string $name, string $slug) => Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 300, 'name' => $name, 'slug' => $slug]);
        $auto = $make('Насосная станция UNIPUMP AUTO QB 80', 'auto-qb-80');
        $auto5 = $make('Насосная станция UNIPUMP AUTO QB 80-5', 'auto-qb-80-5');
        $robot = $make('Насосная станция UNIPUMP АКВАРОБОТ QB 80 (г/а 2 л)', 'akvarobot-qb-80');
        $pump = $make('Насос поверхностный UNIPUMP QB-80', 'qb-80');

        $csv = tempnam(sys_get_temp_dir(), 'tsk').'.csv';
        file_put_contents($csv, implode("\n", [
            'Артикул,,Наименование,Бренд,Модель,Опт 1,МРЦ,Наличие',
            '76108,,Станция авт.водоснабжения AUTO QB 80,UNIPUMP,AUTO QB 80,"326,52","442,56",в наличии',
            '71464,,Станция авт.водоснабжения AUTO QB 80 - 5,UNIPUMP,AUTO QB 80 - 5,"229,87","311,56",под заказ 2 нед',
            '18969,,Адапт. станция АКВАРОБОТ QB 80 (г/а 2л),UNIPUMP,АКВАРОБОТ QB 80 (г/а 2л),"290,63","393,92",в наличии',
            '38873,,Поверхностный вихревой насос QB 80,UNIPUMP,QB 80,"184,87","250,56",в наличии',
        ]));

        $this->artisan('supplier:sync-tsk-nasosy', ['--apply' => true, '--stock-file' => $csv])->assertSuccessful();

        foreach ([[$auto, '76108'], [$auto5, '71464'], [$robot, '18969'], [$pump, '38873']] as [$product, $article]) {
            $this->assertSame($article, $product->supplierProducts()->sole()->supplier_article, $product->name);
        }
        $this->assertSame('326.52', number_format((float) $auto->supplierProducts()->sole()->price_byn, 2, '.', ''));
    }

    public function test_the_hidden_1c_export_layout_gives_price_and_minsk_stock(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'nasosnye-stancii'], ['name' => 'Насосные станции', 'parent_id' => 0, 'is_active' => true]);
        Brand::query()->create(['name' => 'UNIPUMP', 'slug' => 'unipump', 'is_active' => true]);
        $station = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 300, 'name' => 'Насосная станция UNIPUMP AUTO QB 70', 'slug' => 'auto-qb-70']);
        $spare = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 300, 'name' => 'Насосная станция UNIPUMP AUTO QB 60', 'slug' => 'auto-qb-60']);

        $csv = tempnam(sys_get_temp_dir(), 'tsk').'.csv';
        file_put_contents($csv, implode("
", [
            '"Параметры.На дату ","Артикул ","Марка (бренд) ","Номенклатура ","Опт 1 Цена","Опт 2 Цена","VIP Цена","Розница Цена","Штрихкод","В резерве","Остаток в минске всего"',
            '"22.10.2026 23:59:59","28677","UNIPUMP","Станция авт.водоснабжения AUTO QB 70","311,32","324,18","302,81","421,95","4620015751634","","2,00"',
            '"22.10.2026 23:59:59","28600","UNIPUMP","Станция авт.водоснабжения AUTO QB 60","290,00","300,00","280,00","400,00","","","0,00"',
        ]));

        $this->artisan('supplier:sync-tsk-nasosy', ['--apply' => true, '--stock-file' => $csv])->assertSuccessful();

        $link = $station->supplierProducts()->sole();
        $this->assertSame('28677', $link->supplier_article);
        $this->assertSame('311.32', number_format((float) $link->price_byn, 2, '.', ''));
        $this->assertTrue((bool) $link->in_stock);
        $this->assertSame('preorder', $spare->supplierProducts()->sole()->stock_status);
    }
}
