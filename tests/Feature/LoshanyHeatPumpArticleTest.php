<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoshanyHeatPumpArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_loshany_heat_pump_case_is_published_with_seo_and_real_photos(): void
    {
        $slug = 'montazh-teplovogo-nasosa-16-kvt-r290-loshany';
        $post = BlogPost::query()->where('slug', $slug)->firstOrFail();

        $this->assertSame('Монтаж теплового насоса 16 кВт R290 в деревне Лошаны', $post->title);
        $this->assertSame('Тепловой насос 16 кВт R290 в Лошанах | Монтаж KOTLOV', $post->meta_title);
        $this->assertTrue($post->is_published);
        $this->assertCount(7, $post->images);

        $this->get('/blog/'.$slug)
            ->assertOk()
            ->assertSeeText('деревне Лошаны Минского района')
            ->assertSee('heatpump-loshany-cover.jpg', false)
            ->assertSee('heatpump-loshany-boiler-room.jpg', false)
            ->assertSee('/teplovyie-nasosyi/kotlov-ge-nl-flm50-160ii-r290-16-kvt', false)
            ->assertSee('/montazh-teplovyh-nasosov', false);

        foreach ($post->images as $image) {
            $this->assertFileExists(public_path($image));
        }

        [$width, $height] = getimagesize(public_path($post->cover_image));
        $this->assertSame(1600, $width);
        $this->assertSame(900, $height);
    }
}
