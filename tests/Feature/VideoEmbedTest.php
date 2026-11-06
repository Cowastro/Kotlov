<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\VideoEmbed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoEmbedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pasted_youtube_iframe_becomes_its_embed_link(): void
    {
        $code = '<iframe width="1795" height="1010" src="https://www.youtube.com/embed/a3Vijwf9rlw" title="Чугунный камин Шаман" frameborder="0" allowfullscreen></iframe>';

        $this->assertSame('https://www.youtube.com/embed/a3Vijwf9rlw', VideoEmbed::normalize($code));
        $this->assertSame('https://youtu.be/a3Vijwf9rlw', VideoEmbed::normalize(' https://youtu.be/a3Vijwf9rlw '));
        $this->assertNull(VideoEmbed::normalize('просто текст'));
        $this->assertNull(VideoEmbed::normalize(''));
    }

    public function test_the_product_video_is_shown_in_the_description(): void
    {
        $category = Category::query()->create(['name' => 'Печи-камины', 'slug' => 'video-test-category', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Камин с видео', 'slug' => 'video-test-product', 'sku' => 'VIDEO-TEST-1', 'price' => 100,
            'content' => '<p>Описание камина</p>', 'video_url' => 'https://www.youtube.com/watch?v=a3Vijwf9rlw',
            'is_active' => true, 'is_archived' => false, 'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $html = $this->get('/'.$category->slug.'/'.$product->slug)->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'src="https://www.youtube.com/embed/a3Vijwf9rlw"'));
        $this->assertStringContainsString('Видео: Камин с видео', $html);
    }
}
