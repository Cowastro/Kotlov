<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiContentEnricher
{
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
    }

    public function isAvailable(): bool
    {
        return (bool) $this->apiKey;
    }

    /**
     * Generate unique SEO-friendly HTML description for a product.
     *
     * Returns null on failure so callers can fall back to scraped content.
     */
    public function enrich(
        string $productName,
        string $brandName,
        ?string $rawContent,
        array $attributes = []
    ): ?string {
        if (! $this->apiKey) {
            return null;
        }

        $attrText = empty($attributes)
            ? ''
            : implode(', ', array_map(fn ($v, $k) => "$k: $v", $attributes, array_keys($attributes)));

        $rawSnippet = $rawContent
            ? mb_substr(strip_tags($rawContent), 0, 800)
            : '';

        $prompt = <<<PROMPT
Ты SEO-копирайтер для белорусского интернет-магазина kotlov.by (продажа печей, каминов, банных печей).
Напиши уникальное описание товара на русском языке в HTML-формате.

Товар: {$productName}
Бренд: {$brandName}
Характеристики: {$attrText}
Оригинальный текст поставщика (только справочно, не копировать дословно): {$rawSnippet}

Требования к тексту:
- Структура: 2 абзаца <p>, затем список <ul><li> с 4-5 преимуществами
- Первый абзац — назначение, для кого подходит, общее впечатление
- Второй абзац — особенности конструкции и материалов
- Список — конкретные преимущества этой модели
- Органично включи: купить в Минске, доставка по Беларуси, {$brandName}
- Только теги: <p> <ul> <li> <strong>
- Без вводных фраз ("Вот описание:", "Конечно!" и т.п.) — начинай сразу с текста
- Объём: 200–320 слов
PROMPT;

        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 1024,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if ($response->successful()) {
                return trim($response->json('content.0.text') ?? '') ?: null;
            }
        } catch (\Throwable) {
            // fall through — caller uses raw content
        }

        return null;
    }
}
