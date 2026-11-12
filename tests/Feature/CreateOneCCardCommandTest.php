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

    public function test_model_without_a_1c_row_becomes_a_standalone_card(): void
    {
        Category::query()->firstOrCreate(['slug' => 'lyuki-i-dozhdepriemniki'], ['name' => 'Люки и дождеприёмники', 'is_active' => true]);
        $args = ['code' => 'lyuk-3t', 'category' => 'lyuki-i-dozhdepriemniki', '--without-row' => true,
            '--title' => 'Люк 3 т зелёный', '--brand' => 'Гамма-Сан', '--price' => '70',
            '--image' => ['img/products/gamma-san/lyuk-polimerpeschanyi-1-5t-zelenyi.webp'], '--apply' => true];

        $this->artisan('integration:create-onec-card', $args)->expectsOutputToContain('audit.onec_card.created=0:')->assertSuccessful();
        $product = \App\Models\Product::query()->where('name', 'Люк 3 т зелёный')->firstOrFail();
        $this->assertEquals(70, $product->price);
        $this->assertFalse((bool) $product->in_stock);
        $this->assertSame(0, IntegrationProduct::query()->where('product_id', $product->id)->count());

        unset($args['--title']);
        $this->artisan('integration:create-onec-card', $args)->expectsOutputToContain('skipped=lyuk-3t:no_title')->assertFailed();
    }

    public function test_a_variant_card_is_made_like_its_sibling(): void
    {
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $category = Category::query()->firstOrCreate(['slug' => 'teplovyie-nasosyi'], ['name' => 'Тепловые насосы', 'is_active' => true]);
        $sibling = \App\Models\Product::query()->create(['category_id' => $category->id, 'name' => 'Тепловой насос NL-FLM30-100II 10 кВт', 'slug' => 'nl-flm30-100ii',
            'price' => 13254, 'images' => ['img/products/gamma-san/lyuk-polimerpeschanyi-1-5t-zelenyi.webp'], 'content' => '<p>Описание</p>',
            'specs' => [['key' => 'Мощность', 'value' => '10 кВт'], ['key' => 'Электропитание', 'value' => '220 В, 1 фаза']]]);
        $item = IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'onec-pump-3f',
            'external_code' => 'УТ-00008740', 'name' => 'Тепловой насос NL-FLM30-100II 380V 3 phase', 'match_status' => 'unmatched']);

        $this->artisan('integration:create-onec-card', ['code' => 'УТ-00008740', 'category' => '-', '--like' => $sibling->id,
            '--title' => 'Тепловой насос NL-FLM30-100II 10 кВт, 380 В', '--price' => '13254',
            '--spec' => ['Электропитание: 380 В, 3 фазы'], '--apply' => true])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('<p>Описание</p>', $product->content);
        $this->assertSame($sibling->imagePaths(), $product->imagePaths());
        $this->assertSame([['key' => 'Мощность', 'value' => '10 кВт'], ['key' => 'Электропитание', 'value' => '380 В, 3 фазы']], $product->specs);
    }
}
