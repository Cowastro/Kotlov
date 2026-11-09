<?php

namespace Tests\Feature;

use App\Console\Commands\RewriteTemplateDescriptionsCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\AiContentEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewriteTemplateDescriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_descriptions_are_counted_and_rewritten(): void
    {
        $category = Category::query()->create(['name' => 'Насосы', 'slug' => 'template-test', 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Wilo', 'slug' => 'wilo-test']);
        $template = Product::query()->create([
            'category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Насос Wilo Star-RS 25/4', 'slug' => 'wilo-star-rs-25-4-test',
            'price' => 300, 'is_active' => true, 'is_archived' => false,
            'specs' => [['key' => 'Напор', 'value' => '4 м'], ['key' => 'Штрихкод', 'value' => '123']],
            'content' => '<p>Насос Wilo Star-RS 25/4 — комплектующее для инженерной системы бренда Wilo.</p><p>'.RewriteTemplateDescriptionsCommand::MARKER.'</p>',
        ]);
        $normal = Product::query()->create([
            'category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Насос Wilo Yonos', 'slug' => 'wilo-yonos-test',
            'price' => 400, 'is_active' => true, 'is_archived' => false, 'content' => '<p>Хорошее описание.</p>',
        ]);

        $this->app->instance(AiContentEnricher::class, new class extends AiContentEnricher
        {
            public array $specs = [];

            public function isAvailable(): bool
            {
                return true;
            }

            public function enrich(string $productName, string $brandName, ?string $rawContent, array $attributes = []): ?string
            {
                $this->specs = $attributes;

                return '<p>'.$productName.' — циркуляционный насос для отопления. '.str_repeat('Надёжный и тихий. ', 25).'</p><ul><li>Напор 4 м</li></ul>';
            }

            public function shortDescription(string $productName, string $brandName, array $specs = []): ?string
            {
                return $productName.' — циркуляционный насос с напором 4 м.';
            }
        });

        $this->artisan('catalog:rewrite-template-descriptions')
            ->expectsOutputToContain('audit.template_descriptions.brand=wilo-test:1')
            ->expectsOutputToContain('metric.template_descriptions.total=1')
            ->assertSuccessful();

        $this->artisan('catalog:rewrite-template-descriptions --apply')
            ->expectsOutputToContain('metric.template_descriptions.rewritten=1')
            ->assertSuccessful();

        $this->assertStringContainsString('циркуляционный насос', $template->fresh()->content);
        $this->assertStringNotContainsString('комплектующее', $template->fresh()->content);
        $this->assertSame('Насос Wilo Star-RS 25/4 — циркуляционный насос с напором 4 м.', $template->fresh()->short_description);
        $this->assertSame('<p>Хорошее описание.</p>', $normal->fresh()->content);
        $this->assertSame([['key' => 'Напор', 'value' => '4 м']], app(AiContentEnricher::class)->specs);
    }
}
