<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Market\MarketWebResearchService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketWebResearchServiceTest extends TestCase
{
    public function test_it_can_research_a_manager_query_before_a_catalog_product_exists(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'openai',
            'services.ai.openai_key' => 'test-key',
            'services.ai.openai_responses_url' => 'https://api.openai.test/v1/responses',
            'services.ai.openai_research_model' => 'gpt-6.1-sol',
        ]);
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Найден поставщик.',
                            'verdict' => 'Проверить предложение.',
                            'confidence' => 'medium',
                            'search_queries' => ['промышленный насос Беларусь'],
                            'offers' => [],
                            'rejected_candidates' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
            ]),
        ]);

        $result = app(MarketWebResearchService::class)->researchQuery('Промышленный насос 50 м³/ч');

        $this->assertSame('ready', $result['status']);
        $this->assertSame('Промышленный насос 50 м³/ч', $result['product_name']);
        $this->assertNull($result['quote_price']);
        $this->assertNull($result['market']['quote_delta_percent']);
        Http::assertSent(fn (Request $request): bool => str_contains(
            (string) $request->data()['input'],
            'Цена в коммерческом предложении: ещё не назначена',
        ));
    }

    public function test_it_uses_hosted_web_search_and_only_accepts_offers_returned_as_sources(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'openai',
            'services.ai.openai_key' => 'test-key',
            'services.ai.openai_responses_url' => 'https://api.openai.test/v1/responses',
            'services.ai.openai_research_model' => 'gpt-6.1-sol',
            'services.ai.openai_model' => 'gpt-4.1',
        ]);

        $report = [
            'summary' => 'Три сопоставимых предложения подтверждены.',
            'verdict' => 'Цена КП находится в рынке.',
            'confidence' => 'high',
            'search_queries' => ['Татра КТ30 НЛ1 цена Беларусь'],
            'offers' => [
                $this->offer('Магазин 1', 'https://one.by/tatra-kt30', 3000),
                $this->offer('Магазин 2', 'https://two.by/catalog/tatra-kt30?utm_source=search', 3200),
                $this->offer('Магазин 3', 'https://three.by/tatra-kt30/', 3400),
                $this->offer('Выдуманный магазин', 'https://invented.by/tatra-kt30', 1000),
            ],
            'rejected_candidates' => [[
                'title' => 'КТ30 правый',
                'url' => 'https://other.by/right-version',
                'reason' => 'Другая сторона подключения.',
            ]],
        ];

        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'web_search_call',
                        'action' => ['sources' => [
                            ['title' => 'Карточка 1', 'url' => 'https://one.by/tatra-kt30'],
                            ['title' => 'Карточка 2', 'url' => 'https://two.by/catalog/tatra-kt30'],
                            ['title' => 'Карточка 3', 'url' => 'https://three.by/tatra-kt30'],
                            ['title' => 'Похожая модель', 'url' => 'https://other.by/right-version'],
                        ]],
                    ],
                    [
                        'type' => 'message',
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]],
                    ],
                ],
            ]),
        ]);

        $product = new Product([
            'name' => 'Твердотопливный котел Татра КТ30-НЛ1 Сталь 6мм левый одноконтурный',
            'sku' => 'PS-011.367',
            'specs' => [['key' => 'Толщина стали', 'value' => '6 мм']],
        ]);
        $product->setRelation('brand', new Brand(['name' => 'Татра']));
        $product->setRelation('category', new Category(['name' => 'Твердотопливные котлы']));

        $result = app(MarketWebResearchService::class)->research($product, 3154);

        $this->assertSame('ready', $result['status']);
        $this->assertSame(3, $result['market']['count']);
        $this->assertSame(3200.0, $result['market']['median']);
        $this->assertSame(-1.4, $result['market']['quote_delta_percent']);
        $this->assertCount(3, $result['offers']);
        $this->assertNotContains('invented.by', array_column($result['offers'], 'domain'));
        $this->assertCount(4, $result['sources']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $data['store'] === false
                && $data['tools'][0]['type'] === 'web_search'
                && $data['tools'][0]['search_context_size'] === 'high'
                && $data['tools'][0]['return_token_budget'] === 'unlimited'
                && $data['tool_choice'] === 'required'
                && $data['reasoning']['effort'] === 'max'
                && $data['max_output_tokens'] === 16000;
        });
    }

    public function test_it_supports_official_azure_openai_responses_and_web_search(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'azure',
            'services.ai.azure_openai_key' => 'azure-secret-key',
            'services.ai.azure_openai_responses_url' => 'https://example.openai.azure.com/openai/v1/responses',
            'services.ai.azure_openai_research_model' => 'market-research-deployment',
        ]);

        Http::fake([
            'https://example.openai.azure.com/openai/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Предложений не найдено.',
                            'verdict' => 'Нужна ручная проверка.',
                            'confidence' => 'low',
                            'search_queries' => ['тестовый товар цена Беларусь'],
                            'offers' => [],
                            'rejected_candidates' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
            ]),
        ]);

        $result = app(MarketWebResearchService::class)->research(new Product([
            'name' => 'Тестовый товар',
            'sku' => 'TEST-1',
        ]), 100);

        $this->assertSame('ready', $result['status']);
        $this->assertSame('market-research-deployment', $result['model']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->hasHeader('api-key', 'azure-secret-key')
                && ! $request->hasHeader('Authorization')
                && $data['model'] === 'market-research-deployment'
                && $data['tools'][0]['type'] === 'web_search'
                && ! array_key_exists('external_web_access', $data['tools'][0])
                && ! array_key_exists('return_token_budget', $data['tools'][0]);
        });
    }

    public function test_it_supports_deepseek_pro_native_web_search_and_validates_sources(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'deepseek',
            'services.ai.api_key' => 'deepseek-secret-key',
            'services.ai.deepseek_anthropic_url' => 'https://api.deepseek.test/anthropic/v1/messages',
            'services.ai.deepseek_research_model' => 'deepseek-v4-pro',
            'services.ai.deepseek_research_fallback_model' => 'deepseek-flash',
            'services.ai.market_research_safe_mode' => false,
        ]);

        $report = [
            'summary' => 'Найдено одно подтверждённое предложение.',
            'verdict' => 'Нужна дополнительная проверка.',
            'confidence' => 'low',
            'search_queries' => ['тестовый товар цена'],
            'offers' => [
                $this->offer('Магазин', 'https://seller.by/product', 120),
                $this->offer('Выдуманный', 'https://invented.by/product', 80),
            ],
            'rejected_candidates' => [],
        ];

        Http::fake([
            'https://api.deepseek.test/anthropic/v1/messages' => Http::response([
                'type' => 'message',
                'model' => 'deepseek-v4-pro',
                'content' => [
                    [
                        'type' => 'web_search_tool_result',
                        'content' => [[
                            'type' => 'web_search_result',
                            'title' => 'Карточка товара',
                            'url' => 'https://seller.by/product',
                        ]],
                    ],
                    [
                        'type' => 'text',
                        'text' => "```json\n".json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n```",
                    ],
                ],
            ]),
        ]);

        $result = app(MarketWebResearchService::class)->research(new Product([
            'name' => 'Тестовый товар',
            'sku' => 'TEST-2',
        ]), 100);

        $this->assertSame('ready', $result['status']);
        $this->assertSame('deepseek-v4-pro', $result['model']);
        $this->assertCount(1, $result['offers']);
        $this->assertSame('seller.by', $result['offers'][0]['domain']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->hasHeader('x-api-key', 'deepseek-secret-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && ! $request->hasHeader('Authorization')
                && $data['model'] === 'deepseek-v4-pro'
                && $data['tools'][0]['type'] === 'web_search_20250305'
                && $data['tools'][0]['name'] === 'web_search'
                && $data['tools'][0]['max_uses'] === 12
                && $data['max_tokens'] === 16000;
        });
    }

    public function test_it_falls_back_to_deepseek_flash_when_pro_returns_unstructured_text(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'deepseek',
            'services.ai.api_key' => 'deepseek-secret-key',
            'services.ai.deepseek_anthropic_url' => 'https://api.deepseek.test/anthropic/v1/messages',
            'services.ai.deepseek_research_model' => 'deepseek-v4-pro',
            'services.ai.deepseek_research_fallback_model' => 'deepseek-flash',
            'services.ai.market_research_safe_mode' => false,
        ]);

        Http::fakeSequence('https://api.deepseek.test/anthropic/v1/messages')
            ->push(['content' => [['type' => 'text', 'text' => 'Не JSON']]])
            ->push(['content' => [['type' => 'text', 'text' => json_encode([
                'summary' => 'Рынок не найден.',
                'verdict' => 'Нужна ручная проверка.',
                'confidence' => 'low',
                'search_queries' => ['TEST-3'],
                'offers' => [],
                'rejected_candidates' => [],
            ], JSON_UNESCAPED_UNICODE)]]]);

        $result = app(MarketWebResearchService::class)->research(new Product([
            'name' => 'Тестовый товар',
            'sku' => 'TEST-3',
        ]), 100);

        $this->assertSame('ready', $result['status']);
        $this->assertSame('deepseek-flash', $result['model']);

        Http::assertSentCount(2);
    }

    public function test_safe_mode_uses_flash_limits_search_and_reuses_cached_result(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'deepseek',
            'services.ai.api_key' => 'deepseek-secret-key',
            'services.ai.deepseek_anthropic_url' => 'https://api.deepseek.test/anthropic/v1/messages',
            'services.ai.deepseek_research_model' => 'deepseek-v4-pro',
            'services.ai.deepseek_research_fallback_model' => 'deepseek-flash',
            'services.ai.market_research_safe_mode' => true,
            'services.ai.market_research_max_search_uses' => 2,
            'services.ai.market_research_max_output_tokens' => 2500,
            'services.ai.market_research_cache_minutes' => 360,
        ]);

        Http::fake([
            'https://api.deepseek.test/anthropic/v1/messages' => Http::response([
                'type' => 'message',
                'model' => 'deepseek-flash',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'summary' => 'Поставщики не найдены.',
                        'verdict' => 'Нужна ручная проверка.',
                        'confidence' => 'low',
                        'search_queries' => ['TEST-SAFE'],
                        'offers' => [],
                        'rejected_candidates' => [],
                    ], JSON_UNESCAPED_UNICODE),
                ]],
            ]),
        ]);

        $product = new Product(['name' => 'Безопасный поиск', 'sku' => 'TEST-SAFE']);
        $first = app(MarketWebResearchService::class)->research($product, 100);
        $second = app(MarketWebResearchService::class)->research($product, 100);

        $this->assertSame('ready', $first['status']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $data['model'] === 'deepseek-flash'
                && $data['tools'][0]['max_uses'] === 2
                && $data['max_tokens'] === 2500
                && $data['reasoning']['effort'] === 'none';
        });
    }

    public function test_safe_mode_repairs_unstructured_flash_response_without_another_web_search(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'deepseek',
            'services.ai.api_key' => 'deepseek-secret-key',
            'services.ai.deepseek_anthropic_url' => 'https://api.deepseek.test/anthropic/v1/messages',
            'services.ai.deepseek_research_model' => 'deepseek-v4-pro',
            'services.ai.deepseek_research_fallback_model' => 'deepseek-flash',
            'services.ai.market_research_safe_mode' => true,
        ]);

        $report = [
            'summary' => 'Найдено предложение.',
            'verdict' => 'Проверить цену.',
            'confidence' => 'low',
            'search_queries' => ['TEST-REPAIR'],
            'offers' => [$this->offer('Магазин', 'https://seller.by/product', 120)],
            'rejected_candidates' => [],
        ];

        Http::fakeSequence('https://api.deepseek.test/anthropic/v1/messages')
            ->push([
                'content' => [
                    [
                        'type' => 'web_search_tool_result',
                        'content' => [[
                            'type' => 'web_search_result',
                            'title' => 'Карточка товара',
                            'url' => 'https://seller.by/product',
                        ]],
                    ],
                    ['type' => 'text', 'text' => 'Нашёл предложение, но отвечаю обычным текстом.'],
                ],
            ])
            ->push([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
            ]);

        $result = app(MarketWebResearchService::class)->research(new Product([
            'name' => 'Безопасное исправление',
            'sku' => 'TEST-REPAIR',
        ]), 100);

        $this->assertSame('ready', $result['status']);
        $this->assertSame('deepseek-flash', $result['model']);
        $this->assertCount(1, $result['offers']);
        Http::assertSentCount(2);

        $requests = Http::recorded();
        $repairPayload = $requests[1][0]->data();
        $this->assertSame('deepseek-flash', $repairPayload['model']);
        $this->assertSame(2000, $repairPayload['max_tokens']);
        $this->assertSame('none', $repairPayload['reasoning']['effort']);
        $this->assertArrayNotHasKey('tools', $repairPayload);
    }

    public function test_safe_mode_returns_found_sources_when_json_repair_also_fails(): void
    {
        config()->set([
            'services.ai.market_research_provider' => 'deepseek',
            'services.ai.api_key' => 'deepseek-secret-key',
            'services.ai.deepseek_anthropic_url' => 'https://api.deepseek.test/anthropic/v1/messages',
            'services.ai.deepseek_research_fallback_model' => 'deepseek-flash',
            'services.ai.market_research_safe_mode' => true,
        ]);

        Http::fakeSequence('https://api.deepseek.test/anthropic/v1/messages')
            ->push([
                'content' => [
                    [
                        'type' => 'web_search_tool_result',
                        'content' => [[
                            'type' => 'web_search_result',
                            'title' => 'Карточка товара',
                            'url' => 'https://seller.by/fallback',
                        ]],
                    ],
                    ['type' => 'text', 'text' => 'Ответ без JSON.'],
                ],
            ])
            ->push(['content' => [['type' => 'text', 'text' => 'JSON снова не получен.']]]);

        $result = app(MarketWebResearchService::class)->research(new Product([
            'name' => 'Поиск с резервным отображением',
            'sku' => 'TEST-SOURCES-ONLY',
        ]), 100);

        $this->assertSame('ready', $result['status']);
        $this->assertSame('low', $result['confidence']);
        $this->assertCount(1, $result['sources']);
        $this->assertSame([], $result['offers']);
        $this->assertStringContainsString('проверьте цену вручную', $result['verdict']);
        Http::assertSentCount(2);
    }

    /** @return array<string, mixed> */
    private function offer(string $seller, string $url, float $price): array
    {
        return [
            'seller' => $seller,
            'domain' => parse_url($url, PHP_URL_HOST),
            'title' => 'Татра КТ30-НЛ1',
            'url' => $url,
            'price' => $price,
            'currency' => 'BYN',
            'price_byn' => $price,
            'availability' => 'в наличии',
            'vat' => 'с НДС',
            'package' => 'котёл',
            'match' => 'exact',
            'evidence' => 'Совпадают модель, исполнение и толщина стали.',
        ];
    }
}
