<?php

namespace App\Services\CommercialDocuments;

use App\Models\CrmMailThread;
use App\Models\Product;
use App\Services\Crm\BelarusCompanyLookup;
use App\Services\CrmMail\CrmMailPdfTextExtractor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QuickCommercialOfferService
{
    private const STOP_WORDS = [
        'fw', 'fwd', 're', 'запрос', 'коммерческого', 'коммерческое', 'предложение', 'предложения',
        'просим', 'прошу', 'цена', 'цены', 'стоимость', 'наличие', 'срок', 'поставка', 'поставки',
        'добрый', 'день', 'здравствуйте', 'спасибо', 'письмо', 'ответ', 'ответе', 'товар', 'товары',
        'для', 'или', 'как', 'что', 'это', 'нам', 'вам', 'вас', 'при', 'под', 'над', 'без', 'из', 'на', 'по', 'от', 'до',
        'печь', 'печи', 'котел', 'котёл', 'котла', 'котлы', 'камин', 'камины', 'насос', 'насосы',
        'твердотопливный', 'твердотопливная', 'отопительный', 'отопительная', 'банный', 'банная',
    ];

    public function __construct(
        private readonly CommercialOfferMailAnalyzer $mailAnalyzer,
        private readonly BelarusCompanyLookup $companyLookup,
        private readonly CrmMailPdfTextExtractor $pdfTextExtractor,
    ) {}

    /**
     * @return array{prefill: array<string, mixed>, suggestions: array<int, string>, default_product_ids: list<int>, product_quantities: array<int, float>, analysis_summary: string}
     */
    public function prepare(CrmMailThread $thread, ?bool $useAi = null): array
    {
        $thread->loadMissing(['counterparty.contactPoints', 'contact.contactPoints', 'messages']);
        [$text, $pdfSummary] = $this->sourceText($thread);
        $analysis = $this->mailAnalyzer->analyze($thread, $text, $useAi);
        $registry = $this->companyLookup->lookup($analysis['unp'] ?? null);
        if ($registry) {
            $analysis = $this->mergeRegistryData($analysis, $registry);
        }

        $prepared = array_merge(
            $this->matchProducts($text, $analysis),
            ['prefill' => $this->clientPrefill($thread, $text, $analysis)],
        );

        if ($pdfSummary !== '') {
            $prepared['analysis_summary'] = trim($prepared['analysis_summary'].' '.$pdfSummary);
        }

        return $prepared;
    }

    /**
     * @return array{suggestions: array<int, string>, default_product_ids: list<int>, product_quantities: array<int, float>, analysis_summary: string, analysis: array<string, mixed>, unmatched_items: array<int, array<string, mixed>>}
     */
    public function prepareText(string $text, ?bool $useAi = null): array
    {
        $text = trim($text);
        $analysis = $this->mailAnalyzer->analyzeText($text, 'commercial-document-manager', $useAi);
        if (empty($analysis['items'])) {
            $analysis['items'] = $this->fallbackItems($text);
        }

        return $this->matchProducts($text, $analysis);
    }

    /** @return array<string, mixed> */
    private function matchProducts(string $text, array $analysis): array
    {
        $aiSearchText = collect($analysis['items'])
            ->flatMap(fn (array $item): array => [$item['query'], $item['sku']])
            ->filter()->implode(' ');
        $searchText = trim($aiSearchText !== '' ? $aiSearchText : $text);
        $tokens = $this->tokens($searchText);
        $products = $this->candidateProducts($tokens);

        $ranked = $products
            ->map(fn (Product $product): array => [
                'product' => $product,
                'score' => $this->score($product, $tokens, $searchText),
            ])
            ->filter(fn (array $item): bool => $item['score'] >= 60)
            ->sortByDesc(fn (array $item): array => [$item['score'], (int) $item['product']->in_stock, (int) $item['product']->views_count])
            ->values();

        $suggestions = $ranked->take(12)->mapWithKeys(function (array $item): array {
            /** @var Product $product */
            $product = $item['product'];
            $parts = [
                $product->name,
                $product->sku ? 'арт. '.$product->sku : null,
                (float) $product->price > 0 ? number_format((float) $product->price, 2, ',', ' ').' BYN' : 'цена не указана',
                $product->availabilityLabel(),
            ];

            return [$product->id => implode(' · ', array_filter($parts))];
        })->all();

        $exactMatches = $ranked
            ->filter(fn (array $item): bool => $this->sourceContainsExactIdentifier($item['product'], $searchText));
        $defaults = $exactMatches->count() === 1
            ? [(int) $exactMatches->first()['product']->id]
            : [];

        return [
            'suggestions' => $suggestions,
            'default_product_ids' => $defaults,
            'product_quantities' => $ranked->take(12)->mapWithKeys(fn (array $item): array => [
                $item['product']->id => $this->quantityForProduct($item['product'], $analysis['items']),
            ])->all(),
            'analysis_summary' => $this->analysisSummary($analysis),
            'analysis' => $analysis,
            'unmatched_items' => $this->unmatchedItems($analysis['items'], $ranked),
        ];
    }

    /** @return list<array{query: string, sku: ?string, quantity: float}> */
    private function fallbackItems(string $text): array
    {
        return collect(preg_split('/\R+/u', $text) ?: [])
            ->map(function (string $line): ?array {
                $line = trim(preg_replace('/^\s*(?:[-–—•*]+\s*|\d+[.)]\s*)/u', '', $line) ?? $line);
                if (mb_strlen($line) < 3) {
                    return null;
                }

                $quantity = 1.0;
                if (preg_match('/(?:\s|^)[xх×]?\s*(\d+(?:[.,]\d+)?)\s*(?:шт\.?|ед\.?|м\.?|компл\.?|x|х|×)?\s*$/ui', $line, $match)) {
                    $quantity = max(0.001, (float) str_replace(',', '.', $match[1]));
                    $line = trim(mb_substr($line, 0, mb_strlen($line) - mb_strlen($match[0])));
                }

                return $line !== '' ? ['query' => $line, 'sku' => null, 'quantity' => $quantity] : null;
            })
            ->filter()
            ->take(25)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function searchProducts(string $search): array
    {
        $search = trim($search);
        $tokens = $this->tokens($search);
        if ($tokens->isEmpty()) {
            return [];
        }

        return Product::query()
            ->with(['brand', 'category', 'supplierProducts.supplier'])
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(function ($query) use ($search, $tokens): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('h1', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('specs', 'like', "%{$search}%");
                foreach ($tokens->take(10) as $token) {
                    $query->orWhere('name', 'like', "%{$token}%")
                        ->orWhere('sku', 'like', "%{$token}%")
                        ->orWhere('h1', 'like', "%{$token}%")
                        ->orWhere('short_description', 'like', "%{$token}%")
                        ->orWhere('specs', 'like', "%{$token}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$token}%"))
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$token}%"))
                        ->orWhereHas('supplierProducts', fn ($supplierProduct) => $supplierProduct
                            ->where('supplier_name', 'like', "%{$token}%")
                            ->orWhere('supplier_article', 'like', "%{$token}%"));
                }
            })
            ->limit(250)
            ->get()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'score' => $this->manualSearchScore($product, $tokens, $search),
            ])
            ->filter(fn (array $item): bool => $item['score'] >= 60)
            ->sortByDesc(fn (array $item): array => [
                $item['score'],
                (int) $item['product']->in_stock,
                (int) $item['product']->views_count,
            ])
            ->take(30)
            ->mapWithKeys(fn (array $item): array => [
                $item['product']->id => $this->productLabel($item['product']),
            ])
            ->all();
    }

    /** @return array<int, string> */
    public function catalogSearchProducts(string $search): array
    {
        $search = trim($search);
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}.\/_-]{1,}/u', Str::lower($search), $matches);
        $tokens = collect($matches[0] ?? [])
            ->map(fn (string $token): string => trim($token, ".,;:()[]{}<>\"'"))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 2)
            ->reject(fn (string $token): bool => in_array($token, [
                'нужен', 'нужна', 'нужно', 'найти', 'подобрать', 'покажи', 'клиенту', 'товар', 'товары',
            ], true))
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        return Product::query()
            ->with(['brand', 'category', 'supplierProducts.supplier'])
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(function (Builder $query) use ($search, $tokens): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('h1', 'like', "%{$search}%");

                foreach ($tokens->take(10) as $token) {
                    $query->orWhere('name', 'like', "%{$token}%")
                        ->orWhere('sku', 'like', "%{$token}%")
                        ->orWhere('h1', 'like', "%{$token}%")
                        ->orWhereHas('brand', fn (Builder $brand): Builder => $brand->where('name', 'like', "%{$token}%"))
                        ->orWhereHas('category', fn (Builder $category): Builder => $category->where('name', 'like', "%{$token}%"))
                        ->orWhereHas('supplierProducts', fn (Builder $offer): Builder => $offer
                            ->where('supplier_name', 'like', "%{$token}%")
                            ->orWhere('supplier_article', 'like', "%{$token}%")
                            ->orWhereHas('supplier', fn (Builder $supplier): Builder => $supplier->where('name', 'like', "%{$token}%")));
                }
            })
            ->limit(250)
            ->get()
            ->map(function (Product $product) use ($search, $tokens): array {
                $haystack = Str::lower(implode(' ', array_filter([
                    $product->name,
                    $product->h1,
                    $product->sku,
                    $product->brand?->name,
                    $product->category?->name,
                    $product->supplierProducts->pluck('supplier_name')->implode(' '),
                    $product->supplierProducts->pluck('supplier_article')->implode(' '),
                    $product->supplierProducts->pluck('supplier.name')->implode(' '),
                ])));
                $score = Str::contains($haystack, Str::lower($search)) ? 200 : 0;
                $score += $tokens->sum(function (string $token) use ($product, $haystack): int {
                    if (Str::lower((string) $product->sku) === $token) {
                        return 300;
                    }

                    return Str::contains($haystack, $token) ? 40 : 0;
                });

                return ['product' => $product, 'score' => $score];
            })
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc(fn (array $item): array => [
                $item['score'],
                (int) $item['product']->in_stock,
                (int) $item['product']->views_count,
            ])
            ->take(30)
            ->mapWithKeys(fn (array $item): array => [
                $item['product']->id => $this->productLabel($item['product']),
            ])
            ->all();
    }

    /** @return array{ready: bool, message: string, suggestions: array<int, string>} */
    public function manualSelection(string $query): array
    {
        $guidance = $this->selectionGuidance($query);

        return $guidance + [
            'suggestions' => $guidance['ready'] ? $this->searchProducts($query) : [],
        ];
    }

    /** @return array{ready: bool, message: string} */
    public function selectionGuidance(string $query): array
    {
        $query = trim($query);
        $normalized = Str::lower($query);
        $isStoveRequest = preg_match('/\b(?:печ[ьи]|кот[её]л|камин)\b/ui', $normalized) === 1;
        $hasModel = preg_match('~\b(?=[a-zа-я0-9./-]*[a-zа-я])(?=[a-zа-я0-9./-]*\d)[a-zа-я0-9]+(?:[-./][a-zа-я0-9]+)+\b~ui', $normalized) === 1;

        if (! $isStoveRequest || $hasModel) {
            return [
                'ready' => true,
                'message' => $hasModel
                    ? 'Указана конкретная модель. Показываем только достаточно близкие совпадения; выбор остаётся за менеджером.'
                    : 'Показываем только достаточно близкие совпадения. Выберите товар вручную.',
            ];
        }

        $missing = [];
        if (preg_match('/бан|саун|парн|отоп|дом|дач|камин|кот[её]л/ui', $normalized) !== 1) {
            $missing[] = 'назначение: баня/сауна или отопление дома';
        }
        if (preg_match('/\d+(?:[.,]\d+)?\s*(?:м[²³23]|кв\.?\s*м|куб\.?\s*м|квт)/ui', $normalized) !== 1) {
            $missing[] = 'площадь или объём помещения (м²/м³), либо требуемая мощность';
        }
        if (preg_match('/дров|угол|пеллет|газ|электр/ui', $normalized) !== 1) {
            $missing[] = 'вид топлива';
        }

        if ($missing === []) {
            return ['ready' => true, 'message' => 'Основные параметры указаны. Выберите подходящий товар вручную.'];
        }

        return [
            'ready' => false,
            'message' => "Сначала уточните у клиента:\n• ".implode("\n• ", $missing)."\nПосле уточнения повторите подбор — товары автоматически в КП не добавляются.",
        ];
    }

    /** @param array<int|string> $ids
     * @return array<int, string>
     */
    public function productLabels(array $ids): array
    {
        return Product::query()->with(['brand', 'supplierProducts.supplier'])->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (Product $product): array => [$product->id => $this->productLabel($product)])
            ->all();
    }

    /** @param Collection<int, Product> $products */
    private function candidateProducts(Collection $tokens): Collection
    {
        $query = Product::query()
            ->where('is_active', true)
            ->where('is_archived', false);

        if ($tokens->isNotEmpty()) {
            $query->where(function ($query) use ($tokens): void {
                foreach ($tokens->take(12) as $token) {
                    $query->orWhere('name', 'like', "%{$token}%")
                        ->orWhere('sku', 'like', "%{$token}%")
                        ->orWhere('h1', 'like', "%{$token}%");
                }
            });
        }

        $products = $query->orderByDesc('in_stock')->orderByDesc('views_count')->limit(250)->get();

        return $products;
    }

    private function score(Product $product, Collection $tokens, string $source): int
    {
        $name = Str::lower($product->name.' '.$product->h1);
        $sku = Str::lower((string) $product->sku);
        $score = 0;

        if ($sku !== '' && Str::contains(Str::lower($source), $sku)) {
            $score += 160;
        }
        foreach ($tokens as $token) {
            if ($sku !== '' && $token === $sku) {
                $score += 120;
            } elseif ($sku !== '' && Str::contains($sku, $token)) {
                $score += 70;
            }
            if (Str::contains($name, $token)) {
                $score += mb_strlen($token) >= 7 ? 35 : 22;
            }
        }
        if ((float) $product->price > 0) {
            $score += 5;
        }
        if ($product->in_stock) {
            $score += 5;
        }

        return $score;
    }

    private function manualSearchScore(Product $product, Collection $tokens, string $source): int
    {
        $source = Str::lower($source);
        $supplierText = $product->supplierProducts->map(fn ($offer): string => implode(' ', array_filter([
            $offer->supplier_name,
            $offer->supplier_article,
            $offer->supplier?->name,
        ])))->implode(' ');
        $haystack = Str::lower(implode(' ', array_filter([
            $product->name,
            $product->h1,
            $product->sku,
            $product->brand?->name,
            $product->category?->name,
            $product->short_description,
            json_encode($product->specs, JSON_UNESCAPED_UNICODE),
            $supplierText,
        ])));
        $score = Str::contains($haystack, $source) ? 180 : 0;

        foreach ($tokens as $token) {
            if (Str::lower((string) $product->sku) === $token) {
                $score += 160;
            } elseif (Str::contains(Str::lower((string) $product->sku), $token)) {
                $score += 80;
            }
            if (Str::contains($haystack, $token)) {
                $score += mb_strlen($token) >= 7 ? 45 : 28;
            }
        }

        return $score + ((float) $product->price > 0 ? 5 : 0) + ($product->in_stock ? 5 : 0);
    }

    private function sourceContainsExactIdentifier(Product $product, string $source): bool
    {
        $sku = trim((string) $product->sku);
        if ($sku === '') {
            return false;
        }

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($sku, '/').'(?![\p{L}\p{N}])/ui', $source) === 1;
    }

    /** @return array{string, string} */
    private function sourceText(CrmMailThread $thread): array
    {
        $message = $thread->messages->where('direction', 'incoming')->sortByDesc('received_at')->first()
            ?? $thread->messages->sortByDesc('received_at')->first();

        $parts = array_filter([
            $thread->subject,
            $message?->subject,
            $message?->text_body ?: strip_tags((string) $message?->html_body),
        ]);
        $pdfNames = [];
        $pdfFailures = 0;

        if ($message) {
            collect($message->metadata['attachments'] ?? [])
                ->values()
                ->filter(fn (mixed $attachment): bool => is_array($attachment)
                    && mb_strtolower(pathinfo((string) ($attachment['name'] ?? ''), PATHINFO_EXTENSION)) === 'pdf')
                ->take(3)
                ->each(function (array $attachment, int $index) use ($message, &$parts, &$pdfNames, &$pdfFailures): void {
                    try {
                        $extracted = $this->pdfTextExtractor->extract($message, $index);
                        $parts[] = "PDF-ВЛОЖЕНИЕ {$extracted['name']}:\n{$extracted['text']}";
                        $pdfNames[] = $extracted['name'].($extracted['method'] === 'deepseek-ocr' ? ' (DeepSeek OCR)' : '');
                    } catch (\Throwable) {
                        $pdfFailures++;
                    }
                });
        }

        $summary = $pdfNames !== []
            ? 'PDF прочитан: '.implode(', ', $pdfNames).'.'
            : '';
        if ($pdfFailures > 0) {
            $summary .= ($summary !== '' ? ' ' : '').'Не удалось прочитать PDF: '.$pdfFailures.'.';
        }

        return [trim(implode("\n", $parts)), $summary];
    }

    private function tokens(string $text): Collection
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}.\/_-]{2,}/u', Str::lower($text), $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $token): string => trim($token, ".,;:()[]{}<>\"'"))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, self::STOP_WORDS, true))
            ->unique()->sortByDesc(fn (string $token): int => mb_strlen($token))->values();
    }

    /** @return array<string, mixed> */
    private function clientPrefill(CrmMailThread $thread, string $text, array $analysis): array
    {
        $counterparty = $thread->counterparty;
        $recognizedCompany = $analysis['legal_name'] ?: $analysis['company'] ?: $this->extractCompany($text);
        $company = ($counterparty?->type === 'organization' && $counterparty?->classification !== 'unclassified')
            ? ($counterparty->legal_name ?: $counterparty->name)
            : ($recognizedCompany ?: $counterparty?->legal_name ?: $counterparty?->name);

        return [
            'type' => 'quote',
            'crm_mail_thread_id' => $thread->id,
            'crm_counterparty_id' => $counterparty?->id,
            'crm_contact_id' => $thread->contact?->id,
            'client_name' => $thread->contact?->full_name ?: $analysis['contact_name'] ?: $thread->participant_name,
            'client_company' => $company,
            'client_legal_name' => $counterparty?->legal_name ?: ($analysis['legal_name'] ?: $company),
            'client_email' => $thread->participant_email ?: ($analysis['emails'][0] ?? null),
            'client_phone' => $thread->contact?->phones_list ?: $counterparty?->phones_list ?: ($analysis['phones'][0] ?? null),
            'client_unp' => $counterparty?->unp ?: $analysis['unp'],
            'client_address' => $counterparty?->legal_address ?: $analysis['legal_address'],
            'client_default_discount_percent' => (float) ($counterparty?->commercial_discount_percent ?? 0),
            'payment_terms' => $counterparty?->commercial_payment_terms ?: 'Безналичный расчёт по выставленному счёту.',
            'delivery_terms' => $counterparty?->commercial_delivery_terms ?: 'Срок и способ поставки согласовываются с менеджером.',
            'currency' => $counterparty?->commercial_currency ?: 'BYN',
        ];
    }

    /** @param list<array{query: string, sku: ?string, quantity: float}> $items */
    private function quantityForProduct(Product $product, array $items): float
    {
        $productSku = Str::lower(trim((string) $product->sku));
        $productName = Str::lower($product->name.' '.$product->h1);
        $bestQuantity = 1.0;
        $bestScore = 0;

        foreach ($items as $item) {
            $itemSku = Str::lower(trim((string) ($item['sku'] ?? '')));
            if ($productSku !== '' && $itemSku !== '' && ($productSku === $itemSku || Str::contains($productSku, $itemSku))) {
                return (float) $item['quantity'];
            }

            $overlap = $this->tokens((string) $item['query'])
                ->filter(fn (string $token): bool => Str::contains($productName, $token))
                ->count();
            if ($overlap > $bestScore) {
                $bestScore = $overlap;
                $bestQuantity = (float) $item['quantity'];
            }
        }

        return $bestScore > 0 ? $bestQuantity : 1.0;
    }

    private function analysisSummary(array $analysis): string
    {
        if (! $analysis['used']) {
            return 'DeepSeek недоступен или не использован — выполнен быстрый подбор по артикулу и словам письма.';
        }

        $items = collect($analysis['items'])->map(function (array $item): string {
            $name = $item['sku'] ?: $item['query'];

            return $name.' × '.$item['quantity'];
        })->implode('; ');

        $requisites = collect([
            $analysis['unp'] ? 'УНП '.$analysis['unp'] : null,
            $analysis['legal_address'] ? 'адрес найден' : null,
            count($analysis['phones']) ? 'телефоны: '.implode(', ', $analysis['phones']) : null,
        ])->filter()->implode(' · ');

        return 'DeepSeek: '.($analysis['legal_name'] ?: $analysis['company'] ?: 'компания не определена').'. '
            .($requisites !== '' ? $requisites.'. ' : '')
            .($items !== '' ? 'Распознано: '.$items.'.' : 'Позиции автоматически не распознаны — выберите товар вручную.');
    }

    private function mergeRegistryData(array $analysis, array $registry): array
    {
        foreach (['company', 'legal_name', 'unp', 'legal_address'] as $field) {
            if (! empty($registry[$field])) {
                $analysis[$field] = $registry[$field];
            }
        }
        $analysis['phones'] = collect(array_merge($registry['phones'] ?? [], $analysis['phones'] ?? []))->filter()->unique()->values()->all();
        $analysis['emails'] = collect(array_merge($registry['emails'] ?? [], $analysis['emails'] ?? []))->filter()->unique()->values()->all();
        $analysis['registry_source'] = $registry['source'] ?? null;

        return $analysis;
    }

    /** @return list<array{query: string, sku: ?string, quantity: float}> */
    private function unmatchedItems(array $items, Collection $ranked): array
    {
        return collect($items)->filter(function (array $item) use ($ranked): bool {
            return ! $ranked->contains(function (array $rankedItem) use ($item): bool {
                /** @var Product $product */
                $product = $rankedItem['product'];
                $sku = Str::lower(trim((string) ($item['sku'] ?? '')));
                if ($sku !== '' && Str::lower(trim((string) $product->sku)) === $sku) {
                    return true;
                }

                return $this->tokens((string) $item['query'])
                    ->filter(fn (string $token): bool => Str::contains(Str::lower($product->name.' '.$product->h1), $token))
                    ->count() >= 2;
            });
        })->values()->all();
    }

    private function extractCompany(string $text): ?string
    {
        if (preg_match('/\b(?:ООО|ОАО|ЗАО|ЧУП|УП|ИП|ОДО)\s*[«"“]?[^\n,;"”]{2,80}[»"”]?/ui', $text, $match)) {
            return trim($match[0]);
        }

        return null;
    }

    private function productLabel(Product $product): string
    {
        $suppliers = $product->supplierProducts
            ->map(fn ($offer): ?string => $offer->supplier?->name ?: $offer->supplier_name)
            ->filter()->unique()->take(2)->implode(', ');

        return implode(' · ', array_filter([
            $product->name,
            $product->sku ? 'арт. '.$product->sku : 'без артикула',
            number_format((float) $product->price, 2, ',', ' ').' BYN',
            $product->availabilityLabel(),
            $suppliers !== '' ? 'поставщик: '.$suppliers : null,
        ]));
    }
}
