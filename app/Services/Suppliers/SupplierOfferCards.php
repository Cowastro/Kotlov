<?php

namespace App\Services\Suppliers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Support\Catalog\TskPumpClassifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Supplier price rows without a catalogue card («offers»: supplier_products
 * with product_id NULL, stored by the ТСК and АТМОС syncs). A manager writing
 * a КП finds the model here and turns it into a hidden card with the
 * supplier link, photos and specs; the AI text follows
 * (catalog:finish-offer-cards) and a content manager publishes it.
 */
class SupplierOfferCards
{
    /** @return array<int, string> offer id => label */
    public function search(string $query, int $limit = 20): array
    {
        $tokens = array_values(array_filter(preg_split('/\s+/u', trim($query)) ?: [], fn (string $t): bool => mb_strlen($t) >= 2));
        if ($tokens === []) {
            return [];
        }
        $offers = SupplierProduct::query()->with('supplier:id,name,is_active')
            ->whereNull('product_id')
            ->where('stock_status', '!=', 'out_of_stock')
            ->whereHas('supplier', fn ($q) => $q->where('is_active', true))
            ->where(function ($q) use ($tokens): void {
                foreach ($tokens as $token) {
                    $q->where(fn ($inner) => $inner->where('supplier_name', 'like', '%'.$token.'%')->orWhere('supplier_article', 'like', '%'.$token.'%'));
                }
            })
            ->orderByDesc('in_stock')->orderBy('supplier_name')
            ->limit($limit)->get();

        return $offers->mapWithKeys(fn (SupplierProduct $offer): array => [$offer->id => $this->label($offer)])->all();
    }

    public function label(SupplierProduct $offer): string
    {
        $retail = $this->retail($offer);

        return implode(' · ', array_filter([
            $offer->supplier?->name,
            $offer->supplier_name,
            'арт. '.$offer->supplier_article,
            $offer->price_byn > 0 ? 'опт '.number_format((float) $offer->price_byn, 2, ',', ' ') : null,
            $retail ? 'РРЦ '.number_format($retail, 2, ',', ' ') : null,
            $offer->stock_text ?: ($offer->in_stock ? 'в наличии' : 'под заказ'),
        ]));
    }

    /** Category the card would go to, from the supplier's own classifier. */
    public function suggestedCategoryId(int $offerId): ?int
    {
        $offer = SupplierProduct::query()->with('supplier:id,code')->find($offerId);
        $slug = $offer && $offer->supplier?->code === 'tsk_nasosy' ? TskPumpClassifier::categorySlug((string) $offer->supplier_name) : null;

        return $slug ? Category::query()->where('slug', $slug)->value('id') : null;
    }

    /** A page with photos and specs: the supplier's own card where we know it. */
    public function suggestedSourceUrl(SupplierProduct $offer): ?string
    {
        if ($offer->supplier?->code === 'tsk_nasosy') {
            return $this->aqualiderCards()[ltrim((string) $offer->supplier_article, '0')] ?? null;
        }
        $url = (string) ($offer->raw['url'] ?? $offer->source_url);

        return preg_match('#^https?://#', $url) && ! str_contains($url, '/prices?section=') ? $url : null;
    }

    public function create(int $offerId, ?int $categoryId = null, ?string $sourceUrl = null, ?int $userId = null): Product
    {
        $offer = SupplierProduct::query()->with('supplier')->findOrFail($offerId);
        if ($offer->product_id) {
            return Product::query()->findOrFail($offer->product_id);
        }
        $categoryId = $categoryId ?: $this->suggestedCategoryId($offerId);
        if (! $categoryId || ! Category::query()->whereKey($categoryId)->exists()) {
            throw new RuntimeException('Выберите категорию для новой карточки.');
        }
        $brand = $this->brand((string) ($offer->raw['brand'] ?? ''));
        $name = $this->name((string) $offer->supplier_name, $brand?->name);
        $retail = $this->retail($offer);

        $product = DB::transaction(function () use ($offer, $categoryId, $brand, $name, $retail, $userId): Product {
            $product = Product::query()->create([
                'category_id' => $categoryId,
                'brand_id' => $brand?->id,
                'name' => $name,
                'h1' => $name,
                'slug' => $this->uniqueSlug($name),
                'sku' => $this->uniqueSku(strtoupper(Str::slug((string) $offer->supplier?->code, '')).'-'.$offer->supplier_article),
                'price' => $retail ?? 0,
                'currency' => 'BYN',
                'unit' => 'шт',
                'meta_title' => Str::limit($name, 60, '').' — купить в %city%',
                'meta_description' => Str::limit($name.'. Цена, наличие и доставка по Беларуси.', 175, ''),
                // Hidden until a content manager checks it; the КП line does not need the page.
                'is_active' => false,
                'is_archived' => false,
                'in_stock' => (bool) $offer->in_stock,
                'availability_status' => Product::AVAILABILITY_CHECK,
                'requires_price_confirmation' => $retail === null,
                'created_via' => Product::CREATED_VIA_COMMERCIAL_OFFER,
                'created_by_user_id' => $userId,
            ]);
            $offer->forceFill([
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'match_status' => 'matched',
                'match_confidence' => 'created_from_offer',
            ])->save();
            if ($brand) {
                $offer->supplier?->brands()->syncWithoutDetaching([$brand->id]);
            }

            return $product;
        });

        $sourceUrl = $sourceUrl ?: $this->suggestedSourceUrl($offer);
        if ($sourceUrl) {
            $offer->forceFill(['source_url' => $sourceUrl])->save();
            $product->forceFill(['content_source_url' => $sourceUrl])->save();
            try {
                // Photos, specs and documents now; the AI text comes from catalog:finish-offer-cards.
                app(ProductSourceEnricher::class)->enrich($product, $sourceUrl, ['update_content' => false]);
            } catch (\Throwable $e) {
                Log::warning('Offer card enrichment failed', ['product_id' => $product->id, 'url' => $sourceUrl, 'error' => $e->getMessage()]);
            }
        }
        app(SupplierProductAvailabilityService::class)->refreshMany([$product->id]);

        return $product->fresh();
    }

    /**
     * Phase 2: a model in no supplier price. The card is built from a page the
     * manager picked among the web search results; the price comes from the КП
     * line, so the card waits for a price confirmation.
     */
    public function createFromWeb(string $name, ?string $brandName, int $categoryId, string $sourceUrl, ?float $price = null, ?int $userId = null): Product
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if (mb_strlen($name) < 3) {
            throw new RuntimeException('Укажите название товара.');
        }
        if (! Category::query()->whereKey($categoryId)->exists()) {
            throw new RuntimeException('Выберите категорию для новой карточки.');
        }
        if (! preg_match('#^https?://[^/]+/.+#', $sourceUrl)) {
            throw new RuntimeException('Выберите страницу товара из результатов поиска.');
        }
        $brand = $this->brand((string) $brandName);
        $price = $price !== null && $price > 0 ? round($price, 2) : null;
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brand?->id,
            'name' => $name,
            'h1' => $name,
            'slug' => $this->uniqueSlug($name),
            'sku' => $this->uniqueSku('KP-'.now()->format('ymd').'-'.Str::upper(Str::random(4))),
            'price' => $price ?? 0,
            'currency' => 'BYN',
            'unit' => 'шт',
            'meta_title' => Str::limit($name, 60, '').' — купить в %city%',
            'meta_description' => Str::limit($name.'. Цена, наличие и доставка по Беларуси.', 175, ''),
            'content_source_url' => $sourceUrl,
            'is_active' => false,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => true,
            'created_via' => Product::CREATED_VIA_COMMERCIAL_OFFER,
            'created_by_user_id' => $userId,
        ]);
        try {
            app(ProductSourceEnricher::class)->enrich($product, $sourceUrl, ['update_content' => false]);
        } catch (\Throwable $e) {
            Log::warning('Web offer card enrichment failed', ['product_id' => $product->id, 'url' => $sourceUrl, 'error' => $e->getMessage()]);
        }

        return $product->fresh();
    }

    public function retail(SupplierProduct $offer): ?float
    {
        $retail = (float) ($offer->raw['retail'] ?? 0);

        return $retail > 0 ? round($retail, 2) : null;
    }

    private function brand(string $name): ?Brand
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return Brand::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? Brand::query()->create(['name' => $name, 'slug' => Str::slug($name) ?: Str::random(8), 'h1' => $name, 'is_active' => true]);
    }

    /** «WELLMIX Циркуляционный насос Wellmix WRS …» → the brand once. */
    private function name(string $supplierName, ?string $brand): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $supplierName) ?? $supplierName);
        if ($brand && mb_substr_count(mb_strtolower($name), mb_strtolower($brand)) > 1 && str_starts_with(mb_strtolower($name), mb_strtolower($brand))) {
            $name = trim(mb_substr($name, mb_strlen($brand)));
        }

        return $name;
    }

    /** @return array<string, string> ТСК article => aqualider.by card */
    private function aqualiderCards(): array
    {
        return Cache::remember('supplier-offers:aqualider-cards', now()->addDay(), function (): array {
            $xml = (string) Http::timeout(60)->retry(2, 2000, throw: false)->get('https://aqualider.by/sitemap.xml')->body();
            preg_match_all('#<loc>(https://aqualider\.by/p/[^<]*-(\d{4,}))</loc>#', $xml, $m);
            $map = [];
            foreach ($m[2] as $i => $article) {
                $map[ltrim($article, '0')] = $m[1][$i];
            }

            return $map;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $slug = $base = Str::slug($name) ?: 'tovar-'.Str::random(6);
        for ($i = 2; Product::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function uniqueSku(string $base): string
    {
        $sku = $base = Str::limit($base, 60, '');
        for ($i = 2; Product::query()->where('sku', $sku)->exists(); $i++) {
            $sku = $base.'-'.$i;
        }

        return $sku;
    }
}
