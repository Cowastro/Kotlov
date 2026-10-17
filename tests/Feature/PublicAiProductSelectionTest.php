<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PublicAiSelectionRun;
use App\Services\AiContentEnricher;
use App\Services\PublicAiProductSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAiProductSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_uses_deepseek_analysis_but_only_server_defined_questions(): void
    {
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->andReturn(json_encode([
            'category' => 'gas_meter',
            'summary' => 'Нужен левый счётчик G4 длиной 110 мм.',
            'known' => ['flow' => 'G4', 'direction' => 'Левый', 'length' => '110', 'injected' => '<script>alert(1)</script>'],
            'questions' => [['key' => 'dangerous', 'label' => 'Вопрос модели']],
        ], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->start('Счётчик газа G4 левый, длина 110 мм');

        $this->assertSame('gas_meter', $result['category']);
        $this->assertSame(['flow' => 'G4', 'direction' => 'Левый', 'length' => '110'], $result['known']);
        $this->assertSame(['budget'], array_column($result['questions'], 'key'));
        $this->assertTrue($result['ai_used']);
    }

    public function test_recommendations_only_contain_real_active_catalog_products_and_ignore_hallucinated_ids(): void
    {
        $category = $this->category('nasosy', 'Насосы');
        $active = $this->product($category, 'Насос TEST 40', 'pump-test-40', 700);
        $second = $this->product($category, 'Насос TEST 60', 'pump-test-60', 950);
        $archived = $this->product($category, 'Архивный насос', 'archived-pump', 500, ['is_archived' => true]);

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->withArgs(function (string $prompt, int $tokens) use ($active, $second, $archived): bool {
            $this->assertStringContainsString('"id":'.$active->id, $prompt);
            $this->assertStringContainsString('"id":'.$second->id, $prompt);
            $this->assertStringNotContainsString('"id":'.$archived->id, $prompt);

            return $tokens === 1400;
        })->andReturn(json_encode(['matches' => [
            ['product_id' => 999999, 'role' => 'optimal', 'reason' => 'Выдуман'],
            ['product_id' => $active->id, 'role' => 'optimal', 'reason' => 'Есть нужный напор', 'confirmed' => ['Напор 40 м'], 'verify' => ['Рабочую точку']],
            ['product_id' => $archived->id, 'role' => 'reserve', 'reason' => 'Архив'],
            ['product_id' => $second->id, 'role' => 'reserve', 'reason' => 'Дешевле варианта ID 123 и есть запас по напору'],
        ]], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->recommend('Насос для дома с напором 40 метров', 'pump', ['budget' => '1000']);

        $this->assertSame([$active->id, $second->id], array_column($result['products'], 'id'));
        $this->assertTrue($result['ai_used']);
        $this->assertStringNotContainsString('ID 123', $result['products'][1]['reason']);
        $this->assertArrayNotHasKey('supplier', $result['products'][0]);
        $this->assertArrayNotHasKey('purchase_byn', $result['products'][0]);
    }

    public function test_it_falls_back_to_catalog_when_ai_is_unavailable(): void
    {
        $category = $this->category('schetchiki-gaza', 'Счётчики газа');
        $product = $this->product($category, 'Счётчик газа G4 левый L=110', 'meter-g4-left', 180);
        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(false);

        $result = app(PublicAiProductSelection::class)->recommend('Нужен счётчик газа G4 левый 110 мм', 'gas_meter', []);

        $this->assertFalse($result['ai_used']);
        $this->assertSame($product->id, $result['products'][0]['id']);
        $this->assertStringContainsString('DeepSeek временно не ответил', $result['summary']);
    }

    public function test_explicit_meter_size_direction_and_length_outrank_a_close_ai_alternative(): void
    {
        $category = $this->category('schetchiki-gaza', 'Счётчики газа');
        $exact = $this->product($category, 'Счётчик газа СГД G4 (левый) L=110', 'meter-g4-left-110', 200);
        $close = $this->product($category, 'Счётчик газа СГД G2.5 (левый) L=110', 'meter-g25-left-110', 180);

        $ai = $this->mock(AiContentEnricher::class);
        $ai->shouldReceive('isAvailable')->once()->andReturn(true);
        $ai->shouldReceive('complete')->once()->andReturn(json_encode(['matches' => [
            ['product_id' => $close->id, 'role' => 'optimal', 'reason' => 'Дешевле'],
            ['product_id' => $exact->id, 'role' => 'reserve', 'reason' => 'Точный типоразмер'],
        ]], JSON_UNESCAPED_UNICODE));

        $result = app(PublicAiProductSelection::class)->recommend(
            'Нужен счётчик газа G4 левый 110 мм',
            'gas_meter',
            ['flow' => 'G4', 'direction' => 'Левый', 'length' => '110']
        );

        $this->assertSame($exact->id, $result['products'][0]['id']);
        $this->assertSame('optimal', $result['products'][0]['role']);
        $this->assertSame($close->id, $result['products'][1]['id']);
    }

    public function test_public_endpoints_validate_input_and_store_anonymous_result_without_raw_session_id(): void
    {
        $this->postJson(route('ai-selection.start'), ['intent' => 'x'])->assertUnprocessable();

        $service = $this->mock(PublicAiProductSelection::class);
        $service->shouldReceive('recommend')->once()->andReturn([
            'category' => 'pump',
            'summary' => 'Готово',
            'products' => [['id' => 45, 'name' => 'Насос']],
            'ai_used' => true,
        ]);

        $response = $this->postJson(route('ai-selection.recommend'), [
            'intent' => 'Нужен насос для дома',
            'category' => 'pump',
            'answers' => ['budget' => '1000'],
        ]);

        $response->assertOk()->assertJsonPath('products.0.id', 45);
        $run = PublicAiSelectionRun::query()->firstOrFail();
        $this->assertSame('pump', $run->category);
        $this->assertSame([45], $run->result_product_ids);
        $this->assertSame(64, strlen($run->session_hash));
        $this->assertNotSame(session()->getId(), $run->session_hash);
    }

    public function test_storefront_layout_contains_global_ai_selector_and_delayed_teaser_script(): void
    {
        $html = view('partials.ai-product-selector')->render();
        $script = file_get_contents(public_path('assets/js/ai-product-selector.js'));

        $this->assertStringContainsString('Умный подбор', $html);
        $this->assertStringContainsString('data-ai-selector-open', $html);
        $this->assertStringContainsString('40000', $script);
        $this->assertStringContainsString('sevenDays', $script);
        $this->assertStringContainsString('cart|checkout|account|wishlist', $script);
    }

    private function category(string $slug, string $name): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true]
        );
    }

    private function product(Category $category, string $name, string $slug, float $price, array $extra = []): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => 'AI-'.strtoupper($slug),
            'price' => $price,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'manual_stock_management' => true,
            'stock_qty' => 3,
            'specs' => ['Максимальный напор' => '40 м'],
            ...$extra,
        ]);
    }
}
