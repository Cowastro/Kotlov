<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortSectionProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_in_the_root_go_to_the_subcategory_their_title_names(): void
    {
        $root = Category::query()->updateOrCreate(['slug' => 'klimat'], ['name' => 'Климат', 'parent_id' => 0, 'is_active' => true]);
        $split = Category::query()->updateOrCreate(['slug' => 'kondicionery'], ['name' => 'Кондиционеры', 'parent_id' => $root->id, 'is_active' => true]);
        Category::query()->updateOrCreate(['slug' => 'mobilnye-kondicionery'], ['name' => 'Мобильные', 'parent_id' => $root->id, 'is_active' => true]);
        $card = fn (string $name) => Product::query()->create(['category_id' => $root->id, 'name' => $name, 'slug' => 'test-'.md5($name), 'sku' => 'T-'.md5($name), 'price' => 10]);
        $wall = $card('Сплит-система Electrolux EACS-09HAV/N8');
        $mobile = $card('Мобильный кондиционер Electrolux EACM-09');
        $unknown = $card('Житомир КНС-2');

        $this->artisan('catalog:sort-section-products', ['--section' => ['klimat']])
            ->expectsOutputToContain("audit.sort_section.sorted=klimat:{$wall->id}:kondicionery:")
            ->expectsOutputToContain("audit.sort_section.sorted=klimat:{$mobile->id}:mobilnye-kondicionery:")
            ->expectsOutputToContain("audit.sort_section.left=klimat:{$unknown->id}:zitomir-kns-2")
            ->assertSuccessful();
        $this->assertSame($root->id, (int) $wall->fresh()->category_id);

        $this->artisan('catalog:sort-section-products', ['--section' => ['klimat'], '--apply' => true])
            ->expectsOutputToContain('metric.sort_section.sorted=2')
            ->assertSuccessful();
        $this->assertSame($split->id, (int) $wall->fresh()->category_id);
        $this->assertSame($root->id, (int) $unknown->fresh()->category_id);
    }
}
