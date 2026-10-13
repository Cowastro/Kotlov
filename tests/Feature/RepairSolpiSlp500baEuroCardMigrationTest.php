<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairSolpiSlp500baEuroCardMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_the_existing_euro_card_without_changing_its_market_price(): void
    {
        $category = Category::query()->create([
            'name' => 'Стабилизаторы напряжения',
            'slug' => 'stabilizatory-napryazheniya-solpi-test',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-002.366',
            'name' => 'Стабилизатор напряжения Solpi-M SLP-500BA',
            'slug' => 'solpi-m-slp-500ba',
            'price' => 180,
            'content' => '<p>Старое описание с плавным регулированием.</p>',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_10_13_000300_repair_solpi_slp_500ba_euro_card.php');
        $migration->up();

        $product->refresh();
        $this->assertSame('Стабилизатор напряжения Solpi-M SLP-500BA EURO, 500 ВА, 2 розетки', $product->name);
        $this->assertSame('180.00', $product->price);
        $this->assertSame('Электронно-релейный стабилизатор', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('2', collect($product->specs)->firstWhere('key', 'Количество розеток')['value']);
        $this->assertSame('140–260 В', collect($product->specs)->firstWhere('key', 'Диапазон входного напряжения при 220 В')['value']);
        $this->assertStringContainsString('gig.by', (string) $product->content_source_url);
        $this->assertStringNotContainsString('плавным регулированием', (string) $product->content);
        $this->assertTrue($product->is_active);
        $this->assertFalse($product->is_archived);
    }
}
