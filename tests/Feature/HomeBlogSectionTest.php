<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeBlogSectionTest extends TestCase
{
    public function test_it_renders_every_supplied_recent_post_and_blog_archive_link(): void
    {
        $category = new BlogCategory([
            'name' => 'Тепловые насосы',
            'slug' => 'teplovye-nasosy',
        ]);

        $posts = collect(range(1, 6))->map(function (int $number) use ($category): BlogPost {
            $post = new BlogPost([
                'title' => "Свежая статья {$number}",
                'slug' => "svezhaya-statya-{$number}",
                'excerpt' => "Полезный материал {$number}",
                'published_at' => Carbon::parse("2026-10-0{$number} 12:00:00"),
                'is_published' => true,
            ]);

            return $post->setRelation('category', $category);
        });

        $html = view('partials.home-blog-section', compact('posts'))->render();

        $this->assertSame(6, substr_count($html, 'class="swiper-slide"'));
        $this->assertStringContainsString('Свежие статьи об отоплении', $html);
        $this->assertStringContainsString('href="'.route('blog').'"', $html);

        foreach ($posts as $post) {
            $this->assertStringContainsString($post->title, $html);
            $this->assertStringContainsString(route('blog.show', $post->slug), $html);
        }
    }

    public function test_it_hides_the_section_when_there_are_no_published_posts(): void
    {
        $html = view('partials.home-blog-section', ['posts' => collect()])->render();

        $this->assertSame('', trim($html));
    }
}
