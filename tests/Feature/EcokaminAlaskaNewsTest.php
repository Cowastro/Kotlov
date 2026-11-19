<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcokaminAlaskaNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_novelty_gets_its_news_post_description_and_banner_features(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'pechi-kaminy'], ['name' => 'Печи-камины', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Камин АЛЯСКА', 'slug' => 'kamin-aliaska', 'price' => 5844.99,
            'images' => ['img/products/ecokamin/alaska-1.jpg', 'img/products/ecokamin/alaska-2.jpg'],
            'specs' => [['key' => 'Тепловая мощность', 'value' => '12 кВт'], ['key' => 'Встроенная шиберная заслонка', 'value' => 'Нет']]]);

        (require database_path('migrations/2026_11_19_150000_publish_ecokamin_alaska_news.php'))->up();

        $card->refresh();
        $this->assertTrue((bool) $card->is_new);
        $this->assertSame('Камин ЭкоКамин АЛЯСКА с чугунной топкой на дровнике', $card->name);
        $this->assertSame('kamin-aliaska', $card->slug);
        $specs = collect($card->specs)->pluck('value', 'key');
        $this->assertSame('12 кВт', $specs['Тепловая мощность']);
        $this->assertSame('Да', $specs['Встроенная шиберная заслонка']);
        $this->assertSame('до 500 мм', $specs['Длина поленьев']);
        $this->assertStringContainsString('/blog/novinka-ekokamin-alyaska-kamin-s-chugunnoy-topkoy', $card->content);

        $post = BlogPost::query()->where('slug', 'novinka-ekokamin-alyaska-kamin-s-chugunnoy-topkoy')->firstOrFail();
        $this->assertTrue((bool) $post->is_published);
        $this->assertSame('img/products/ecokamin/alaska-1.jpg', $post->cover_image);
        $this->assertStringContainsString('href="/pechi-kaminy/kamin-aliaska"', $post->content);
        $this->assertStringNotContainsString('₽', $post->content);
    }
}
