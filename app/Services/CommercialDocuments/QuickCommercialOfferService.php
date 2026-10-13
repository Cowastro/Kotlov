<?php

namespace App\Services\CommercialDocuments;

use App\Models\CrmMailThread;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QuickCommercialOfferService
{
    private const STOP_WORDS = [
        'fw', 'fwd', 're', 'запрос', 'коммерческого', 'коммерческое', 'предложение', 'предложения',
        'просим', 'прошу', 'цена', 'цены', 'стоимость', 'наличие', 'срок', 'поставка', 'поставки',
        'добрый', 'день', 'здравствуйте', 'спасибо', 'письмо', 'ответ', 'ответе', 'товар', 'товары',
        'для', 'или', 'как', 'что', 'это', 'нам', 'вам', 'вас', 'при', 'под', 'над', 'без', 'из', 'на', 'по', 'от', 'до',
    ];

    public function __construct(private readonly CommercialOfferMailAnalyzer $mailAnalyzer) {}

    /**
     * @return array{prefill: array<string, mixed>, suggestions: array<int, string>, default_product_ids: list<int>, product_quantities: array<int, float>, analysis_summary: string}
     */
    public function prepare(CrmMailThread $thread, ?bool $useAi = null): array
    {
        $thread->loadMissing(['counterparty.contactPoints', 'contact.contactPoints', 'messages']);
        $text = $this->sourceText($thread);
        $analysis = $this->mailAnalyzer->analyze($thread, $text, $useAi);
        $aiSearchText = collect($analysis['items'])
            ->flatMap(fn (array $item): array => [$item['query'], $item['sku']])
            ->filter()->implode(' ');
        $searchText = trim($text."\n".$aiSearchText);
        $tokens = $this->tokens($searchText);
        $products = $this->candidateProducts($tokens);

        $ranked = $products
            ->map(fn (Product $product): array => [
                'product' => $product,
                'score' => $this->score($product, $tokens, $searchText),
            ])
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

        $bestScore = (int) ($ranked->first()['score'] ?? 0);
        $defaults = $bestScore >= 80
            ? $ranked->filter(fn (array $item): bool => $item['score'] >= max(80, $bestScore - 20))->take(3)
                ->pluck('product.id')->map(fn ($id): int => (int) $id)->values()->all()
            : [];

        return [
            'prefill' => $this->clientPrefill($thread, $text, $analysis),
            'suggestions' => $suggestions,
            'default_product_ids' => $defaults,
            'product_quantities' => $ranked->take(12)->mapWithKeys(fn (array $item): array => [
                $item['product']->id => $this->quantityForProduct($item['product'], $analysis['items']),
            ])->all(),
            'analysis_summary' => $this->analysisSummary($analysis),
        ];
    }

    /** @return array<int, string> */
    public function searchProducts(string $search): array
    {
        $search = trim($search);

        return Product::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('h1', 'like', "%{$search}%");
            })
            ->orderByDesc('in_stock')
            ->orderByDesc('views_count')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [$product->id => $this->productLabel($product)])
            ->all();
    }

    /** @param array<int|string> $ids
     * @return array<int, string>
     */
    public function productLabels(array $ids): array
    {
        return Product::query()->whereIn('id', $ids)->get()
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

        if ($products->isEmpty()) {
            return Product::query()->where('is_active', true)->where('is_archived', false)
                ->where('price', '>', 0)->orderByDesc('in_stock')->orderByDesc('views_count')->limit(12)->get();
        }

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

    private function sourceText(CrmMailThread $thread): string
    {
        $message = $thread->messages->where('direction', 'incoming')->sortByDesc('received_at')->first()
            ?? $thread->messages->sortByDesc('received_at')->first();

        return trim(implode("\n", array_filter([
            $thread->subject,
            $message?->subject,
            $message?->text_body ?: strip_tags((string) $message?->html_body),
        ])));
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
        $company = $counterparty?->legal_name ?: $counterparty?->name ?: $analysis['company'] ?: $this->extractCompany($text);

        return [
            'type' => 'quote',
            'crm_mail_thread_id' => $thread->id,
            'client_name' => $thread->contact?->full_name ?: $analysis['contact_name'] ?: $thread->participant_name,
            'client_company' => $company,
            'client_email' => $thread->participant_email,
            'client_phone' => $thread->contact?->phones_list ?: $counterparty?->phones_list,
            'client_unp' => $counterparty?->unp,
            'client_address' => $counterparty?->legal_address,
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

        return 'DeepSeek: '.($analysis['company'] ?: 'компания не определена').'. '
            .($items !== '' ? 'Распознано: '.$items.'.' : 'Позиции автоматически не распознаны — выберите товар вручную.');
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
        return trim($product->name.' · '.($product->sku ?: 'без артикула').' · '.number_format((float) $product->price, 2, ',', ' ').' BYN');
    }
}
