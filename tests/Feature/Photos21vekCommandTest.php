<?php

namespace Tests\Feature;

use App\Console\Commands\Photos21vekCommand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Photos21vekCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_21vek_names_read_as_our_cards(): void
    {
        $this->assertSame(Photos21vekCommand::key('Теплов и Сухов Отвод 87 ОМ-Р 430-0.5 D120 (3S)'),
            Photos21vekCommand::key('Поворот для труб Теплов и Сухов 87 ОМ-Р 430-0.5 D120 (3S)'));
        $this->assertSame(Photos21vekCommand::key('Теплов и Сухов Адаптер Моно М-М 430-0.5 D80'),
            Photos21vekCommand::key('Адаптер для труб Теплов и Сухов Моно М-М 430-0.5 D80 / TS.FRT.AKL.0080.62699'));
        $this->assertSame(Photos21vekCommand::key('Теплов и Сухов Зонт ЗМ-Р 430-0.5 D200'),
            Photos21vekCommand::key('Колпак для труб Теплов и Сухов ЗМ-Р 430-0.5 D200 / TS.FRT.ZNT.0200.74695'));
        $this->assertNotSame(Photos21vekCommand::key('Теплов и Сухов Отвод 87 ОМ-Р 430-0.5 D120 (3S)'),
            Photos21vekCommand::key('Поворот для труб Теплов и Сухов 87 ОМ-Р 430-0.8 D120 (3S)'));
    }

    public function test_it_lists_the_item_with_the_same_parameters_and_its_own_gallery(): void
    {
        $category = Category::query()->create(['name' => 'Отводы', 'slug' => 'kolena-mono', 'is_active' => true]);
        $product = Product::query()->create(['category_id' => $category->id, 'name' => 'Теплов и Сухов Отвод 87 ОМ-Р 430-0.5 D120 (3S)',
            'slug' => 'tis-otvod-120', 'sku' => 'KOTLOV-030300', 'price' => 30, 'is_active' => true, 'images' => ['product/001/001000/otvod.jpeg']]);
        $card = fn (string $url, string $name) => '<div itemType="https://schema.org/Product"><a href="'.$url.'"></a><span itemProp="name">'.$name.'</span></div>';
        Http::fake([
            'www.21vek.by/chimneys/all/teplov_syhov/page:*' => Http::response('<html></html>'),
            'www.21vek.by/chimneys/all/teplov_syhov/' => Http::response('<html>'
                .$card('/chimneys/8743005d1203s_teplov_syhov_9649748.html', 'Поворот для труб Теплов и Сухов 87 ОМ-Р 430-0.5 D120 (3S)')
                .$card('/chimneys/8743008d1203s_teplov_syhov_1.html', 'Поворот для труб Теплов и Сухов 87 ОМ-Р 430-0.8 D120 (3S)').'</html>'),
            'www.21vek.by/chimneys/8743005d1203s_teplov_syhov_9649748.html' => Http::response(
                '"https://cdn21vek.by/img/galleries/9649/748/8743005d1203s_teplov_syhov_9649748_a1.jpeg" '
                .'"https://cdn21vek.by/img/galleries/9603/864/43005d250_teplov_syhov_9603864_b2.webp"'),
        ]);

        $this->artisan('catalog:photos-21vek', ['--ids' => [$product->id]])
            ->expectsOutputToContain("audit.photos_21vek.card={$product->id}:tis-otvod-120:found:1")
            ->expectsOutputToContain('metric.photos_21vek.site_items=2')
            ->assertSuccessful();
    }
}
