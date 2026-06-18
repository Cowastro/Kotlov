<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductCatalogAiAdvisor
{
    private const BRAND_RULES = [
        'aston' => ['aston', 'астон'],
        'doorwood' => ['doorwood', 'door wood', 'дорвуд'],
        'vezuvij' => ['vezuviy', 'vezuvij', 'везувий'],
        'teplodar' => ['teplodar', 'теплодар', 'былина', 'сиеста', 'сибирский утес', 'сибирский утёс'],
        'nmk' => ['novmk', 'нмк', 'сибирь'],
        'tmf' => ['tmf', 'тмф', 'термофор', 'termofor'],
        'everest' => ['everest', 'эверест'],
        'etna' => ['etna', 'этна'],
        'harvia' => ['harvia', 'харвия'],
        'grillver' => ['grillver', 'гриллвер'],
        'fakel' => ['fakel', 'факел'],
    ];

    private const CATEGORY_RULES = [
        'pechnoe-i-kaminnoe-lite' => ['двер.*печ', 'дверц.*печ', 'чугун.*двер', 'стекло.*печ'],
        'dveri-dlya-ban-i-saun' => ['doorwood', 'двер.*саун', 'двер.*бани', 'дверь.*бан'],
        'elektrokamenki' => ['электрокамен', 'электр.*печ', 'harvia'],
        'mangaly' => ['мангал', 'грил', 'шашлык'],
        'kazany' => ['казан'],
        'pechi-dlya-kazana' => ['печ.*казан'],
        'kotly' => ['котел', 'котёл', 'куппер'],
        'topki' => ['топка'],
        'pechi-kaminy' => ['печь-камин', 'печь камин', 'аот'],
        'aksessuary-dlya-bani' => ['шапк', 'мочал', 'коврик', 'ведро', 'обруч', 'средств'],
        'kamni-dlya-bani' => ['камень', 'камни', 'жадеит', 'нефрит', 'талько', 'кварцит', 'габбро', 'диабаз'],
        'drovyanye-pechi-dlya-bani' => ['печ.*бан', 'банн.*печ', 'aston', 'астон', 'былина', 'сибирь'],
    ];

    public function __construct(private AiContentEnricher $ai) {}

    /**
     * @return array<string, mixed>
     */
    public function advise(Product $product, bool $useAi = true): array
    {
        $product->loadMissing(['brand', 'category', 'supplierProducts.supplier']);

        $context = $this->context($product);
        $ruleAdvice = $this->ruleAdvice($context);
        $aiAdvice = $useAi && $this->ai->isAvailable()
            ? $this->aiAdvice($product, $context, $ruleAdvice)
            : [];

        $brandSlug = $this->chooseBrandSlug($ruleAdvice, $aiAdvice);
        $categorySlug = $this->chooseCategorySlug($ruleAdvice, $aiAdvice);

        $brand = $this->resolveBrand($brandSlug);
        $category = $this->resolveCategory($categorySlug);
        $duplicate = $this->findDuplicate($product);

        $confidence = max((float) ($ruleAdvice['confidence'] ?? 0.0), (float) ($aiAdvice['confidence'] ?? 0.0));
        $reason = $this->mergeReasons($ruleAdvice, $aiAdvice);

        $changes = [];
        if ($brand && (int) $product->brand_id !== (int) $brand->id) {
            $changes['brand_id'] = (int) $brand->id;
        }
        if ($category && (int) $product->category_id !== (int) $category->id) {
            $changes['category_id'] = (int) $category->id;
        }

        return [
            'product_id' => (int) $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'current_brand_id' => $product->brand_id,
            'current_brand' => $product->brand?->name,
            'current_category_id' => $product->category_id,
            'current_category' => $product->category?->name,
            'suggested_brand_id' => $brand?->id,
            'suggested_brand' => $brand?->name,
            'suggested_brand_slug' => $brand?->slug,
            'suggested_category_id' => $category?->id,
            'suggested_category' => $category?->name,
            'suggested_category_slug' => $category?->slug,
            'duplicate_product_id' => $duplicate?->id,
            'duplicate_sku' => $duplicate?->sku,
            'duplicate_name' => $duplicate?->name,
            'confidence' => max(0.0, min(1.0, $confidence)),
            'reason' => $reason !== '' ? $reason : 'Каталогическая подсказка по названию и данным поставщика.',
            'changes' => $changes,
            'source' => $aiAdvice !== [] ? 'ai' : 'rules',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function context(Product $product): array
    {
        $supplierProducts = $product->supplierProducts;

        return [
            'name' => (string) $product->name,
            'brand' => (string) ($product->brand?->name ?? ''),
            'category' => (string) ($product->category?->name ?? ''),
            'supplier_names' => $supplierProducts->pluck('supplier_name')->filter()->implode(' | '),
            'supplier_articles' => $supplierProducts->pluck('supplier_article')->filter()->implode(' | '),
            'source_urls' => $supplierProducts->pluck('source_url')->filter()->implode(' | '),
            'suppliers' => $supplierProducts->map(fn ($sp) => $sp->supplier?->name)->filter()->unique()->implode(' | '),
        ];
    }

    /**
     * @param array<string, string> $context
     * @return array<string, mixed>
     */
    private function ruleAdvice(array $context): array
    {
        $haystack = $this->normalize(implode(' ', $context));
        $brandSlug = null;
        $categorySlug = null;
        $reasons = [];

        foreach (self::BRAND_RULES as $slug => $needles) {
            foreach ($needles as $needle) {
                if ($this->matches($haystack, $needle)) {
                    if ($slug === 'nmk' && $this->matches($haystack, 'сибирский ут')) {
                        continue;
                    }
                    $brandSlug = $slug;
                    $reasons[] = 'бренд по "' . $needle . '"';
                    break 2;
                }
            }
        }

        foreach (self::CATEGORY_RULES as $slug => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->matches($haystack, $pattern)) {
                    $categorySlug = $this->resolveCategoryRuleSlug($slug);
                    $reasons[] = 'категория по "' . $pattern . '"';
                    break 2;
                }
            }
        }

        $confidence = 0.25;
        if ($brandSlug && $categorySlug) {
            $confidence = 0.86;
        } elseif ($brandSlug || $categorySlug) {
            $confidence = 0.72;
        }

        return [
            'brand_slug' => $brandSlug,
            'category_slug' => $categorySlug,
            'confidence' => $confidence,
            'reason' => $reasons ? implode('; ', $reasons) : 'правила не нашли уверенную подсказку',
        ];
    }

    /**
     * @param array<string, mixed> $ruleAdvice
     * @param array<string, mixed> $aiAdvice
     */
    private function chooseBrandSlug(array $ruleAdvice, array $aiAdvice): ?string
    {
        $ruleSlug = filled($ruleAdvice['brand_slug'] ?? null) ? (string) $ruleAdvice['brand_slug'] : null;
        $aiSlug = filled($aiAdvice['brand_slug'] ?? null) ? (string) $aiAdvice['brand_slug'] : null;
        $aiConfidence = (float) ($aiAdvice['confidence'] ?? 0.0);

        if ($ruleSlug && $aiSlug && $ruleSlug !== $aiSlug) {
            return $aiConfidence >= 0.94 ? $aiSlug : $ruleSlug;
        }

        return $ruleSlug ?: $aiSlug;
    }

    /**
     * @param array<string, mixed> $ruleAdvice
     * @param array<string, mixed> $aiAdvice
     */
    private function chooseCategorySlug(array $ruleAdvice, array $aiAdvice): ?string
    {
        $ruleSlug = filled($ruleAdvice['category_slug'] ?? null) ? (string) $ruleAdvice['category_slug'] : null;
        $aiSlug = filled($aiAdvice['category_slug'] ?? null) ? (string) $aiAdvice['category_slug'] : null;
        $aiConfidence = (float) ($aiAdvice['confidence'] ?? 0.0);

        if ($ruleSlug && $this->isProtectedCategoryRuleSlug($ruleSlug)) {
            return $ruleSlug;
        }

        if ($ruleSlug && $aiSlug && $ruleSlug !== $aiSlug) {
            return $aiConfidence >= 0.90 ? $aiSlug : $ruleSlug;
        }

        return $ruleSlug ?: $aiSlug;
    }

    /**
     * @param array<string, mixed> $ruleAdvice
     * @param array<string, mixed> $aiAdvice
     */
    private function mergeReasons(array $ruleAdvice, array $aiAdvice): string
    {
        $parts = [];
        if (filled($ruleAdvice['reason'] ?? null)) {
            $parts[] = 'Правила: ' . $ruleAdvice['reason'];
        }
        if (filled($aiAdvice['reason'] ?? null)) {
            $parts[] = 'AI: ' . $aiAdvice['reason'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, string> $context
     * @param array<string, mixed> $ruleAdvice
     * @return array<string, mixed>
     */
    private function aiAdvice(Product $product, array $context, array $ruleAdvice): array
    {
        $brands = Brand::query()
            ->orderBy('name')
            ->limit(180)
            ->get(['name', 'slug'])
            ->map(fn (Brand $brand): string => $brand->name . ' [' . $brand->slug . ']')
            ->implode("\n");

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (Category $category): string => $category->name . ' [' . $category->slug . ']')
            ->implode("\n");

        $prompt = <<<PROMPT
Ты помогаешь разобрать товары интернет-магазина kotlov.by после импорта поставщика.
Нужно предложить правильный бренд и категорию только из списков ниже.
Если не уверен, верни null. Не придумывай новые бренды и категории.
Важно: Банька, BANIA.by и bania.by — это поставщик/техническая заглушка, а НЕ реальный бренд товара.
Если текущий бренд Банька, не доверяй ему и ищи настоящий бренд в названии, артикуле и URL.
Если локальная подсказка правил нашла бренд по явному слову в названии, не меняй его без очень веской причины.

Товар:
SKU: {$product->sku}
Название: {$context['name']}
Текущий бренд: {$context['brand']}
Текущая категория: {$context['category']}
Поставщики: {$context['suppliers']}
Названия поставщика: {$context['supplier_names']}
Артикулы поставщика: {$context['supplier_articles']}
URL источников: {$context['source_urls']}

Подсказка правил:
brand_slug={$ruleAdvice['brand_slug']}
category_slug={$ruleAdvice['category_slug']}
reason={$ruleAdvice['reason']}

Бренды:
{$brands}

Категории:
{$categories}

Ответ строго JSON:
{"brand_slug":null,"category_slug":null,"confidence":0.0,"reason":"коротко почему"}
PROMPT;

        $answer = $this->ai->complete($prompt, 500);
        if (! $answer) {
            return [];
        }

        $json = $this->extractJson($answer);
        if (! is_array($json)) {
            return [];
        }

        return [
            'brand_slug' => filled($json['brand_slug'] ?? null) ? (string) $json['brand_slug'] : null,
            'category_slug' => filled($json['category_slug'] ?? null) ? (string) $json['category_slug'] : null,
            'confidence' => is_numeric($json['confidence'] ?? null) ? (float) $json['confidence'] : 0.5,
            'reason' => trim((string) ($json['reason'] ?? '')),
        ];
    }

    private function resolveBrand(?string $slug): ?Brand
    {
        if (! $slug) {
            return null;
        }

        return Brand::query()->where('slug', $slug)->first();
    }

    private function resolveCategory(?string $slug): ?Category
    {
        if (! $slug) {
            return null;
        }

        return Category::query()->where('slug', $slug)->first();
    }

    private function resolveCategoryRuleSlug(string $slug): string
    {
        if ($slug !== 'kamni-dlya-bani') {
            return $slug;
        }

        $existingSlug = Category::query()
            ->where('is_active', true)
            ->whereIn('slug', [
                'kamni-dlya-bani',
                'kamni-dlya-ban-i-saun',
                'kamni-dlya-sauny',
                'kamni-dlya-pechi',
                'bannye-kamni',
            ])
            ->orderByRaw("FIELD(slug, 'kamni-dlya-bani', 'kamni-dlya-ban-i-saun', 'kamni-dlya-sauny', 'kamni-dlya-pechi', 'bannye-kamni')")
            ->value('slug');

        return $existingSlug ?: 'aksessuary-dlya-bani';
    }

    private function isProtectedCategoryRuleSlug(string $slug): bool
    {
        return in_array($slug, [
            'kamni-dlya-bani',
            'kamni-dlya-ban-i-saun',
            'kamni-dlya-sauny',
            'kamni-dlya-pechi',
            'bannye-kamni',
        ], true);
    }

    private function findDuplicate(Product $product): ?Product
    {
        $normalized = $this->normalizeName((string) $product->name);
        if ($normalized === '') {
            return null;
        }

        $firstToken = collect(explode(' ', $normalized))
            ->first(fn (string $token): bool => mb_strlen($token) >= 4);

        return Product::query()
            ->whereKeyNot($product->id)
            ->where('is_archived', false)
            ->when($firstToken, fn ($query) => $query->where('name', 'like', '%' . $firstToken . '%'))
            ->limit(300)
            ->get(['id', 'sku', 'name'])
            ->first(fn (Product $candidate): bool => $this->normalizeName((string) $candidate->name) === $normalized);
    }

    private function normalizeName(string $value): string
    {
        $value = $this->normalize($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function matches(string $haystack, string $pattern): bool
    {
        $pattern = $this->normalize($pattern);

        if (! str_contains($pattern, '.*')) {
            return str_contains($haystack, $pattern);
        }

        return (bool) preg_match('/' . str_replace('\.\*', '.*', preg_quote($pattern, '/')) . '/u', $haystack);
    }

    private function normalize(string $value): string
    {
        $value = Str::lower($value);
        $value = str_replace(['ё', '-', '_', '/', '\\'], ['е', ' ', ' ', ' ', ' '], $value);
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $answer): ?array
    {
        $answer = trim(preg_replace('/```(?:json)?/i', '', $answer) ?? $answer);
        $start = strpos($answer, '{');
        $end = strrpos($answer, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $data = json_decode(substr($answer, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }
}
