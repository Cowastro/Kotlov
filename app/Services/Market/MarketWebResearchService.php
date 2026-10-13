<?php

namespace App\Services\Market;

use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MarketWebResearchService
{
    /** @return array<string, mixed> */
    public function researchQuery(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return $this->failure('Введите название, модель или характеристики товара для поиска.');
        }

        $product = new Product;
        $product->name = $query;
        $product->sku = null;
        $product->price = 0;

        return $this->research($product, 0);
    }

    /** @return array<string, mixed> */
    public function research(Product $product, float $quotePrice): array
    {
        $provider = $this->provider();
        $safeMode = $provider['name'] === 'deepseek'
            && (bool) config('services.ai.market_research_safe_mode', true);
        if ($safeMode) {
            $provider['model'] = trim((string) config(
                'services.ai.deepseek_research_fallback_model',
                'deepseek-flash'
            ));
        }
        $apiKey = $provider['api_key'];
        if ($apiKey === '') {
            return $this->failure('Поиск не настроен: отсутствует API-ключ выбранного провайдера.');
        }
        if ($provider['responses_url'] === '' || $provider['model'] === '') {
            return $this->failure('Поиск не настроен: для выбранного провайдера не заданы endpoint или модель.');
        }

        $product->loadMissing(['brand', 'category']);
        $cacheKey = 'market-research:v3:'.hash('sha256', implode('|', [
            $provider['name'],
            $provider['model'],
            (string) ($product->getKey() ?: $product->sku),
            Str::lower(trim((string) $product->name)),
            number_format($quotePrice, 2, '.', ''),
        ]));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['status'] ?? null) === 'ready') {
            return $cached;
        }

        $lock = Cache::lock($cacheKey.':lock', max(
            30,
            (int) config('services.ai.openai_research_timeout', 180) + 10
        ));
        if (! $lock->get()) {
            return $this->failure('Этот поиск уже выполняется. Дождитесь результата, повторный запрос не отправлен.');
        }

        $models = $safeMode
            ? [$provider['model']]
            : ($provider['name'] === 'azure'
                ? [$provider['model']]
                : array_values(array_unique(array_filter([
                    $provider['model'],
                    $provider['name'] === 'deepseek'
                        ? trim((string) config('services.ai.deepseek_research_fallback_model', 'deepseek-flash'))
                        : trim((string) config('services.ai.openai_model', 'gpt-4.1')),
                ]))));
        $lastError = null;

        try {
            foreach ($models as $model) {
                try {
                    $request = Http::acceptJson()
                        ->timeout((int) config('services.ai.openai_research_timeout', 180));
                    if (! $safeMode) {
                        $request = $request->retry(2, 1500, throw: false);
                    }
                    if ($provider['name'] === 'azure') {
                        $request = $request->withHeaders(['api-key' => $apiKey]);
                    } elseif ($provider['name'] === 'deepseek') {
                        $request = $request->withHeaders([
                            'x-api-key' => $apiKey,
                            'anthropic-version' => '2023-06-01',
                        ]);
                    } else {
                        $request = $request->withToken($apiKey);
                        $proxy = trim((string) config('services.ai.openai_proxy'));
                        if ($proxy !== '') {
                            $request = $request->withOptions(['proxy' => $proxy]);
                        }
                    }

                    $response = $request->post(
                        $provider['responses_url'],
                        $this->payload($product, $quotePrice, $model, $provider['name'])
                    );

                    if (! $response->successful()) {
                        $lastError = $this->apiError($response->json(), $response->status(), $provider['label']);

                        continue;
                    }

                    $result = $this->normalize($response->json(), $product, $quotePrice, $model);
                    if ($safeMode && ($result['status'] ?? null) !== 'ready') {
                        $repairResponse = $this->repairDeepSeekResponse($response->json(), $provider, $model);
                        if ($repairResponse !== null) {
                            $result = $this->normalize(
                                $repairResponse,
                                $product,
                                $quotePrice,
                                $model,
                                $response->json()
                            );
                        }
                        if (($result['status'] ?? null) !== 'ready') {
                            $result = $this->sourcesOnlyResult(
                                $response->json(),
                                $product,
                                $quotePrice,
                                $model
                            );
                        }
                    }
                    if (($result['status'] ?? null) === 'ready') {
                        Cache::put(
                            $cacheKey,
                            $result,
                            now()->addMinutes(max(1, (int) config('services.ai.market_research_cache_minutes', 360)))
                        );

                        return $result;
                    }

                    $lastError = (string) ($result['message'] ?? 'модель не вернула структурированный отчёт');
                } catch (ConnectionException|RequestException $exception) {
                    $lastError = $exception->getMessage();
                } catch (Throwable $exception) {
                    report($exception);
                    $lastError = $exception->getMessage();
                }
            }

            return $this->failure('Веб-поиск не завершён. '.($lastError ?: 'Провайдер не вернул ответ.'));
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    private function payload(Product $product, float $quotePrice, string $model, string $provider): array
    {
        if ($provider === 'deepseek') {
            $safeMode = (bool) config('services.ai.market_research_safe_mode', true);

            $payload = [
                'model' => $model,
                'max_tokens' => $safeMode
                    ? max(2000, min(12000, (int) config('services.ai.market_research_max_output_tokens', 8000)))
                    : 16000,
                'metadata' => ['user_id' => 'kotlov-market-research'],
                'system' => $this->instructions()."\n\n".$this->deepSeekJsonContract(),
                'messages' => [[
                    'role' => 'user',
                    'content' => $this->productPrompt($product, $quotePrice),
                ]],
                'tools' => [[
                    'type' => 'web_search_20250305',
                    'name' => 'web_search',
                    'max_uses' => $safeMode
                        ? max(1, min(8, (int) config('services.ai.market_research_max_search_uses', 6)))
                        : 12,
                ]],
            ];

            if ($safeMode) {
                $payload['reasoning'] = ['effort' => 'low'];
            }

            return $payload;
        }

        $webSearch = [
            'type' => 'web_search',
            'search_context_size' => 'high',
            'user_location' => [
                'type' => 'approximate',
                'country' => 'BY',
                'city' => 'Minsk',
                'region' => 'Minsk',
            ],
        ];
        if ($provider === 'openai') {
            $webSearch['external_web_access'] = true;
            $webSearch['return_token_budget'] = 'unlimited';
        }

        $payload = [
            'model' => $model,
            'store' => false,
            'tools' => [$webSearch],
            'tool_choice' => 'required',
            'include' => ['web_search_call.action.sources'],
            'max_output_tokens' => 16000,
            'instructions' => $this->instructions(),
            'input' => $this->productPrompt($product, $quotePrice),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'belarus_market_research',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];

        if (preg_match('/^(gpt-[56]|o[1-9])/', $model) === 1) {
            $payload['reasoning'] = ['effort' => 'max'];
        }

        return $payload;
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Ты — старший специалист по закупкам и конкурентной разведке интернет-магазина отопительной техники в Беларуси. Проведи настоящее исследование открытого интернета с помощью web_search. Не ограничивайся первым поиском: используй название, бренд, артикул, варианты транслитерации и ключевые характеристики.

Главные правила:
1. Сначала ищи предложения в Беларуси. Если их мало — отдельно покажи РФ и другие соседние рынки, не смешивая валюты без явного пересчёта.
2. Считай подходящим только тот же товар: модель, мощность, исполнение, сторона подключения, толщина стали, комплектность и единица продажи должны совпадать. Похожие позиции заноси в rejected_candidates с причиной.
3. Открой страницы предложений и проверь цену, валюту, наличие, НДС и комплектность. Результаты поиска, категории, агрегаторы без подтверждаемой карточки и архивные страницы не считай полноценным предложением.
4. Стремись получить не менее трёх независимых продавцов на разных доменах. Если их меньше, всё равно дай полезный вывод и честно снизь confidence.
5. Не выдумывай цены, продавцов и ссылки. В offers разрешены только страницы, реально найденные инструментом web_search. Для каждого вывода дай короткое evidence.
6. Цена клиента дана только для сравнения; не подгоняй рынок под неё. Не принимай решение об изменении цены автоматически.
7. Ответ дай по-русски строго в заданной JSON-схеме. Все денежные значения — числа без текста; неизвестные значения — null.
PROMPT;
    }

    private function productPrompt(Product $product, float $quotePrice): string
    {
        $specs = collect($product->specs ?: [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): string {
                $name = trim((string) ($row['name'] ?? $row['key'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));

                return $name !== '' && $value !== '' ? $name.': '.$value : '';
            })
            ->filter()
            ->take(30)
            ->implode('; ');

        return implode("\n", [
            'Дата исследования: '.now()->toDateString(),
            'Название: '.$product->name,
            'Бренд: '.($product->brand?->name ?: 'не указан'),
            'Артикул/SKU: '.($product->sku ?: 'не указан'),
            'Категория: '.($product->category?->name ?: 'не указана'),
            'Характеристики: '.($specs ?: 'не указаны'),
            'Цена в коммерческом предложении: '.($quotePrice > 0 ? number_format($quotePrice, 2, '.', '').' BYN' : 'ещё не назначена'),
            '',
            'Найди актуальные предложения, сравни их и дай менеджеру обоснованный вывод. В search_queries перечисли фактически выполненные поисковые формулировки.',
        ]);
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => 'string'],
                'verdict' => ['type' => 'string'],
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'search_queries' => ['type' => 'array', 'items' => ['type' => 'string']],
                'offers' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'seller' => ['type' => 'string'],
                            'domain' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                            'price' => $nullableNumber,
                            'currency' => $nullableString,
                            'price_byn' => $nullableNumber,
                            'availability' => $nullableString,
                            'vat' => $nullableString,
                            'package' => $nullableString,
                            'match' => ['type' => 'string', 'enum' => ['exact', 'probable']],
                            'evidence' => ['type' => 'string'],
                        ],
                        'required' => ['seller', 'domain', 'title', 'url', 'price', 'currency', 'price_byn', 'availability', 'vat', 'package', 'match', 'evidence'],
                    ],
                ],
                'rejected_candidates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'url' => $nullableString,
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'url', 'reason'],
                    ],
                ],
            ],
            'required' => ['summary', 'verdict', 'confidence', 'search_queries', 'offers', 'rejected_candidates'],
        ];
    }

    private function deepSeekJsonContract(): string
    {
        return <<<'PROMPT'
Верни только один JSON-объект без Markdown и пояснений. Формат:
{"summary":"...","verdict":"...","confidence":"high|medium|low","search_queries":["..."],"offers":[{"seller":"...","domain":"...","title":"...","url":"https://...","price":123.45,"currency":"BYN","price_byn":123.45,"availability":"...","vat":"...","package":"...","match":"exact|probable","evidence":"..."}],"rejected_candidates":[{"title":"...","url":"https://... или null","reason":"..."}]}
PROMPT;
    }

    /** @param array<string, mixed> $response
     * @param array{name: string, label: string, api_key: string, responses_url: string, model: string} $provider
     * @return array<string, mixed>|null
     */
    private function repairDeepSeekResponse(array $response, array $provider, string $model): ?array
    {
        $text = trim($this->outputText($response));
        if ($text === '') {
            return null;
        }

        $sources = collect($this->webSources($response))
            ->map(fn (array $source): string => trim((string) $source['title']).' — '.(string) $source['url'])
            ->implode("\n");

        try {
            $repair = Http::acceptJson()
                ->timeout(min(60, (int) config('services.ai.openai_research_timeout', 180)))
                ->withHeaders([
                    'x-api-key' => $provider['api_key'],
                    'anthropic-version' => '2023-06-01',
                ])
                ->post($provider['responses_url'], [
                    'model' => $model,
                    'max_tokens' => 4000,
                    'reasoning' => ['effort' => 'none'],
                    'metadata' => ['user_id' => 'kotlov-market-research-format'],
                    'system' => 'Преобразуй исходный отчёт в JSON. Не выполняй новый поиск, не добавляй факты, цены или ссылки. '.$this->deepSeekJsonContract(),
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Исходный отчёт:\n".$text."\n\nПодтверждённые источники:\n".($sources ?: 'нет'),
                    ]],
                ]);

            return $repair->successful() ? $repair->json() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function sourcesOnlyResult(
        array $response,
        Product $product,
        float $quotePrice,
        string $model
    ): array {
        $sources = $this->webSources($response);
        $count = count($sources);

        return [
            'status' => 'ready',
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quote_price' => $quotePrice > 0 ? $quotePrice : null,
            'summary' => $count > 0
                ? 'Поиск нашёл источники, но безопасный режим не смог достоверно извлечь из них цены.'
                : 'Безопасный поиск завершён, подтверждённых карточек товара не найдено.',
            'verdict' => 'Откройте найденные источники и проверьте цену вручную.',
            'confidence' => 'low',
            'search_queries' => [$product->name],
            'offers' => [],
            'rejected_candidates' => [],
            'sources' => $sources,
            'market' => [
                'count' => 0,
                'minimum' => null,
                'median' => null,
                'maximum' => null,
                'quote_delta_percent' => null,
            ],
            'model' => $model,
            'searched_at' => now(),
        ];
    }

    /** @param array<string, mixed> $response
     * @param array<string, mixed>|null $sourceResponse
     * @return array<string, mixed>
     */
    private function normalize(
        array $response,
        Product $product,
        float $quotePrice,
        string $model,
        ?array $sourceResponse = null
    ): array
    {
        $decoded = $this->decodeStructuredReport($this->outputText($response));
        if (! is_array($decoded)) {
            return $this->failure('Поиск завершился, но модель не вернула структурированный отчёт.');
        }

        $sources = $this->webSources($sourceResponse ?? $response);
        $sourceIndex = collect($sources)->keyBy(fn (array $source): string => $this->normalizeUrl((string) $source['url']));
        $offers = collect($decoded['offers'] ?? [])
            ->filter(fn ($offer): bool => is_array($offer) && trim((string) ($offer['url'] ?? '')) !== '')
            ->map(function (array $offer) use ($sourceIndex): ?array {
                $source = $sourceIndex->get($this->normalizeUrl((string) $offer['url']));
                if (! is_array($source)) {
                    return null;
                }

                $offer['url'] = $source['url'];
                $offer['domain'] = parse_url((string) $source['url'], PHP_URL_HOST) ?: ($offer['domain'] ?? '');
                $offer['price_byn'] = is_numeric($offer['price_byn'] ?? null) ? round((float) $offer['price_byn'], 2) : null;

                return $offer;
            })
            ->filter()
            ->unique(fn (array $offer): string => mb_strtolower((string) $offer['domain']))
            ->values()
            ->all();

        $prices = collect($offers)
            ->pluck('price_byn')
            ->filter(fn ($price): bool => is_numeric($price) && (float) $price > 0)
            ->map(fn ($price): float => (float) $price)
            ->sort()
            ->values();
        $median = $prices->isEmpty() ? null : $this->median($prices->all());
        $rejectedCandidates = collect($decoded['rejected_candidates'] ?? [])
            ->filter(fn ($candidate): bool => is_array($candidate))
            ->map(function (array $candidate) use ($sourceIndex): array {
                $source = $sourceIndex->get($this->normalizeUrl((string) ($candidate['url'] ?? '')));
                $candidate['url'] = is_array($source) ? $source['url'] : null;

                return $candidate;
            })
            ->values()
            ->all();

        return [
            'status' => 'ready',
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quote_price' => $quotePrice > 0 ? $quotePrice : null,
            'summary' => trim((string) ($decoded['summary'] ?? '')),
            'verdict' => trim((string) ($decoded['verdict'] ?? '')),
            'confidence' => in_array($decoded['confidence'] ?? null, ['high', 'medium', 'low'], true) ? $decoded['confidence'] : 'low',
            'search_queries' => array_values(array_filter(array_map('strval', (array) ($decoded['search_queries'] ?? [])))),
            'offers' => $offers,
            'rejected_candidates' => $rejectedCandidates,
            'sources' => $sources,
            'market' => [
                'count' => $prices->count(),
                'minimum' => $prices->isEmpty() ? null : round((float) $prices->first(), 2),
                'median' => $median,
                'maximum' => $prices->isEmpty() ? null : round((float) $prices->last(), 2),
                'quote_delta_percent' => $median && $median > 0 && $quotePrice > 0
                    ? round(($quotePrice - $median) / $median * 100, 1)
                    : null,
            ],
            'model' => $model,
            'searched_at' => now(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function decodeStructuredReport(string $text): ?array
    {
        $text = trim($text);
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/iu', $text, $matches) === 1) {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $response */
    private function outputText(array $response): string
    {
        if (isset($response['content']) && is_array($response['content'])) {
            $text = collect($response['content'])
                ->filter(fn ($content): bool => is_array($content) && ($content['type'] ?? null) === 'text')
                ->pluck('text')
                ->map(fn ($value): string => (string) $value)
                ->implode("\n");

            return $this->stripJsonFence($text);
        }

        foreach ((array) ($response['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text') {
                    return $this->stripJsonFence((string) ($content['text'] ?? ''));
                }
            }
        }

        return $this->stripJsonFence((string) ($response['output_text'] ?? ''));
    }

    private function stripJsonFence(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return $text;
    }

    /** @param array<string, mixed> $response
     * @return list<array{title: string, url: string}>
     */
    private function webSources(array $response): array
    {
        $sources = [];
        foreach ((array) ($response['content'] ?? []) as $content) {
            if (! is_array($content)) {
                continue;
            }

            if (($content['type'] ?? null) === 'web_search_tool_result') {
                foreach ((array) ($content['content'] ?? []) as $source) {
                    $this->appendSource($sources, $source);
                }
            }

            foreach ((array) ($content['citations'] ?? []) as $citation) {
                $this->appendSource($sources, $citation);
            }
        }

        foreach ((array) ($response['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'web_search_call') {
                foreach ((array) data_get($item, 'action.sources', []) as $source) {
                    $this->appendSource($sources, $source);
                }
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                foreach ((array) (is_array($content) ? ($content['annotations'] ?? []) : []) as $annotation) {
                    $this->appendSource($sources, $annotation);
                }
            }
        }

        return collect($sources)
            ->filter(fn (array $source): bool => filter_var($source['url'], FILTER_VALIDATE_URL) !== false
                && in_array(Str::lower((string) parse_url($source['url'], PHP_URL_SCHEME)), ['http', 'https'], true))
            ->unique(fn (array $source): string => $this->normalizeUrl($source['url']))
            ->values()
            ->all();
    }

    /** @param array<int, array{title: string, url: string}> $sources */
    private function appendSource(array &$sources, mixed $source): void
    {
        if (! is_array($source)) {
            return;
        }

        $url = trim((string) ($source['url'] ?? data_get($source, 'url_citation.url', '')));
        if ($url === '') {
            return;
        }

        $sources[] = [
            'title' => trim((string) ($source['title'] ?? data_get($source, 'url_citation.title', parse_url($url, PHP_URL_HOST) ?: $url))),
            'url' => $url,
        ];
    }

    private function normalizeUrl(string $url): string
    {
        return Str::lower(rtrim(preg_replace('/[?#].*$/', '', trim($url)) ?: trim($url), '/'));
    }

    /** @param list<float> $values */
    private function median(array $values): float
    {
        $count = count($values);
        $middle = intdiv($count, 2);

        return round($count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2, 2);
    }

    /** @param array<string, mixed>|null $body */
    private function apiError(?array $body, int $status, string $provider): string
    {
        return $provider.' HTTP '.$status.': '.trim((string) data_get($body, 'error.message', 'неизвестная ошибка'));
    }

    /** @return array{name: string, label: string, api_key: string, responses_url: string, model: string} */
    private function provider(): array
    {
        $name = trim((string) config('services.ai.market_research_provider', 'deepseek'));

        if ($name === 'deepseek') {
            return [
                'name' => 'deepseek',
                'label' => 'DeepSeek',
                'api_key' => trim((string) config('services.ai.api_key')),
                'responses_url' => trim((string) config('services.ai.deepseek_anthropic_url', 'https://api.deepseek.com/anthropic/v1/messages')),
                'model' => trim((string) config('services.ai.deepseek_research_model', 'deepseek-v4-pro')),
            ];
        }

        if ($name === 'azure') {
            return [
                'name' => 'azure',
                'label' => 'Azure OpenAI',
                'api_key' => trim((string) config('services.ai.azure_openai_key')),
                'responses_url' => trim((string) config('services.ai.azure_openai_responses_url')),
                'model' => trim((string) config('services.ai.azure_openai_research_model', 'gpt-5.5')),
            ];
        }

        return [
            'name' => 'openai',
            'label' => 'OpenAI',
            'api_key' => trim((string) config('services.ai.openai_key')),
            'responses_url' => trim((string) config('services.ai.openai_responses_url', 'https://api.openai.com/v1/responses')),
            'model' => trim((string) config('services.ai.openai_research_model', 'gpt-6.1-sol')),
        ];
    }

    /** @return array<string, mixed> */
    private function failure(string $message): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'offers' => [],
            'sources' => [],
            'rejected_candidates' => [],
            'market' => ['count' => 0, 'minimum' => null, 'median' => null, 'maximum' => null, 'quote_delta_percent' => null],
            'searched_at' => now(),
        ];
    }
}
