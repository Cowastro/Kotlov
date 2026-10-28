<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizeChimneyMountsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_subcategories_moves_products_and_preserves_old_urls(): void
    {
        $root = Category::query()->create(['name' => 'Крепления и монтаж', 'slug' => 'krepleniya-dymohod', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->create(['name' => 'Теплосъёмники', 'slug' => 'teplosyomniki', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->create(['name' => 'Переходы', 'slug' => 'perehody-adaptery-dymohod', 'parent_id' => 0, 'is_active' => true]);
        $fixtures = [
            'Площадка монтажная термо Ø150/250' => 'montazhnye-ploshchadki-dymohoda',
            'Хомут растяжки Ø200' => 'homuty-i-rastyazhki-dymohoda',
            'Крепление-подвес КП 700' => 'krepleniya-podvesy-i-shtangi-dymohoda',
            'ППУ составной FERRUM Ф200' => 'prohody-perekrytiy-i-potolochnye-razdelki',
            'Мастер-флеш RES №2 красный' => 'krovelnye-prohodki-master-flash',
            'Розета декоративная Ø150' => 'rozety-i-dekorativnye-elementy-dymohoda',
            'Базальтовая вата для ППУ' => 'teploizolyatsiya-i-zashchitnye-listy-dymohoda',
            'Уплотнение силиконовое D80' => 'uplotneniya-vtulki-i-flantsy-dymohoda',
            'Радиатор Darco RPZ130' => 'teplosyomniki',
            'Адаптер Моно М-М Ø80' => 'perehody-adaptery-dymohod',
        ];
        foreach ($fixtures as $name => $target) {
            Product::query()->create(['category_id' => $root->id, 'name' => $name, 'slug' => md5($name), 'price' => 10, 'is_active' => true]);
        }

        $this->artisan('catalog:organize-chimney-mounts', ['--apply' => true])
            ->expectsOutput('metric.chimney_mounts.direct=10')
            ->expectsOutput('metric.chimney_mounts.classified=10')
            ->expectsOutput('metric.chimney_mounts.unmatched=0')
            ->expectsOutput('metric.chimney_mounts.moved=10')
            ->assertSuccessful();

        foreach ($fixtures as $name => $target) {
            $product = Product::query()->where('name', $name)->firstOrFail();
            $this->assertSame($target, $product->category?->slug);
            $this->assertDatabaseHas('redirects', [
                'from_url' => '/krepleniya-dymohod/'.$product->slug,
                'to_url' => '/'.$target.'/'.$product->slug,
                'status_code' => 301,
            ]);
        }
    }

    public function test_preview_does_not_move_products(): void
    {
        $root = Category::query()->create(['name' => 'Крепления', 'slug' => 'krepleniya-dymohod', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->create(['name' => 'Теплосъёмники', 'slug' => 'teplosyomniki', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->create(['name' => 'Переходы', 'slug' => 'perehody-adaptery-dymohod', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::query()->create(['category_id' => $root->id, 'name' => 'Хомут трубный Ø150', 'slug' => 'preview-clamp', 'price' => 10]);

        $this->artisan('catalog:organize-chimney-mounts')->assertSuccessful();

        $this->assertSame($root->id, $product->fresh()->category_id);
    }
}
