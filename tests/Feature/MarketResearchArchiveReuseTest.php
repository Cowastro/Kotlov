<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Services\Market\MarketWebResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketResearchArchiveReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_research_is_attached_to_product_and_reused_with_current_quote_price(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'openai',
            'services.ai.openai_key' => 'test-key',
            'services.ai.openai_responses_url' => 'https://api.openai.test/v1/responses',
            'services.ai.openai_research_model' => 'gpt-6.1-sol',
        ]);
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'web_search_call',
                        'action' => ['sources' => [
                            ['title' => 'Wilo Беларусь', 'url' => 'https://seller.by/wilo-yonos-pico'],
                        ]],
                    ],
                    [
                        'type' => 'message',
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode([
                                'summary' => 'Найдено предложение Wilo.',
                                'verdict' => 'Использовать как рыночный ориентир.',
                                'confidence' => 'medium',
                                'search_queries' => ['Wilo Yonos PICO цена Беларусь'],
                                'offers' => [[
                                    'seller' => 'Продавец',
                                    'domain' => 'seller.by',
                                    'title' => 'Wilo Yonos PICO',
                                    'url' => 'https://seller.by/wilo-yonos-pico',
                                    'price' => 500,
                                    'currency' => 'BYN',
                                    'price_byn' => 500,
                                    'availability' => 'в наличии',
                                    'match' => 'exact',
                                    'evidence' => 'Совпадает точная модель.',
                                ]],
                                'rejected_candidates' => [],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                    ],
                ],
            ]),
        ]);

        $category = Category::query()->create([
            'name' => 'Насосы',
            'slug' => 'nasosy-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Wilo Yonos PICO 25/1-6',
            'slug' => 'wilo-yonos-pico-test',
            'sku' => 'WILO-TEST',
            'price' => 490,
            'currency' => 'BYN',
        ]);

        $service = app(MarketWebResearchService::class);
        $first = $service->research($product, 490);
        $second = $service->research($product, 550);

        $this->assertSame('ready', $first['status']);
        $this->assertFalse($first['archive']['reused']);
        $this->assertSame('ready', $second['status']);
        $this->assertTrue($second['archive']['reused']);
        $this->assertSame(550.0, $second['quote_price']);
        $this->assertSame(10.0, $second['market']['quote_delta_percent']);
        $this->assertInstanceOf(\DateTimeInterface::class, $second['searched_at']);
        $this->assertSame($first['archive']['id'], $second['archive']['id']);
        $this->assertSame(1, MarketResearchReport::query()->whereBelongsTo($product)->count());
        $this->assertSame('Wilo Yonos PICO 25/1-6', MarketResearchReport::query()->first()->research_payload['product_name']);
        Http::assertSentCount(1);
    }
}
