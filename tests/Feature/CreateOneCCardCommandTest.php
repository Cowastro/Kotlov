<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOneCCardCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_hatch_card_is_created_from_our_photo_and_linked(): void
    {
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        Category::query()->firstOrCreate(['slug' => 'lyuki-i-dozhdepriemniki'], ['name' => 'Люки и дождеприёмники', 'is_active' => true]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'onec-hatch',
            'external_code' => 'УТ-00009534',
            'name' => 'Люк 1,5т зеленый полимерно-композитный',
            'match_status' => 'unmatched',
            'stock_quantity' => 15,
        ]);
        $args = ['code' => 'УТ-00009534', 'category' => 'lyuki-i-dozhdepriemniki',
            '--title' => 'Люк полимерпесчаный 1,5 т зелёный', '--brand' => 'Гамма-Сан',
            '--image' => ['img/products/gamma-san/lyuk-polimerpeschanyi-1-5t-zelenyi.webp'],
            '--spec' => ['Нагрузка: 1,5 т', 'Цвет: зелёный']];

        $this->artisan('integration:create-onec-card', $args)->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-onec-card', $args + ['--apply' => true])->assertSuccessful();
        $product = $item->fresh()->product;
        $this->assertSame('Люк полимерпесчаный 1,5 т зелёный', $product->name);
        $this->assertSame('Гамма-Сан', $product->brand->name);
        $this->assertSame(['img/products/gamma-san/lyuk-polimerpeschanyi-1-5t-zelenyi.webp'], $product->imagePaths());
        $this->assertSame('/img/products/gamma-san/lyuk-polimerpeschanyi-1-5t-zelenyi.webp', $product->imageUrl());

        $this->artisan('integration:create-onec-card', ['code' => 'УТ-00009534', 'category' => 'lyuki-i-dozhdepriemniki', '--image' => ['img/none.webp']])
            ->expectsOutputToContain('skipped')->assertFailed();
    }
}
