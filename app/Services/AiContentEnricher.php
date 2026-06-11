<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Generates unique SEO product descriptions via any LLM provider.
 *
 * Priority:
 *   1. Anthropic  — set ANTHROPIC_API_KEY
 *   2. OpenAI-compatible (DeepSeek, Groq, Mistral, OpenAI, etc.)
 *        set AI_API_KEY + AI_API_URL + AI_MODEL
 *
 * Example .env for DeepSeek:
 *   AI_API_KEY=sk-...
 *   AI_API_URL=https://api.deepseek.com/chat/completions
 *   AI_MODEL=deepseek-chat
 *
 * Example .env for Groq (free):
 *   AI_API_KEY=gsk_...
 *   AI_API_URL=https://api.groq.com/openai/v1/chat/completions
 *   AI_MODEL=llama3-8b-8192
 */
class AiContentEnricher
{
    private string $mode;         // 'anthropic' | 'openai_compat' | 'none'
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct()
    {
        $anthropicKey = env('ANTHROPIC_API_KEY', '');
        $genericKey   = env('AI_API_KEY', '');

        if ($anthropicKey) {
            $this->mode   = 'anthropic';
            $this->apiKey = $anthropicKey;
            $this->apiUrl = 'https://api.anthropic.com/v1/messages';
            $this->model  = 'claude-haiku-4-5-20251001';
        } elseif ($genericKey && env('AI_API_URL')) {
            $this->mode   = 'openai_compat';
            $this->apiKey = $genericKey;
            $this->apiUrl = env('AI_API_URL');
            $this->model  = env('AI_MODEL', 'deepseek-chat');
        } else {
            $this->mode   = 'none';
            $this->apiKey = '';
            $this->apiUrl = '';
            $this->model  = '';
        }
    }

    public function isAvailable(): bool
    {
        return $this->mode !== 'none';
    }

    public function providerName(): string
    {
        return match ($this->mode) {
            'anthropic'    => 'Anthropic (' . $this->model . ')',
            'openai_compat' => $this->model . ' (' . parse_url($this->apiUrl, PHP_URL_HOST) . ')',
            default        => 'none',
        };
    }

    /**
     * Generate unique SEO HTML description. Returns null on failure.
     */
    public function enrich(
        string $productName,
        string $brandName,
        ?string $rawContent,
        array $attributes = []
    ): ?string {
        if ($this->mode === 'none') {
            return null;
        }

        $attrText   = empty($attributes)
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
Оригинальный текст поставщика (справочно, не копировать дословно): {$rawSnippet}

Требования:
- Структура: 2 абзаца <p>, затем список <ul><li> с 4-5 преимуществами
- Первый абзац — назначение и для кого подходит
- Второй абзац — особенности конструкции и материалов
- Список — конкретные преимущества этой модели
- Органично включи фразы: купить в %city%, доставка по Беларуси, {$brandName}
- Только теги: <p> <ul> <li> <strong>
- Без вводных фраз ("Вот описание:", "Конечно!" и т.п.) — начинай сразу с текста
- Объём: 200–320 слов
PROMPT;

        try {
            return match ($this->mode) {
                'anthropic'    => $this->callAnthropic($prompt),
                'openai_compat' => $this->callOpenAiCompat($prompt),
                default        => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Providers ─────────────────────────────────────────────────────────────────

    private function callAnthropic(string $prompt): ?string
    {
        $response = Http::timeout(45)
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        return $response->successful()
            ? (trim($response->json('content.0.text') ?? '') ?: null)
            : null;
    }

    private function callOpenAiCompat(string $prompt): ?string
    {
        $response = Http::timeout(45)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        return $response->successful()
            ? (trim($response->json('choices.0.message.content') ?? '') ?: null)
            : null;
    }
}
