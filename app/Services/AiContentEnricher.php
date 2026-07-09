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
        $anthropicKey = config('services.ai.anthropic_key', '');
        $genericKey   = config('services.ai.api_key', '');

        if ($anthropicKey) {
            $this->mode   = 'anthropic';
            $this->apiKey = $anthropicKey;
            $this->apiUrl = 'https://api.anthropic.com/v1/messages';
            $this->model  = 'claude-haiku-4-5-20251001';
        } elseif ($genericKey && config('services.ai.api_url')) {
            $this->mode   = 'openai_compat';
            $this->apiKey = $genericKey;
            $this->apiUrl = config('services.ai.api_url');
            $this->model  = config('services.ai.model', 'deepseek-chat');
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

    public function withModel(?string $model): self
    {
        $model = trim((string) $model);
        if ($model === '' || $this->mode !== 'openai_compat') {
            return $this;
        }

        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    public function withOpenAi(?string $model = null): self
    {
        $key = (string) env('OPENAI_API_KEY', '');
        if ($key === '') {
            return $this;
        }

        $clone = clone $this;
        $clone->mode = 'openai_compat';
        $clone->apiKey = $key;
        $clone->apiUrl = (string) env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
        $clone->model = trim((string) $model) !== ''
            ? trim((string) $model)
            : (string) env('OPENAI_MATCH_MODEL', env('OPENAI_MODEL', 'gpt-5.5'));

        return $clone;
    }

    public function complete(string $prompt, int $maxTokens = 700): ?string
    {
        if ($this->mode === 'none') {
            return null;
        }

        try {
            return match ($this->mode) {
                'anthropic' => $this->callAnthropic($prompt, $maxTokens),
                'openai_compat' => $this->callOpenAiCompat($prompt, $maxTokens),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
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

        // Support both [{key,value,unit}] and {key:value} formats
        $flatAttrs = [];
        foreach ($attributes as $k => $v) {
            if (is_array($v)) {
                $key = $v['key'] ?? $k;
                $val = $v['value'] ?? '';
                $unit = $v['unit'] ?? '';
                if ($key !== '' && $val !== '') {
                    $flatAttrs[$key] = $val . ($unit ? ' ' . $unit : '');
                }
            } else {
                $flatAttrs[$k] = $v;
            }
        }
        $attrText = empty($flatAttrs)
            ? ''
            : implode(', ', array_map(fn ($v, $k) => "$k: $v", $flatAttrs, array_keys($flatAttrs)));

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
- НЕ указывай страну производства или происхождение бренда — эта информация не предоставлена.
RULES;
        } elseif ($hasShort) {
            $dataBlock = "Краткое описание поставщика: {$rawSnippet}";
            $rules = <<<'RULES'
СТРОГИЕ ПРАВИЛА:
- Опирайся на краткое описание выше. Не придумывай конкретные технические числа.
- Описывай назначение и преимущества типа оборудования, не конкретные параметры модели.
- НЕ указывай страну производства или происхождение бренда — эта информация не предоставлена.
RULES;
        } else {
            $dataBlock = '';
            $rules = <<<'RULES'
СТРОГИЕ ПРАВИЛА:
- Описывай только назначение и преимущества данного типа оборудования и бренда.
- НЕ указывай конкретные технические числа (мощность, размеры, КПД и т.д.) — они неизвестны.
- Описание должно быть честным и применимым к данной модели.
- НЕ указывай страну производства или происхождение бренда — эта информация не предоставлена.
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
- ОБЯЗАТЕЛЬНО вставь ДОСЛОВНО строку «%city%» как плейсхолдер города (система заменит его автоматически): например «купить {$brandName} в %city%» или «заказать в %city% с доставкой по Беларуси» — НЕ заменяй %city% реальным словом
- доставка по Беларуси, {$brandName}
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

    /**
     * Generate a short 1–2 sentence product description (plain text, no HTML).
     */
    public function shortDescription(string $productName, string $brandName, array $specs = []): ?string
    {
        if ($this->mode === 'none') {
            return null;
        }

        $flatSpecs = [];
        foreach ($specs as $k => $v) {
            if (is_array($v)) {
                $key = $v['key'] ?? $k;
                $val = ($v['value'] ?? '') . ($v['unit'] ?? '' ? ' ' . $v['unit'] : '');
                if ($key !== '' && trim($val) !== '') {
                    $flatSpecs[] = "$key: $val";
                }
            } else {
                $flatSpecs[] = "$k: $v";
            }
        }
        $specsLine = empty($flatSpecs) ? '' : 'Характеристики: ' . implode(', ', $flatSpecs);

        $prompt = <<<PROMPT
Напиши краткое описание товара для белорусского интернет-магазина kotlov.by — 1–2 предложения, обычный текст без HTML-тегов.
Описание должно отражать суть и назначение товара. Без упоминания России, Москвы, телефонов и сторонних сайтов.

Товар: {$productName}
Бренд: {$brandName}
{$specsLine}

Только текст, без кавычек, без HTML.
PROMPT;

        try {
            return match ($this->mode) {
                'anthropic'    => $this->callAnthropic($prompt),
                'openai_compat' => $this->callOpenAiCompat($prompt),
                default        => null,
            };
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AiContentEnricher::enrich failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate SEO short_description + content in one call, strictly from supplier
     * data (name/brand/category/specs). Returns ['short' => string, 'content' => string]
     * or null on failure. No price/availability/delivery/warranty claims, no Markdown.
     */
    public function generateSeo(string $name, string $brand, string $category, array $specs = []): ?array
    {
        if ($this->mode === 'none') {
            return null;
        }

        $flat = $this->flattenSpecs($specs);
        $specsText = $flat === []
            ? '(характеристики не предоставлены)'
            : implode("\n", array_map(fn ($v, $k) => "- {$k}: {$v}", $flat, array_keys($flat)));
        $cat = $category !== '' ? $category : 'оборудование';

        $prompt = <<<PROMPT
Ты SEO-копирайтер интернет-магазина отопительного и климатического оборудования.
Сгенерируй описание товара на русском языке СТРОГО на основе данных ниже.

Название: {$name}
Бренд: {$brand}
Категория: {$cat}
Характеристики:
{$specsText}

Требования:
- Пиши только на основе названия, бренда, категории и характеристик. НИЧЕГО не выдумывай.
- НЕ указывай числовые характеристики, которых нет в списке (мощность, размеры, объём, КПД и т.п.).
- КРИТИЧНО: НИКОГДА не пиши единицу измерения (Вт, кВт, В, А, м², м³/час, м³/ч, мм, см, м, дБ, об/мин, кг, ч, л, °C) без конкретного числа НЕПОСРЕДСТВЕННО перед ней.
- Если числового значения характеристики нет в списке — полностью ОПУСТИ упоминание этой характеристики. Лучше короче, но без пропусков значений.
- ЗАПРЕЩЕНЫ любые заглушки и плейсхолдеры: «[Вт]», «(укажите …)», «вписать значение», «указанное значение», «определённого значения», «уточните», символы * и [ ].
- Естественно упомяни бренд, модель и тип товара.
- Без воды и штампов. НЕ пиши «купить дёшево», «лучшая цена», «выгодно».
- НЕ обещай наличие, доставку, гарантию, скидки, сроки.
- Язык — русский. Markdown ЗАПРЕЩЁН.
- content — это СВЯЗНОЕ ТЕКСТОВОЕ ОПИСАНИЕ (проза), 2–4 абзаца <p>. НЕ вставляй таблицу или список характеристик — характеристики на сайте выводятся ОТДЕЛЬНЫМ блоком.
- В поле content разрешены теги: <p>, <h2>, <h3>. Список <ul><li> допустим ТОЛЬКО для преимуществ/назначения, но НЕ для перечисления технических характеристик со значениями.

Верни СТРОГО валидный JSON без пояснений и без обрамления в код:
{"short_description": "1–2 предложения, обычный текст без HTML", "content": "HTML-проза: 2–4 абзаца <p>, без списка/таблицы характеристик"}

Additional SEO quality rules:
- Write a richer commercial description, not a thin paraphrase. Use the product name, brand, category and real specs naturally.
- Include natural search phrases when relevant: "купить в Беларуси", category name, brand name, product type, compatible use case. Do not stuff keywords and do not repeat the same phrase unnaturally.
- For chimney parts, mention the smoke exhaust system context, compatibility by diameter/material when present in specs or name, and practical installation purpose.
- content must contain 3-5 useful paragraphs and may include one <h2> plus one short <ul><li> block for practical advantages or use cases. Do not duplicate the technical characteristics table.
- short_description must be a concise SEO preview, 160-230 characters when possible.
PROMPT;

        try {
            $raw = match ($this->mode) {
                'anthropic'     => $this->callAnthropic($prompt),
                'openai_compat' => $this->callOpenAiCompat($prompt),
                default         => null,
            };
        } catch (\Throwable) {
            return null;
        }

        return $raw ? $this->parseSeoJson($raw) : null;
    }

    /** Flatten specs ({k:v} or [{key,value,unit}]) into a clean [k => v] map. */
    private function flattenSpecs(array $specs): array
    {
        $flat = [];
        foreach ($specs as $k => $v) {
            if (is_array($v)) {
                $key  = $v['key'] ?? $k;
                $val  = (string) ($v['value'] ?? '');
                $unit = (string) ($v['unit'] ?? '');
                if ($key !== '' && trim($val) !== '') {
                    $flat[$key] = $val . ($unit !== '' ? ' ' . $unit : '');
                }
            } elseif (is_scalar($v) && trim((string) $v) !== '') {
                $flat[$k] = (string) $v;
            }
        }
        return $flat;
    }

    /** Parse the model's JSON reply, sanitising HTML to the allowed tag set. */
    private function parseSeoJson(string $raw): ?array
    {
        $s = trim($raw);
        $s = preg_replace('/```(?:json)?/i', '', $s) ?? $s;
        $start = strpos($s, '{');
        $end   = strrpos($s, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $data = json_decode(substr($s, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            return null;
        }

        $short   = trim(strip_tags((string) ($data['short_description'] ?? '')));
        $content = trim((string) ($data['content'] ?? ''));
        if ($content !== '') {
            $content = trim(strip_tags($content, '<p><ul><li><h2><h3><strong>'));
        }

        if ($short === '' && $content === '') {
            return null;
        }

        return ['short' => $short, 'content' => $content];
    }

    // ── Providers ─────────────────────────────────────────────────────────────────

    private function callAnthropic(string $prompt, int $maxTokens = 1024): ?string
    {
        $response = Http::timeout(45)
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => $maxTokens,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        return $response->successful()
            ? (trim($response->json('content.0.text') ?? '') ?: null)
            : null;
    }

    private function callOpenAiCompat(string $prompt, int $maxTokens = 1024): ?string
    {
        $payload = [
            'model'    => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        if ($this->usesMaxCompletionTokens()) {
            $payload['max_completion_tokens'] = $maxTokens;
        } else {
            $payload['max_tokens'] = $maxTokens;
        }

        $response = Http::timeout(45)
            ->withToken($this->apiKey)
            ->post($this->apiUrl, $payload);

        if (! $response->successful()) {
            \Illuminate\Support\Facades\Log::warning('AiContentEnricher HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200));
            return null;
        }

        return trim($response->json('choices.0.message.content') ?? '') ?: null;
    }

    private function usesMaxCompletionTokens(): bool
    {
        $host = strtolower((string) parse_url($this->apiUrl, PHP_URL_HOST));
        $model = strtolower($this->model);

        return str_contains($host, 'api.openai.com')
            && (str_starts_with($model, 'gpt-5') || str_starts_with($model, 'o1') || str_starts_with($model, 'o3') || str_starts_with($model, 'o4'));
    }
}
