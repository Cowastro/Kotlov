<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChimneyCategoryCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_podves_700_is_moved_to_chimney_mounts_with_a_permanent_redirect(): void
    {
        $oldCategory = Category::query()->firstOrCreate(
            ['slug' => 'zaglushki-dymohod'],
            ['name' => 'Заглушки и оголовки', 'parent_id' => 0],
        );
        $targetCategory = Category::query()->firstOrCreate(
            ['slug' => 'krepleniya-dymohod'],
            ['name' => 'Крепления и монтаж', 'parent_id' => 0],
        );
        $product = Product::query()->updateOrCreate(
            ['sku' => 'KOTLOV-008072'],
            [
                'category_id' => $oldCategory->id,
                'name' => 'Теплов и Сухов Крепление подвес 700 MC Black',
                'slug' => 'teplov-i-suxov-kreplenie-podves-700-mc-black-t-kraski-200s',
                'price' => 103.09,
            ],
        );

        $migration = require database_path(
            'migrations/2026_10_12_100000_move_teplov_sukhov_podves_700_to_chimney_mounts.php'
        );
        $migration->up();

        $this->assertSame($targetCategory->id, $product->fresh()->category_id);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/zaglushki-dymohod/teplov-i-suxov-kreplenie-podves-700-mc-black-t-kraski-200s',
            'to_url' => '/krepleniya-dymohod/teplov-i-suxov-kreplenie-podves-700-mc-black-t-kraski-200s',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }
}
