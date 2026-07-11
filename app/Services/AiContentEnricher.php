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
    private ?string $lastRawResponse = null;
    private ?string $lastError = null;

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

    public function lastRawResponse(): ?string
    {
        return $this->lastRawResponse;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
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
        $key = (string) config('services.ai.openai_key', '');
        if ($key === '') {
            return $this;
        }

        $url = trim((string) config('services.ai.openai_url', ''));
        if ($url === '') {
            $url = 'https://api.openai.com/v1/chat/completions';
        }

        $clone = clone $this;
        $clone->mode = 'openai_compat';
        $clone->apiKey = $key;
        $clone->apiUrl = $url;
        $clone->model = trim((string) $model) !== ''
            ? trim((string) $model)
            : (string) config('services.ai.openai_model', 'gpt-4.1');

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
     * data (name/brand/category/specs/source context). Returns ['short' => string, 'content' => string]
     * or null on failure. No price/availability/delivery/warranty claims, no Markdown.
     */
    public function generateSeo(string $name, string $brand, string $category, array $specs = [], array $sourceContext = []): ?array
    {
        if ($this->mode === 'none') {
            return null;
        }

        return $this->generateSeoClean($name, $brand, $category, $specs, $sourceContext);

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
- KOTLOV is the seller and marketplace. Never send the customer to suppliers, dealers, other sellers, showrooms or distributors.
- Never write phrases like "обращайтесь к поставщикам", "у поставщиков", "у дилеров", "у продавцов", "поставщикам климатического оборудования".
- Write in the store voice: "купить на KOTLOV", "заказать на KOTLOV", "интернет-магазин KOTLOV", "подобрать на KOTLOV".
- For local SEO in content, use the exact placeholder "в %city%" when geography is needed. Do not replace %city% with a real city; the site renders it automatically.
- Do not put "%city%" in short_description; use neutral Belarus wording there if needed.
- content must include one natural store paragraph with KOTLOV and the exact phrase "в %city%": selection, order, consultation or комплектующие on KOTLOV.
- For chimney parts, do not use the generic phrase "климатическое оборудование". Use "дымоходная система", "система дымоудаления", "отопительная система" or "печь/котел" only when supported by the product name/specs.
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

    private function generateSeoClean(string $name, string $brand, string $category, array $specs = [], array $sourceContext = []): ?array
    {
        $flat = $this->flattenSpecs($specs);
        $specsText = $flat === []
            ? '(характеристики не предоставлены)'
            : implode("\n", array_map(fn ($v, $k) => "- {$k}: {$v}", $flat, array_keys($flat)));
        $cat = trim($category) !== '' ? $category : 'оборудование';
        $brandText = trim($brand) !== '' ? $brand : 'бренд не указан';
        $sourceFacts = $this->formatSourceContext($sourceContext);
        $sourceBlock = $sourceFacts !== ''
            ? "\nДанные из карточки источника/производителя:\n{$sourceFacts}\n"
            : '';

        $prompt = <<<PROMPT
Ты SEO-копирайтер интернет-магазина KOTLOV.BY. Напиши полезное описание товара на русском языке строго по данным ниже.

Название: {$name}
Бренд: {$brandText}
Категория: {$cat}
Характеристики:
{$specsText}
{$sourceBlock}

Правила фактов:
- Используй только название, бренд, категорию, характеристики и данные источника выше. Не выдумывай цифры, страну производства, комплектацию, гарантию, наличие, скидки, сроки или цены.
- Данные источника можно использовать как факты о назначении, серии, применении, конструкции и преимуществах, но нельзя копировать дословно длинные фразы.
- Игнорируй из источника телефоны, адреса, email, график работы, отзывы, рейтинги, чужие магазины, условия оплаты/доставки, цены и наличие.
- Не упоминай материал, покрытие, тип нагревательного элемента, теплообменник, КПД, автоматику, давление, объем, мощность, диаметр, размеры, вес или подключение, если этого нет в названии или характеристиках.
- Не называй бренд итальянским, немецким, российским, польским или другим по происхождению, если страна не дана в характеристиках.
- Не обещай "длительный срок службы", "надежную работу", "стабильную работу", "экономию" или "высокую эффективность", если это не следует из характеристик.
- Не перечисляй таблицу характеристик внутри content: она выводится на сайте отдельно.
- Если характеристик нет, пиши только о назначении и типе товара без конкретных чисел.
- Нельзя использовать заглушки, квадратные скобки, Markdown, фразы "уточните", "у поставщиков", "у дилеров", "обращайтесь к поставщикам".
- KOTLOV.BY — продавец. Не отправляй клиента к другим магазинам, поставщикам, дилерам или дистрибьюторам.

Стиль:
- Спокойный, коммерческий, грамотный. Без воды и без обещаний вроде "лучшая цена".
- Текст должен помогать выбрать товар: где применяется, на что обратить внимание, почему эта позиция может подойти.
- Пиши как интернет-маркетплейс отопительного оборудования: помогай купить, сравнить и подобрать товар, но без пустых рекламных обещаний.
- Естественно используй SEO-фразы, если они подходят к товару: "купить", бренд, тип товара, категория, "в %city%", "с доставкой по Беларуси", "для отопления", "для горячего водоснабжения", "для дымоходной системы". Не вставляй все ключи подряд и не повторяй одну фразу несколько раз.
- Для локального SEO в content используй точный плейсхолдер "в %city%". Не заменяй %city% реальным городом.
- В short_description не ставь %city%; там можно писать "по Беларуси".
- Если товар относится к дымоходам, используй "дымоходная система", "система дымоудаления", "печь" или "котел", а не "климатическое оборудование".
- Если товар относится к радиаторам, пиши про систему отопления, секционность/панельность и подключение только когда это следует из названия или характеристик.

Формат:
- Верни строго валидный JSON без пояснений и без code block.
- short_description: 1-2 предложения, 160-230 символов, обычный текст без HTML.
- content: HTML-проза 3-5 полезных абзацев. Разрешены только теги <p>, <h2>, <h3>, <ul>, <li>, <strong>.
- Один короткий список <ul><li> допустим для преимуществ или сценариев использования, но не для характеристик.
- content должен содержать один естественный абзац с KOTLOV.BY и фразой "в %city%": подбор, заказ, консультация или комплектация.

JSON:
{"short_description": "...", "content": "..."}
PROMPT;

        $prompt .= <<<'PROMPT'

Финальная проверка качества:
- Начинай content с тега <p>, не с <h2>. Не повторяй полное название товара в заголовке.
- Не используй рекламные штампы: "современный", "эффективный", "надежный", "комфортный", "лучший", "выгодный", "инновационный", "универсальный", "идеальный", "стабильный", если это не прямо указано в характеристиках.
- Не пиши "очищение воды", "защита от коррозии", "экономия", "простое управление", "стабильная работа" и похожие выводы, если таких характеристик нет в списке.
- Если характеристика есть, можно аккуратно использовать ее как факт: объем, монтаж, мощность, температура, размеры, материал, подключение.
- Тон текста: спокойный консультант магазина, не рекламный буклет.
- Не оставляй незавершенные фразы вроде "которое обеспечивает.", "позволяет.", "подходит для.". Каждое предложение должно быть грамматически законченным.
- Хороший content: 2-3 абзаца, затем короткий список "на что обратить внимание", затем абзац KOTLOV.BY с фразой "в %city%".
- Не делай длинный SEO-текст ради длины. Лучше короче, но точнее.
PROMPT;

        try {
            $raw = match ($this->mode) {
                'anthropic' => $this->callAnthropic($prompt, 1400),
                'openai_compat' => $this->callOpenAiCompat($prompt, 1400),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }

        return $raw ? $this->parseSeoJson($raw) : null;
    }

    private function formatSourceContext(array $sourceContext): string
    {
        $lines = [];
        $labels = [
            'source_url' => 'URL',
            'source_title' => 'Заголовок источника',
            'source_short_description' => 'Краткое описание источника',
            'source_description' => 'Описание источника',
        ];

        foreach ($labels as $key => $label) {
            $value = trim((string) ($sourceContext[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $value = preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value;
            $max = $key === 'source_description' ? 2200 : 500;
            $lines[] = "- {$label}: " . mb_substr($value, 0, $max);
        }

        return implode("\n", $lines);
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
        $short   = $this->cleanupStoreVoice($short, false);
        $content = trim((string) ($data['content'] ?? ''));
        if ($content !== '') {
            $content = trim(strip_tags($content, '<p><ul><li><h2><h3><strong>'));
            $content = $this->cleanupStoreVoice($content, true);
        }

        if ($short === '' && $content === '') {
            return null;
        }

        return ['short' => $short, 'content' => $content];
    }

    // ── Providers ─────────────────────────────────────────────────────────────────

    /**
     * Keep generated copy in KOTLOV's own store voice.
     *
     * Product pages must not redirect users to third-party suppliers. City SEO
     * text is rendered later from ProductController's %city% placeholder.
     */
    private function cleanupStoreVoice(string $text, bool $allowCityPlaceholder): string
    {
        if ($text === '') {
            return '';
        }

        $cleanOrderSentence = $allowCityPlaceholder
            ? 'Заказать товар можно на KOTLOV.BY в %city%.'
            : 'Заказать товар можно на KOTLOV.BY.';

        $cleanPatterns = [
            '~(?:для\s+)?приобретения\s+([^.!?]{0,120})\s+обращайтесь\s+к\s+поставщикам[^.!?]*[.!?]?~iu' => $cleanOrderSentence,
            '~обращайтесь\s+к\s+(?:поставщикам|дилерам|продавцам|дистрибьюторам)[^.!?]*[.!?]?~iu' => $cleanOrderSentence,
            '~у\s+поставщиков\s+климатического\s+оборудования~iu' => 'на KOTLOV.BY',
            '~у\s+(?:поставщиков|дилеров|продавцов|дистрибьюторов)~iu' => 'на KOTLOV.BY',
            '~через\s+(?:поставщиков|дилеров|продавцов|дистрибьюторов)~iu' => 'на KOTLOV.BY',
            '~поставщикам\s+климатического\s+оборудования~iu' => 'KOTLOV.BY',
        ];

        foreach ($cleanPatterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        $text = preg_replace('~\b(?:итальянского|немецкого|российского|польского|литовского|китайского)\s+бренда\b~iu', 'бренда', $text) ?? $text;
        $text = preg_replace('~\s*(?:Модель|Товар|Оборудование)\s+[^.!?]{0,80}?(?:рассчитан[ао]?|создан[ао]?)\s+на\s+(?:длительный\s+срок\s+службы|стабильную\s+работу)[^.!?]*[.!?]?~iu', '', $text) ?? $text;
        $text = preg_replace('~\b(?:длительный\s+срок\s+службы|стабильная\s+работа|надежная\s+работа|высокая\s+эффективность)\b~iu', '', $text) ?? $text;

        $text = preg_replace('~\b(?:современн(?:ый|ая|ое|ые|ого|ой|ых)|эффективн(?:ый|ая|ое|ые|ого|ой|ых)|надежн(?:ый|ая|ое|ые|ого|ой|ых)|комфортн(?:ый|ая|ое|ые|ого|ой|ых)|инновационн(?:ый|ая|ое|ые|ого|ой|ых)|выгодн(?:ый|ая|ое|ые|ого|ой|ых)|универсальн(?:ый|ая|ое|ые|ого|ой|ых)|идеальн(?:ый|ая|ое|ые|ого|ой|ых)|стабильн(?:ый|ая|ое|ые|ого|ой|ых))\s+~iu', '', $text) ?? $text;
        $text = preg_replace('~\b(?:очищени[ея]\s+воды|защит[ау]\s+от\s+коррозии|стабильн(?:ая|ую)\s+работ[ау]|прост(?:ое|ым)\s+управлени[еем]|длительн(?:ый|ого)\s+срок(?:а)?\s+службы)\b~iu', '', $text) ?? $text;
        $text = preg_replace('~\s+([,.!?;:])~u', '$1', $text) ?? $text;

        $orderSentence = $allowCityPlaceholder
            ? 'Заказать товар можно на KOTLOV в %city%.'
            : 'Заказать товар можно на KOTLOV.';

        $patterns = [
            '~(?:для\s+)?приобретения\s+([^.!?]{0,120})\s+обращайтесь\s+к\s+поставщикам[^.!?]*[.!?]?~iu' => $orderSentence,
            '~обращайтесь\s+к\s+(?:поставщикам|дилерам|продавцам|дистрибьюторам)[^.!?]*[.!?]?~iu' => $orderSentence,
            '~у\s+поставщиков\s+климатического\s+оборудования~iu' => 'на KOTLOV',
            '~у\s+(?:поставщиков|дилеров|продавцов|дистрибьюторов)~iu' => 'на KOTLOV',
            '~через\s+(?:поставщиков|дилеров|продавцов|дистрибьюторов)~iu' => 'на KOTLOV',
            '~поставщикам\s+климатического\s+оборудования~iu' => 'KOTLOV',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        if (! $allowCityPlaceholder) {
            $text = str_replace(['в %city%', '%city%'], 'по Беларуси', $text);
            $text = str_replace(['в %city%', '%city%'], 'в Беларуси', $text);
        }

        $text = preg_replace('/\s+([,.!?;:])/u', '$1', $text) ?? $text;
        $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;

        return trim($text);
    }

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
        $this->lastRawResponse = null;
        $this->lastError = null;

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

        $this->lastRawResponse = $response->body();

        if (! $response->successful()) {
            $this->lastError = 'HTTP ' . $response->status();
            \Illuminate\Support\Facades\Log::warning('AiContentEnricher HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200));
            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (is_array($content)) {
            $content = collect($content)
                ->map(fn ($part): string => is_array($part) ? (string) ($part['text'] ?? '') : (string) $part)
                ->implode('');
        }

        if (! is_string($content) || trim($content) === '') {
            $content = $response->json('output_text');
        }

        if (! is_string($content) || trim($content) === '') {
            $this->lastError = 'empty content';
            return null;
        }

        return trim($content) ?: null;
    }

    private function usesMaxCompletionTokens(): bool
    {
        $host = strtolower((string) parse_url($this->apiUrl, PHP_URL_HOST));
        $model = strtolower($this->model);

        return str_contains($host, 'api.openai.com')
            && (str_starts_with($model, 'gpt-5') || str_starts_with($model, 'o1') || str_starts_with($model, 'o3') || str_starts_with($model, 'o4'));
    }
}
