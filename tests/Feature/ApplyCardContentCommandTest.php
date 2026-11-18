<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyCardContentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_hatch_cards_get_content_meta_and_the_passport_drawing(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'lyuki-i-dozhdepriemniki'], ['name' => 'Люки', 'is_active' => true]);
        foreach (['liuk-polimerno-kompozitnyi-15-t-zelenyi-gamma-san', 'liuk-polimerno-kompozitnyi-3-t-zelenyi-gamma-san'] as $slug) {
            Product::query()->create(['name' => $slug, 'slug' => $slug, 'category_id' => $category->id, 'price' => 55,
                'images' => ['img/products/gamma-san/lyuk-polimerpeschanyi-1-5t-zelenyi.webp']]);
        }

        $this->artisan('catalog:apply-card-content', ['file' => 'gamma_san_lyuki_content'])
            ->expectsOutputToContain('metric.card_content.updated=0')->assertSuccessful();
        $this->artisan('catalog:apply-card-content', ['file' => 'gamma_san_lyuki_content', '--apply' => true])
            ->expectsOutputToContain('metric.card_content.updated=2')->assertSuccessful();

        $card = Product::query()->where('slug', 'liuk-polimerno-kompozitnyi-3-t-zelenyi-gamma-san')->first();
        $this->assertStringContainsString('26 ± 2 кг', $card->content);
        $this->assertStringContainsString('%city%', $card->meta_title);
        $this->assertSame('img/products/gamma-san/lyuk-polimerno-kompozitnyi-3t-chertezh.webp', $card->imagePaths()[1]);
        $small = Product::query()->where('slug', 'liuk-polimerno-kompozitnyi-15-t-zelenyi-gamma-san')->first();
        $this->assertStringContainsString('диаметр внизу — 800 ± 5 мм', $small->content);
        $this->assertContains(['key' => 'Диаметр обечайки внизу', 'value' => '800 мм'], $small->specs);

        $this->artisan('catalog:apply-card-content', ['file' => 'gamma_san_lyuki_content', '--apply' => true])
            ->expectsOutputToContain('metric.card_content.updated=0')->assertSuccessful();
    }

    public function test_a_card_gets_a_new_name(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'dymokhody-test'], ['name' => 'Дымоходы', 'is_active' => true]);
        $card = Product::query()->create(['name' => 'Труба 1 м', 'slug' => 'truba-1-m', 'category_id' => $category->id, 'price' => 180]);

        $this->artisan('catalog:apply-card-content', ['file' => 'metabel_names_2026_11', '--apply' => true])
            ->expectsOutputToContain("audit.card_content.card={$card->id}:truba-1-m:name")
            ->expectsOutputToContain('metric.card_content.updated=1')->assertSuccessful();

        $this->assertSame('Труба Мета-Бел 1 м', $card->fresh()->name);
        $this->assertSame('truba-1-m', $card->fresh()->slug);
    }
}
