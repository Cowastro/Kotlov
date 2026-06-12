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

        $hasSpecs = ! empty($attributes);
        $hasShort = mb_strlen((string) $rawSnippet) > 20;

        if ($hasSpecs) {
            $dataBlock = "Реальные характеристики товара (используй только их):\n{$attrText}";
            $rules = <<<'RULES'
СТРОГИЕ ПРАВИЛА:
- Используй ТОЛЬКО характеристики из списка выше. Не придумывай параметры, которых нет.
- Конкретные числа (мощность, размеры, площадь и т.д.) — только из характеристик выше.
- Список преимуществ строго на основе предоставленных характеристик.
RULES;
        } elseif ($hasShort) {
            $dataBlock = "Краткое описание поставщика: {$rawSnippet}";
            $rules = <<<'RULES'
СТРОГИЕ ПРАВИЛА:
- Опирайся на краткое описание выше. Не придумывай конкретные технические числа.
- Описывай назначение и преимущества типа оборудования, не конкретные параметры модели.
RULES;
        } else {
            $dataBlock = '';
            $rules = <<<'RULES'
СТРОГИЕ ПРАВИЛА:
- Описывай только назначение и преимущества данного типа оборудования и бренда.
- НЕ указывай конкретные технические числа (мощность, размеры, КПД и т.д.) — они неизвестны.
- Описание должно быть честным и применимым к данной модели.
RULES;
        }

        $prompt = <<<PROMPT
Ты SEO-копирайтер для белорусского интернет-магазина kotlov.by (котлы, насосы, радиаторы, кондиционеры, климатическое оборудование).
Напиши описание товара на русском языке в HTML-формате.

Товар: {$productName}
Бренд: {$brandName}
{$dataBlock}

{$rules}

Структура:
- 2 абзаца <p>: первый — назначение и для кого, второй — достоинства
- список <ul><li> с 3–5 пунктами
- Органично включи: купить в %city%, доставка по Беларуси, {$brandName}
- Только теги: <p> <ul> <li> <strong>
- Без вводных фраз — начинай сразу с текста
- Объём: 150–250 слов
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
