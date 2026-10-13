<?php

namespace App\Services\Market;

use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MarketWebResearchService
{
    /** @return array<string, mixed> */
    public function research(Product $product, float $quotePrice): array
    {
        $apiKey = trim((string) config('services.ai.openai_key'));
        if ($apiKey === '') {
            return $this->failure('Поиск не настроен: на сервере отсутствует OPENAI_API_KEY.');
        }

        $product->loadMissing(['brand', 'category']);
        $models = array_values(array_unique(array_filter([
            trim((string) config('services.ai.openai_research_model', 'gpt-6.1-sol')),
            trim((string) config('services.ai.openai_model', 'gpt-4.1')),
        ])));
        $lastError = null;

        foreach ($models as $model) {
            try {
                $request = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout((int) config('services.ai.openai_research_timeout', 180))
                    ->retry(2, 1500, throw: false);
                $proxy = trim((string) config('services.ai.openai_proxy'));
                if ($proxy !== '') {
                    $request = $request->withOptions(['proxy' => $proxy]);
                }

                $response = $request->post(
                    (string) config('services.ai.openai_responses_url', 'https://api.openai.com/v1/responses'),
                    $this->payload($product, $quotePrice, $model)
                );

                if (! $response->successful()) {
                    $lastError = $this->apiError($response->json(), $response->status());

                    continue;
                }

                return $this->normalize($response->json(), $product, $quotePrice, $model);
            } catch (ConnectionException|RequestException $exception) {
                $lastError = $exception->getMessage();
            } catch (Throwable $exception) {
                report($exception);
                $lastError = $exception->getMessage();
            }
        }

        return $this->failure('Веб-поиск не завершён. '.($lastError ?: 'Провайдер не вернул ответ.'));
    }

    /** @return array<string, mixed> */
    private function payload(Product $product, float $quotePrice, string $model): array
    {
        $payload = [
            'model' => $model,
            'store' => false,
            'tools' => [[
                'type' => 'web_search',
                'external_web_access' => true,
                'search_context_size' => 'high',
                'return_token_budget' => 'unlimited',
                'user_location' => [
                    'type' => 'approximate',
                    'country' => 'BY',
                    'city' => 'Minsk',
                    'region' => 'Minsk',
                ],
            ]],
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
            'Цена в коммерческом предложении: '.number_format($quotePrice, 2, '.', '').' BYN',
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

    /** @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function normalize(array $response, Product $product, float $quotePrice, string $model): array
    {
        $decoded = json_decode($this->outputText($response), true);
        if (! is_array($decoded)) {
            return $this->failure('Поиск завершился, но модель не вернула структурированный отчёт.');
        }

        $sources = $this->webSources($response);
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
            'quote_price' => $quotePrice,
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
                'quote_delta_percent' => $median && $median > 0 ? round(($quotePrice - $median) / $median * 100, 1) : null,
            ],
            'model' => $model,
            'searched_at' => now(),
        ];
    }

    /** @param array<string, mixed> $response */
    private function outputText(array $response): string
    {
        foreach ((array) ($response['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text') {
                    return (string) ($content['text'] ?? '');
                }
            }
        }

        return (string) ($response['output_text'] ?? '');
    }

    /** @param array<string, mixed> $response
     * @return list<array{title: string, url: string}>
     */
    private function webSources(array $response): array
    {
        $sources = [];
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
    private function apiError(?array $body, int $status): string
    {
        return 'OpenAI HTTP '.$status.': '.trim((string) data_get($body, 'error.message', 'неизвестная ошибка'));
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
