<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketWebResearchViewTest extends TestCase
{
    public function test_market_research_report_renders_as_readable_cards(): void
    {
        $html = view('filament.market.live-price-research', [
            'research' => [
                'status' => 'ready',
                'product_name' => 'Котёл Татра КТ30-НЛ1',
                'sku' => 'PS-011.367',
                'quote_price' => 3154,
                'confidence' => 'medium',
                'summary' => 'Найдено несколько предложений.',
                'verdict' => 'Цена находится в рынке.',
                'search_queries' => ['Татра КТ30 цена'],
                'model' => 'deepseek-v4-pro',
                'searched_at' => now(),
                'market' => [
                    'count' => 1,
                    'minimum' => 3154,
                    'median' => 3154,
                    'maximum' => 3154,
                    'quote_delta_percent' => 0,
                ],
                'offers' => [[
                    'seller' => 'Продавец',
                    'domain' => 'seller.by',
                    'title' => 'Котёл Татра КТ30-НЛ1',
                    'url' => 'https://seller.by/product',
                    'price' => 3154,
                    'currency' => 'BYN',
                    'price_byn' => 3154,
                    'availability' => 'В наличии',
                    'vat' => 'с НДС',
                    'package' => 'котёл',
                    'match' => 'exact',
                    'evidence' => 'Совпадают модель и мощность.',
                ]],
                'sources' => [[
                    'title' => 'Карточка товара',
                    'url' => 'https://seller.by/product',
                ]],
                'rejected_candidates' => [],
            ],
            'pricing' => [],
        ])->render();

        $this->assertStringContainsString('mr-offers', $html);
        $this->assertStringContainsString('Рекомендация менеджеру', $html);
        $this->assertStringContainsString('Почему засчитано:', $html);
        $this->assertStringContainsString('Нет закупочных данных.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }
}
