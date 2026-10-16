<?php

namespace Tests\Feature;

use App\Console\Commands\PublishKotlovCeramicBoilerCommand;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\Kotlov95SeoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Kotlov95SeoArticleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_the_selection_block_and_keeps_commercial_and_stock_fields_untouched(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], ['name' => 'Пеллетные котлы', 'parent_id' => 0]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'KOTLOV 95',
            'sku' => PublishKotlovCeramicBoilerCommand::SKU, 'slug' => PublishKotlovCeramicBoilerCommand::SLUG,
            'price' => 25600, 'stock_qty' => 2, 'in_stock' => true, 'content' => '<p>Existing technical content</p>',
        ]);
        $before = $product->getAttributes();
        $migration = require database_path('migrations/2026_10_16_170000_publish_kotlov_95_boiler_selection_article.php');
        $migration->up();
        $migration->up();
        $content = $product->fresh()->content;
        $this->assertStringStartsWith('<p>Existing technical content</p>', $content);
        $this->assertSame(1, substr_count($content, 'data-kotlov-95-selection='));
        $after = $product->fresh()->getAttributes();
        foreach (array_diff(array_keys($before), ['content', 'updated_at']) as $key) {
            $this->assertEquals($before[$key], $after[$key], $key);
        }

        $post = BlogPost::query()->where('slug', Kotlov95SeoContent::ARTICLE_SLUG)->firstOrFail();
        $this->assertTrue($post->is_published);
        $this->assertStringContainsString('не менее 140 °C', $post->content);
        $this->assertStringContainsString('нижний зольник', $post->content);
        $this->assertStringContainsString(Kotlov95SeoContent::PRODUCT_URL, $post->content);
        $this->assertStringContainsString('blog-faq-item', $post->content);
        $this->get('/blog/'.Kotlov95SeoContent::ARTICLE_SLUG)->assertOk()
            ->assertSee('как подобрать и подготовить котельную')
            ->assertSee('installation-current-wide-original.jpg', false);

        $product->update(['content' => $content.'<p>User addition</p>']);
        $post->update(['content' => $post->content.'<p>Editorial addition</p>']);
        $migration->down();
        $this->assertStringContainsString('User addition', $product->fresh()->content);
        $this->assertStringNotContainsString('data-kotlov-95-selection=', $product->fresh()->content);
        $this->assertStringContainsString('Editorial addition', $post->fresh()->content);
    }

    public function test_existing_editorial_draft_is_never_overwritten_or_published(): void
    {
        BlogPost::query()->where('slug', Kotlov95SeoContent::ARTICLE_SLUG)->delete();
        $post = BlogPost::query()->create([
            'slug' => Kotlov95SeoContent::ARTICLE_SLUG, 'title' => 'Editorial draft',
            'content' => 'Unpublished user content', 'is_published' => false,
        ]);
        $before = $post->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_10_16_170000_publish_kotlov_95_boiler_selection_article.php');
        $migration->up();
        $migration->down();
        $this->assertSame($before, $post->fresh()->getAttributes());
    }
}
