<?php

namespace Tests\Feature;

use App\Console\Commands\CreateChistyBeregCardsCommand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateChistyBeregCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_categories_narrow_to_ours_by_the_title(): void
    {
        $this->assertSame('tsirkulyatsionnyie', CreateChistyBeregCardsCommand::targetCategory('Насосы', 'Насос циркуляционный GHN 25/60-180 IMP Pumps Словения'));
        $this->assertSame('komplektuyushchie-dlya-nasosov', CreateChistyBeregCardsCommand::targetCategory('Насосы', 'Трос для скважинного насоса 3 мм'));
        $this->assertSame('elektricheskie', CreateChistyBeregCardsCommand::targetCategory('Котлы и дымоходы, автоматика', 'Котел электрический MK-S 21 кВт ZOTA Россия'));
        $this->assertSame('bimetallicheskie-radiatory', CreateChistyBeregCardsCommand::targetCategory('Отопительное оборудование', 'Радиатор биметаллический 500/80 10 секций'));
        $this->assertSame('obratnye-klapany', CreateChistyBeregCardsCommand::targetCategory('Клапаны', 'Клапан обратный пружинный 1/2"'));
        $this->assertSame('schetchiki-vody-i-tepla', CreateChistyBeregCardsCommand::targetCategory('Приборы учета и контроля', 'Счетчик воды крыльчатый Ду 15'));
        $this->assertNull(CreateChistyBeregCardsCommand::targetCategory('Трубы ПП для водоснабжения и фасонные части', 'Труба ПП 20'));
    }

    public function test_an_offer_becomes_a_card_with_bereg_photos_and_specs_and_is_shown(): void
    {
        Category::query()->create(['name' => 'Задвижки', 'slug' => 'zadvizhki', 'is_active' => true]);
        $supplier = Supplier::query()->where('code', 'chisty_bereg')->firstOrFail();
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'supplier_article' => '00134787', 'supplier_name' => 'Задвижка чугунная 30ч6бр Ру 10 Ду 50 Китай',
            'price_byn' => 117.01, 'in_stock' => true, 'stock_quantity' => 50, 'stock_status' => 'in_stock', 'match_status' => 'unmatched',
            'raw' => ['category' => 'Задвижки', 'brand' => '', 'maker_code' => '', 'retail' => 140.98],
        ]);
        $markdown = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'supplier_article' => '00199999', 'supplier_name' => 'Уценка Задвижка чугунная Ду 80',
            'match_status' => 'unmatched', 'raw' => ['category' => 'Задвижки', 'retail' => 50],
        ]);
        $page = '<h1>Задвижка чугунная 30ч6бр Ру 10 Ду 50</h1><div class="newchar">'
            .'<div class="product-page__char_row"><p class="product-page__char_prop">Артикул</p><p class="product-page__char_descr">00134787</p></div>'
            .'<div class="product-page__char_row"><p class="product-page__char_prop">Материал корпуса</p><p class="product-page__char_descr">чугун</p></div>'
            .'</div><div class="newdesc"><p>Задвижка для трубопроводов.</p></div>'
            .'<img src="/upload/aoptima_imagick_resizes/toBestFit_Y/w_500_h_500/blur_1/aa/image_1_00134787.webp">'
            .'<img src="/upload/aoptima_imagick_resizes/toBestFit_Y/w_40_h_40/blur_1/bb/image_1_00142911.webp">';
        Http::fake([
            'www.bereg.by/search/*' => Http::response('<a href="/product/zadvizhka_50/">'),
            'www.bereg.by/product/*' => Http::response($page),
            'www.bereg.by/upload/*' => Http::response(str_repeat('x', 10), 200, ['Content-Type' => 'image/webp']),
            '*' => Http::response('{}'),
        ]);

        $this->artisan('supplier:chisty-bereg-create')->expectsOutputToContain('audit.chisty_bereg_create.category=zadvizhki:1')->assertSuccessful();
        $this->assertNull($offer->fresh()->product_id);

        $this->artisan('supplier:chisty-bereg-create', ['--apply' => true])
            ->expectsOutputToContain('metric.chisty_bereg_create.created=1')
            ->expectsOutputToContain('metric.chisty_bereg_create.markdown=1')
            ->assertSuccessful();

        $card = Product::query()->where('sku', 'BEREG-00134787')->firstOrFail();
        $this->assertSame($card->id, $offer->fresh()->product_id);
        $this->assertSame('Задвижка чугунная 30ч6бр Ру 10 Ду 50', $card->name);
        $this->assertSame(140.98, (float) $card->price);
        $this->assertContains(['key' => 'Страна производства', 'value' => 'Китай'], $card->specs);
        $this->assertNull($markdown->fresh()->product_id);
    }
}
