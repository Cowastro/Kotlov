<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplaceInProductNameCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_the_whole_fragment_in_name_h1_and_meta(): void
    {
        $category = Category::query()->create(['name' => 'Переходы', 'slug' => 'perehody-adaptery-dymohod', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Адаптер котла Теплов и Сухов АКМ AISI 304 0.8 150', 'h1' => 'Адаптер АКМ 304 (артикул 3040)',
            'meta_title' => 'Адаптер AISI 304 — купить', 'slug' => 'adapter-akm-304', 'price' => 17.6, 'is_active' => true,
        ]);

        $this->artisan('catalog:replace-in-name', ['product' => $product->id, 'from' => '304', 'to' => '430'])
            ->expectsOutputToContain("audit.replace_in_name.card={$product->id}:name.h1.meta_title")
            ->assertSuccessful();
        $this->assertStringContainsString('304', $product->fresh()->name);

        $this->artisan('catalog:replace-in-name', ['product' => $product->id, 'from' => '304', 'to' => '430', '--apply' => true])->assertSuccessful();

        $product->refresh();
        $this->assertSame('Адаптер котла Теплов и Сухов АКМ AISI 430 0.8 150', $product->name);
        $this->assertSame('Адаптер АКМ 430 (артикул 3040)', $product->h1);
        $this->assertSame('adapter-akm-304', $product->slug);
    }
}
