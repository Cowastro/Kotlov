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
    public function test_it_uses_hosted_web_search_and_only_accepts_offers_returned_as_sources(): void
    {
        config()->set([
            'services.ai.openai_key' => 'test-key',
            'services.ai.openai_responses_url' => 'https://api.openai.test/v1/responses',
            'services.ai.openai_research_model' => 'gpt-5.4',
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
                && $data['reasoning']['effort'] === 'high'
                && $data['return_token_budget'] === 'unlimited'
                && $data['max_output_tokens'] === 9000;
        });
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
