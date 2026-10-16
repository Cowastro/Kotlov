<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopTypographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_public_layout_loads_versioned_desktop_typography(): void
    {
        $this->get('/dostavka')->assertOk()->assertSee('assets/css/content-typography.css?v=', false);
    }

    public function test_product_description_and_specifications_use_shared_reading_style_without_content_changes(): void
    {
        $category = Category::create(['name' => 'Типографика', 'slug' => 'typography-test']);
        $content = '<h2>Модель</h2><h3>Комплект поставки</h3><p><strong>Гарантия:</strong> 36 месяцев.</p>';
        $product = Product::create(['name' => 'Проверка оформления', 'slug' => 'type-test', 'category_id' => $category->id, 'is_active' => true, 'price' => 100, 'content' => $content, 'specs' => [['key' => 'Мощность', 'value' => '500 ВА']]]);
        $this->get('/typography-test/type-test')->assertOk()
            ->assertSee('tab-content_desc site-prose', false)
            ->assertSee('<h3>Комплект поставки</h3>', false)
            ->assertSeeText('Технические характеристики');
        $this->assertSame($content, $product->fresh()->content);
    }

    public function test_every_new_rule_is_inside_desktop_media_query(): void
    {
        $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents(public_path('assets/css/content-typography.css')));
        $css = trim($css);
        $this->assertMatchesRegularExpression('/^@media\s*\(min-width:\s*992px\)\s*\{/', $css);
        $depth = 0;
        $opened = false;
        for ($i = 0; $i < strlen($css); $i++) {
            if ($css[$i] === '{') {
                $depth++;
                $opened = true;
            } elseif ($css[$i] === '}') {
                $depth--;
                $this->assertGreaterThanOrEqual(0, $depth);
            }
            if ($opened && $depth === 0) {
                $this->assertSame('', trim(substr($css, $i + 1)), 'No rules may escape the desktop-only media query.');
                break;
            }
        }
        $this->assertSame(0, $depth);
    }
}
