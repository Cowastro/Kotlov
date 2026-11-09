<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\SpotlightProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SpotlightPrimoTest extends TestCase
{
    use RefreshDatabase;

    public function test_primo_700_opens_its_section_with_the_gift_and_leads_the_home_slider(): void
    {
        Cache::flush();
        $category = Category::query()->create(['name' => 'Топки тест', 'slug' => 'spotlight-topki', 'is_active' => true]);
        $other = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Топка Альфа тестовая', 'slug' => 'spotlight-alfa',
            'sku' => 'SPOT-ALFA', 'price' => 1500, 'is_active' => true, 'is_archived' => false, 'in_stock' => true,
        ]);
        $primo = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Топка чугунная PRIMO 700', 'slug' => SpotlightProducts::PRIMO_700,
            'sku' => 'SPOT-PRIMO', 'price' => 2380, 'is_active' => true, 'is_archived' => false, 'in_stock' => true,
        ]);

        $this->get('/spotlight-topki')->assertOk()
            ->assertSeeInOrder(['card-product--spotlight', $primo->name, $other->name], false)
            ->assertSee('Решётка-гриль в подарок');

        $this->get('/spotlight-topki?sort=price_asc')->assertOk()
            ->assertSeeInOrder([$other->name, $primo->name]);

        $this->get('/spotlight-topki/'.$primo->slug)->assertOk()
            ->assertSee('spotlight-gift', false)
            ->assertSee('primo-700-gift.webp', false);

        $this->get('/')->assertOk()
            ->assertSee('primo-cover', false)
            ->assertSee('primo-cover.webp', false)
            ->assertSee('/spotlight-topki/'.SpotlightProducts::PRIMO_700, false)
            ->assertSee('/blog/ekokamin-primo-700-reshetka-gril-v-podarok', false);

        $this->get('/blog/ekokamin-primo-700-reshetka-gril-v-podarok')->assertOk()
            ->assertSee('решётка-гриль в подарок')
            ->assertSee('/topki/topka-cugunnaia-primo-700', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"Это Invicta Philos?"', false)
            ->assertSee('Чугунная каминная топка PRIMO 700 купить в Минске', false);
    }
}
