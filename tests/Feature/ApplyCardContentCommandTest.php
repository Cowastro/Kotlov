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

    public function test_wrong_rows_of_the_characteristics_table_are_corrected_by_name(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'kaminy-fix'], ['name' => 'Камины', 'is_active' => true]);
        $card = Product::query()->create(['name' => 'Камин РИО', 'slug' => 'kamin-rio-fix', 'category_id' => $category->id, 'price' => 100,
            'specs' => [['key' => 'Габариты (ВхШхГ)', 'value' => '970х610х400 мм'], ['key' => 'Цвет', 'value' => 'Чёрный']]]);
        $weight = \App\Models\Attribute::create(['category_id' => $category->id, 'name' => 'Вес', 'suffix' => 'кг', 'type' => 'value', 'in_product' => true]);
        $afterburn = \App\Models\Attribute::create(['category_id' => $category->id, 'name' => 'Вторичный дожиг', 'type' => 'select', 'in_product' => true]);
        $no = \App\Models\AttributeOption::create(['attribute_id' => $afterburn->id, 'name' => 'Нет']);
        \App\Models\ProductAttributeValue::create(['product_id' => $card->id, 'attribute_id' => $weight->id, 'value' => '93']);
        \App\Models\ProductAttributeValue::create(['product_id' => $card->id, 'attribute_id' => $afterburn->id, 'option_id' => $no->id]);
        $file = 'test_characteristics_fix';
        file_put_contents(database_path('data/'.$file.'.json'), json_encode(['kamin-rio-fix' => ['characteristics' => [
            'Габариты (ВхШхГ)' => '1331х577х468 мм', 'Вес' => '154 кг', 'Вторичный дожиг' => 'Да', 'Цвет' => 'Чёрный', 'Высота' => '1331 мм']]], JSON_UNESCAPED_UNICODE));

        try {
            $this->artisan('catalog:apply-card-content', ['file' => $file])
                ->expectsOutputToContain("audit.card_content.card={$card->id}:kamin-rio-fix:characteristics")
                ->expectsOutputToContain("audit.card_content.characteristic={$card->id}:ves:attribute")
                ->expectsOutputToContain("audit.card_content.characteristic={$card->id}:vysota:missing")->assertSuccessful();
            $this->assertSame('93', \App\Models\ProductAttributeValue::query()->where('attribute_id', $weight->id)->value('value'));

            $this->artisan('catalog:apply-card-content', ['file' => $file, '--apply' => true])->expectsOutputToContain('metric.card_content.updated=1')->assertSuccessful();
        } finally {
            @unlink(database_path('data/'.$file.'.json'));
        }
        $this->assertSame('154', \App\Models\ProductAttributeValue::query()->where('attribute_id', $weight->id)->value('value'));
        $this->assertSame('Да', \App\Models\ProductAttributeValue::query()->with('option')->where('attribute_id', $afterburn->id)->first()->option->name);
        $this->assertSame([['key' => 'Габариты (ВхШхГ)', 'value' => '1331х577х468 мм'], ['key' => 'Цвет', 'value' => 'Чёрный']], $card->fresh()->specs);
    }
}
