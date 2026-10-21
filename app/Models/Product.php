<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

class Product extends Model
{
    public const AVAILABILITY_IN_STOCK = 'in_stock';

    public const AVAILABILITY_CHECK = 'check';

    public const AVAILABILITY_OUT_OF_STOCK = 'out_of_stock';

    public const PUBLIC_SALE_SLUGS = [
        'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt',
        'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-eb140',
        'pelletnaya-gorelka-hotta-ceramik-20-kvt-komplekt-1',
        'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3',
    ];

    private const SUPPLIER_TECHNICAL_ATTRIBUTES = [
        'Поставщик',
        'Артикул поставщика',
        'Источник',
        'supplier',
        'supplier_article',
        'source_url',
        'source_wp_id',
    ];

    public static function supplierTechnicalAttributeNames(): array
    {
        return self::SUPPLIER_TECHNICAL_ATTRIBUTES;
    }

    protected $fillable = [
        'category_id', 'brand_id', 'supplier_id',
        'name', 'slug', 'h1', 'sku',
        'price', 'price_old', 'manual_purchase_price_byn', 'manual_price_updated_by', 'manual_price_updated_at', 'currency',
        'content', 'short_description',
        'images', 'specs', 'service_info', 'documents', 'promo_flags', 'video_url', 'content_source_url',
        'weight', 'unit', 'warranty',
        'is_active', 'is_archived', 'in_stock', 'availability_status', 'stock_qty',
        'requires_price_confirmation', 'manual_stock_management',
        'is_featured', 'is_new', 'is_sale', 'sort_order', 'variant_selector_excluded',
        'meta_title', 'meta_keywords', 'meta_description',
        'rating', 'reviews_count', 'views_count', 'popularity_score', 'popularity_updated_at',
    ];

    protected $casts = [
        'images' => 'array',
        'specs' => 'array',
        'service_info' => 'array',
        'documents' => 'array',
        'promo_flags' => 'array',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
        'requires_price_confirmation' => 'boolean',
        'manual_stock_management' => 'boolean',
        'in_stock' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_sale' => 'boolean',
        'variant_selector_excluded' => 'boolean',
        'price' => 'decimal:2',
        'price_old' => 'decimal:2',
        'manual_purchase_price_byn' => 'decimal:2',
        'manual_price_updated_at' => 'datetime',
        'popularity_score' => 'decimal:4',
        'popularity_updated_at' => 'datetime',
    ];

    public static function availabilityStatusOptions(): array
    {
        return [
            self::AVAILABILITY_IN_STOCK => 'В наличии',
            self::AVAILABILITY_CHECK => 'Уточняйте наличие',
            self::AVAILABILITY_OUT_OF_STOCK => 'Нет в наличии',
        ];
    }

    public function effectiveAvailabilityStatus(): string
    {
        if (! $this->is_active || $this->is_archived) {
            return self::AVAILABILITY_OUT_OF_STOCK;
        }

        $status = $this->availability_status ?: null;

        // Explicitly marked "нет в наличии" — only case that shows "Нет в наличии"
        if ($status === self::AVAILABILITY_OUT_OF_STOCK) {
            return self::AVAILABILITY_OUT_OF_STOCK;
        }

        // A pricing hold is stronger than a fresh supplier balance. The card
        // remains public with its last known price, but checkout must wait for
        // a manager to confirm that the retail price still covers procurement.
        if ($this->requires_price_confirmation) {
            return self::AVAILABILITY_CHECK;
        }

        if ($this->manual_stock_management) {
            return $status === self::AVAILABILITY_IN_STOCK && $this->in_stock
                ? self::AVAILABILITY_IN_STOCK
                : self::AVAILABILITY_CHECK;
        }

        // "В наличии" is allowed only when a linked supplier channel confirms
        // a positive/available stock.  A bare flag on the product card is not
        // enough: it can be left over after a link or an import was disabled.
        if ($this->hasAvailableSupplierChannel() || $this->hasFreshConfirmedPresence()) {
            return self::AVAILABILITY_IN_STOCK;
        }

        // A linked channel without a positive balance, or a product without a
        // channel at all, must be confirmed by a manager before sale.
        if ($status === self::AVAILABILITY_CHECK) {
            return self::AVAILABILITY_CHECK;
        }

        // Not in stock but not explicitly marked — show "Уточняйте наличие"
        return self::AVAILABILITY_CHECK;
    }

    public function availabilityLabel(): string
    {
        return match ($this->publicAvailabilityStatus()) {
            self::AVAILABILITY_IN_STOCK => 'В наличии',
            self::AVAILABILITY_CHECK => 'Доступно к заказу',
            default => 'Нет в наличии',
        };
    }

    public function publicAvailabilityLabel(): string
    {
        if (! $this->hasAssignedSupplier()) {
            return 'Уточняйте наличие';
        }

        $status = $this->publicAvailabilityStatus();
        if ($status === self::AVAILABILITY_IN_STOCK) {
            $quantity = $this->confirmedStockQuantity();
            $unit = in_array($this->unit, [null, '', 'шт', 'шт.'], true) ? 'шт.' : $this->unit;

            return $quantity !== null
                ? 'В наличии: '.rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',').' '.$unit
                : 'В наличии';
        }

        if ($status === self::AVAILABILITY_CHECK && ($days = $this->supplierDeliveryDays()) !== null) {
            return 'Поставка до '.$days.' рабочих дней';
        }

        return $status === self::AVAILABILITY_CHECK ? 'Доступно к заказу' : 'Нет в наличии';
    }

    private function supplierDeliveryDays(): ?int
    {
        $links = $this->relationLoaded('supplierProducts')
            ? $this->supplierProducts
            : $this->supplierProducts()->with('supplier:id,is_active')->get();

        $days = $links
            ->filter(fn (SupplierProduct $link): bool => $link->supplier?->is_active === true
                && $link->stock_status === 'preorder'
                && (int) $link->delivery_days > 0)
            ->min('delivery_days');

        return $days !== null ? (int) $days : null;
    }

    /** The same resolved state is used by public labels, the cart and checkout. */
    public function publicAvailabilityStatus(): string
    {
        if (! $this->hasAssignedSupplier()) {
            return self::AVAILABILITY_CHECK;
        }

        return $this->effectiveAvailabilityStatus();
    }

    private function hasFreshConfirmedPresence(): bool
    {
        $links = $this->relationLoaded('supplierProducts')
            ? $this->supplierProducts
            : $this->supplierProducts()->with('supplier:id,code,is_active')->get();

        return $links->contains(function (SupplierProduct $link): bool {
            $confirmedAt = $link->last_stock_synced_at ?? $link->last_synced_at;

            return $link->stock_quantity === null && $link->in_stock
                && (
                    $link->stock_status === SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE
                    || (
                        $confirmedAt?->gte(now()->subHours(SupplierProduct::STALE_AFTER_HOURS))
                        && in_array($link->stock_status, ['low_without_quantity', 'available_without_quantity', 'available', 'in_stock'], true)
                    )
                )
                && $link->supplier?->is_active === true;
        });
    }

    /** Exact fresh channel quantities only, never the potentially stale integer summary. */
    public function confirmedStockQuantity(): ?float
    {
        if ($this->effectiveAvailabilityStatus() !== self::AVAILABILITY_IN_STOCK) {
            return null;
        }
        if ($this->manual_stock_management) {
            return (float) $this->stock_qty > 0 ? (float) $this->stock_qty : null;
        }
        $integrations = $this->relationLoaded('integrationProducts')
            ? $this->integrationProducts
            : $this->integrationProducts()->with('source')->get();
        $quantities = $integrations->filter(fn ($item) => $item->match_status === 'matched' && $item->hasFreshPositiveStock())
            ->groupBy('integration_source_id')->map(fn ($items) => (float) $items->max('stock_quantity'));
        if ($quantities->isNotEmpty()) {
            return $quantities->sum();
        }
        $links = $this->relationLoaded('supplierProducts')
            ? $this->supplierProducts
            : $this->supplierProducts()->with('supplier:id,is_active')->get();
        $quantities = $links->filter(fn ($item) => $item->supplier?->is_active === true && $item->hasFreshPositiveStock())
            ->groupBy('supplier_id')->map(fn ($items) => (float) $items->max('stock_quantity'));

        return $quantities->isNotEmpty() ? $quantities->sum() : null;
    }

    public function canBeOrdered(): bool
    {
        return $this->is_active
            && ! $this->is_archived
            && (float) $this->price > 0
            && in_array($this->effectiveAvailabilityStatus(), [
                self::AVAILABILITY_IN_STOCK,
                self::AVAILABILITY_CHECK,
            ], true);
    }

    /**
     * The owner permits normal checkout for fresh confirmed availability with
     * or without a numeric balance. Unknown quantities remain null.
     */
    public function canBePurchased(): bool
    {
        return $this->is_active
            && ! $this->is_archived
            && (float) $this->price > 0
            && $this->effectiveAvailabilityStatus() === self::AVAILABILITY_IN_STOCK;
    }

    /**
     * A storefront order must always have an operational supplier route.
     * A price or an old stock flag on the product itself is not enough.
     */
    public function hasAssignedSupplier(): bool
    {
        return $this->operationalSuppliers()->isNotEmpty();
    }

    /** @return Collection<int, SupplierProduct> */
    public function operationalSupplierProducts(): Collection
    {
        $items = $this->relationLoaded('supplierProducts')
            ? $this->supplierProducts
            : $this->supplierProducts()->with(['supplier.latestChannelTransition'])->get();

        return $items
            ->filter(fn (SupplierProduct $item): bool => $item->supplier?->is_active === true
                && ($item->match_confidence === 'manual_price_list'
                    || ($item->match_confidence === 'official_source' && (float) $item->price_byn > 0)
                    || $item->supplier->usesLegacyChannel()))
            ->values();
    }

    /** @return Collection<int, IntegrationProduct> */
    public function operationalIntegrationProducts(): Collection
    {
        $items = $this->relationLoaded('integrationProducts')
            ? $this->integrationProducts
            : $this->integrationProducts()->with(['source.supplier'])->get();

        return $items
            ->filter(fn (IntegrationProduct $item): bool => $item->match_status === 'matched'
                && $item->source?->is_active === true
                && $item->source?->supplier?->is_active === true)
            ->values();
    }

    /** @return Collection<int, ProductPurchasePriceSource> */
    public function usablePurchasePriceSources(): Collection
    {
        $sources = $this->relationLoaded('purchasePriceSources')
            ? $this->purchasePriceSources
            : $this->purchasePriceSources()->with('supplier')->get();

        return $sources
            ->filter(fn (ProductPurchasePriceSource $source): bool => $source->isUsable()
                && $source->supplier?->is_active === true)
            ->values();
    }

    /** @return Collection<int, Supplier> */
    public function operationalSuppliers(): Collection
    {
        return $this->usablePurchasePriceSources()
            ->map(fn (ProductPurchasePriceSource $source): ?Supplier => $source->supplier)
            ->concat($this->operationalSupplierProducts()
                ->map(fn (SupplierProduct $item): ?Supplier => $item->supplier))
            ->concat($this->operationalIntegrationProducts()
                ->map(fn (IntegrationProduct $item): ?Supplier => $item->source?->supplier))
            ->filter()
            ->unique('id')
            ->values();
    }

    public function canBeAddedToCart(): bool
    {
        return ! $this->requires_price_confirmation
            && $this->canBeOrdered()
            && $this->hasAssignedSupplier();
    }

    public function hasVerifiedPublicPrice(): bool
    {
        return (float) $this->price > 0
            && ! $this->requires_price_confirmation
            && $this->hasAssignedSupplier();
    }

    public function requiresAvailabilityConfirmation(): bool
    {
        return $this->canBeOrdered()
            && $this->effectiveAvailabilityStatus() === self::AVAILABILITY_CHECK;
    }

    public function hasLinkedSupplierChannel(): bool
    {
        $hasLegacyLink = $this->relationLoaded('supplierProducts')
            ? $this->supplierProducts->isNotEmpty()
            : $this->supplierProducts()->exists();

        if ($hasLegacyLink) {
            return true;
        }

        if ($this->relationLoaded('integrationProducts')) {
            return $this->integrationProducts->contains(
                fn (IntegrationProduct $item): bool => $item->match_status === 'matched'
                    && $item->source?->is_active === true
            );
        }

        return $this->integrationProducts()
            ->where('match_status', 'matched')
            ->whereHas('source', fn ($source) => $source->where('is_active', true))
            ->exists();
    }

    public function hasAvailableSupplierChannel(): bool
    {
        $legacyAvailable = $this->relationLoaded('supplierProducts')
            ? $this->supplierProducts->contains(
                fn (SupplierProduct $item): bool => $item->supplier?->is_active === true && $item->confirmsAvailability()
            )
            : $this->supplierProducts()
                ->whereHas('supplier', fn ($supplier) => $supplier->where('is_active', true))
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->where('in_stock', true)
                            ->where('stock_status', SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE);
                    })->orWhere(function ($query): void {
                        $query->where('stock_quantity', '>', 0)
                            ->whereRaw(
                                'COALESCE(last_stock_synced_at, last_synced_at, updated_at) >= ?',
                                [now()->subHours(SupplierProduct::STALE_AFTER_HOURS)]
                            );
                    });
                })
                ->exists();

        if ($legacyAvailable) {
            return true;
        }

        if ($this->relationLoaded('integrationProducts')) {
            return $this->integrationProducts->contains(
                fn (IntegrationProduct $item): bool => $item->match_status === 'matched'
                    && $item->hasFreshPositiveStock()
            );
        }

        return $this->integrationProducts()
            ->with('source')
            ->where('match_status', 'matched')
            ->where('stock_quantity', '>', 0)
            ->whereHas('source', fn ($source) => $source->where('is_active', true))
            ->get()
            ->contains(fn (IntegrationProduct $item): bool => $item->hasFreshPositiveStock());
    }

    public function purchasePriceSources(): HasMany
    {
        return $this->hasMany(ProductPurchasePriceSource::class);
    }

    public function scopeWithSupplierChannels($query)
    {
        return $query->with([
            'supplierProducts:id,product_id,supplier_id,supplier_article,supplier_name,price_byn,in_stock,stock_status,stock_quantity,stock_text,warehouse_name,delivery_days,match_confidence,last_stock_synced_at,last_synced_at,updated_at',
            'supplierProducts.supplier:id,crm_counterparty_id,code,name,contact,is_active',
            'supplierProducts.supplier.crmCounterparty.contactPoints',
            'supplierProducts.supplier.latestChannelTransition',
            'integrationProducts:id,product_id,integration_source_id,external_sku,name,price,price_currency,price_currency_rate,price_tax_mode,price_vat_rate,price_byn,stock_quantity,match_status,last_seen_at,last_offer_seen_at,stock_confirmed_at',
            'integrationProducts.source:id,supplier_id,code,name,driver,price_currency,price_currency_rate,is_active,settings',
            'integrationProducts.source.supplier:id,crm_counterparty_id,code,name,contact,is_active',
            'integrationProducts.source.supplier.crmCounterparty.contactPoints',
            'integrationProducts.source.latestSuccessfulCatalogExchangeRun',
            'purchasePriceSources.supplier:id,crm_counterparty_id,code,name,contact,is_active',
        ]);
    }

    public function heatPumpCatalogMeta(): ?array
    {
        if ($this->category?->slug !== 'teplovyie-nasosyi' || $this->brand?->slug !== 'kotlov-ge') {
            return null;
        }

        $specs = collect($this->specs ?: [])
            ->filter(fn ($spec) => is_array($spec) && isset($spec['key'], $spec['value']))
            ->mapWithKeys(fn ($spec) => [mb_strtolower(trim((string) $spec['key'])) => trim((string) $spec['value'])]);

        $refrigerant = $specs->get('хладагент');
        $power = $specs->get('мощность');
        $waterTemperature = $specs->get('температура воды');
        $temperature = null;

        if ($waterTemperature && preg_match('/отоплен\S*\s+до\s*(\d+)/ui', $waterTemperature, $match)) {
            $temperature = 'до '.$match[1].' °C';
        } elseif ($waterTemperature && preg_match('/до\s*(\d+)/ui', $waterTemperature, $match)) {
            $temperature = 'до '.$match[1].' °C';
        }

        $chips = collect([$refrigerant, $power, $temperature])
            ->filter(fn ($value) => $value && mb_strtolower($value) !== 'уточняйте')
            ->unique()
            ->values()
            ->all();

        if ($chips === []) {
            return null;
        }

        return [
            'chips' => $chips,
            'purpose' => match (mb_strtoupper((string) $refrigerant)) {
                'R290' => 'Радиаторы и горячая вода',
                'R32' => 'Тёплый пол и охлаждение',
                default => 'Отопление и горячая вода',
            },
        ];
    }

    public function isPublicSale(): bool
    {
        return $this->is_sale && in_array($this->slug, self::PUBLIC_SALE_SLUGS, true);
    }

    public function qualifiesForOnlineOrderDiscount(): bool
    {
        $categorySlug = $this->category?->slug;
        $excluded = (array) config('shop.online_order_discount.excluded_category_slugs', []);

        return ! in_array($categorySlug, $excluded, true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(ProductView::class);
    }

    public function allAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)
            ->with(['attribute', 'option'])
            ->whereHas('attribute', fn ($q) => $q
                ->where('in_product', true)
                ->whereNotIn('name', self::SUPPLIER_TECHNICAL_ATTRIBUTES))
            ->orderBy('attribute_id');
    }

    public function briefAttributes(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)
            ->with(['attribute', 'option'])
            ->whereHas('attribute', fn ($q) => $q
                ->where('in_brief', true)
                ->whereNotIn('name', self::SUPPLIER_TECHNICAL_ATTRIBUTES))
            ->orderBy('attribute_id');
    }

    // Первое фото (сырое имя/путь из БД)
    public function getMainImageAttribute(): ?string
    {
        return $this->imagePaths()[0] ?? null;
    }

    /**
     * Return a predictable gallery array even for legacy rows where `images`
     * contains a JSON scalar or a plain path instead of a JSON array.
     */
    public function imagePaths(): array
    {
        $images = $this->images;

        if (is_string($images)) {
            $decoded = json_decode($images, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $images = $decoded;
            }
        }

        if (is_string($images)) {
            $images = [$images];
        }

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(
            $images,
            fn ($path) => is_string($path) && trim($path) !== ''
        ));
    }

    // URL первого фото через proxy (или placeholder)
    public function getImageUrlAttribute(): string
    {
        return $this->imageUrl(0);
    }

    // URL фото по индексу через proxy (или placeholder)
    public function imageUrl(int $index = 0): string
    {
        $placeholder = asset('img/products/product-placeholder.jpg');

        $images = $this->imagePaths();

        if (empty($images)) {
            return $placeholder;
        }

        $path = $images[$index] ?? $images[0] ?? null;

        if (! $path) {
            return $placeholder;
        }

        // product/000/000065/file.jpg — уже полный путь
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
            return '/'.ltrim($path, '/');
        }

        // Загружено вручную через FileUpload → public storage
        if (str_starts_with($path, 'products/')) {
            return asset('storage/'.$path);
        }

        if (str_starts_with($path, 'product/')) {
            return '/proxy-image/'.$path;
        }

        // 000/000065/file.jpg — путь с двумя слешами
        if (substr_count($path, '/') >= 2) {
            return '/proxy-image/product/'.$path;
        }

        // Просто имя файла — сначала пробуем SKU (PS-010.397 → 0010/010397)
        $sku = $this->sku ?? '';
        $skuParts = explode('.', $sku);
        $firstRaw = explode('-', $skuParts[0] ?? '')[1] ?? null;
        $secondRaw = $skuParts[1] ?? null;

        if ($firstRaw !== null && $secondRaw !== null && is_numeric($firstRaw) && is_numeric($secondRaw)) {
            $n1 = (int) $firstRaw;
            $dir1 = sprintf('00%d', $n1);
            $dir2 = sprintf('%s%03d', str_pad($n1, 3, '0', STR_PAD_LEFT), (int) $secondRaw);

            return '/proxy-image/product/'.$dir1.'/'.$dir2.'/'.$path;
        }

        // Нестандартный SKU — строим путь по ID (sprintf для совместимости с форматом)
        $id = $this->id;
        if ($id) {
            $n1 = (int) floor($id / 1000);
            $dir1 = sprintf('00%d', $n1);
            $dir2 = str_pad($id, 6, '0', STR_PAD_LEFT);

            return '/proxy-image/product/'.$dir1.'/'.$dir2.'/'.$path;
        }

        return $placeholder;
    }

    // Процент скидки
    public function getDiscountPercentAttribute(): ?int
    {
        if ($this->isPublicSale() && $this->price_old && $this->price_old > $this->price) {
            return round((1 - $this->price / $this->price_old) * 100);
        }

        return null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeCatalogDefaultOrder(
        $query,
        ?int $priorityBrandId = null,
        bool $prioritizeStock = false
    ) {
        if ($priorityBrandId) {
            $query->orderByRaw(
                'CASE WHEN brand_id = ? THEN 0 ELSE 1 END',
                [$priorityBrandId]
            );
        }

        if ($prioritizeStock) {
            $query->orderByDesc('in_stock');
        }

        return $query
            ->orderByDesc('is_featured')
            ->orderByPopularity();
    }

    public function scopePrioritizeConfirmedStock($query)
    {
        $productId = $query->getModel()->qualifyColumn('id');
        $requiresConfirmation = $query->getModel()->qualifyColumn('requires_price_confirmation');
        $availability = $query->getModel()->qualifyColumn('availability_status');
        $manualStock = $query->getModel()->qualifyColumn('manual_stock_management');
        $inStock = $query->getModel()->qualifyColumn('in_stock');
        $supplierFreshAfter = now()->subHours(SupplierProduct::STALE_AFTER_HOURS);
        $integrationFreshAfter = now()->subMinutes(15);
        $oneCFreshAfter = now()->subMinutes(IntegrationSource::ONE_C_STOCK_MIN_VALIDITY_MINUTES);

        $query->orderByRaw(
            "CASE WHEN {$requiresConfirmation} = 0
                AND ({$availability} IS NULL OR {$availability} != ?)
                AND (
                    ({$manualStock} = 1 AND {$availability} = 'in_stock' AND {$inStock} = 1)
                    OR ({$manualStock} = 0 AND (
                    EXISTS (
                        SELECT 1 FROM supplier_products AS catalog_supplier_stock
                        WHERE catalog_supplier_stock.product_id = {$productId}
                          AND catalog_supplier_stock.stock_quantity > 0
                          AND COALESCE(
                              catalog_supplier_stock.last_stock_synced_at,
                              catalog_supplier_stock.last_synced_at,
                              catalog_supplier_stock.updated_at
                          ) >= ?
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM integration_products AS catalog_integration_stock
                        INNER JOIN integration_sources AS catalog_stock_source
                            ON catalog_stock_source.id = catalog_integration_stock.integration_source_id
                        WHERE catalog_integration_stock.product_id = {$productId}
                          AND catalog_integration_stock.match_status = 'matched'
                          AND catalog_integration_stock.stock_quantity > 0
                          AND catalog_stock_source.is_active = 1
                          AND (
                              (
                                  catalog_stock_source.code != 'onec'
                                  AND catalog_stock_source.driver != 'commerceml'
                                  AND catalog_integration_stock.stock_confirmed_at >= ?
                              )
                              OR (
                                  (catalog_stock_source.code = 'onec' OR catalog_stock_source.driver = 'commerceml')
                                  AND (
                                      catalog_integration_stock.stock_confirmed_at >= ?
                                      OR EXISTS (
                                          SELECT 1 FROM integration_exchange_runs AS catalog_stock_run
                                          WHERE catalog_stock_run.integration_source_id = catalog_stock_source.id
                                            AND catalog_stock_run.status = 'success'
                                            AND catalog_stock_run.operation = 'catalog'
                                            AND catalog_stock_run.finished_at >= ?
                                      )
                                  )
                              )
                          )
                    )
                ))) THEN 0 ELSE 1 END",
            [
                self::AVAILABILITY_OUT_OF_STOCK,
                $supplierFreshAfter,
                $integrationFreshAfter,
                $oneCFreshAfter,
                $oneCFreshAfter,
            ]
        );

        // Products with an approved retail price stay ahead of consultation
        // cards even when neither item currently has a confirmed stock count.
        // The mass safety hold sets this flag for every unlinked price, so an
        // old popular card can no longer outrank a verified catalogue offer.
        return $query->orderByRaw(
            "CASE WHEN {$requiresConfirmation} = 0 AND ".$query->getModel()->qualifyColumn('price').' > 0 THEN 0 ELSE 1 END'
        );
    }

    public function scopeOrderByPopularity($query)
    {
        return $query
            ->orderByDesc('popularity_score')
            ->orderByDesc('rating')
            ->orderByDesc('reviews_count')
            ->orderByDesc('views_count')
            ->orderByDesc('id');
    }

    public function scopeHomepageRatingOrder($query, bool $prioritizeFeatured = false)
    {
        $query->prioritizeConfirmedStock();

        if ($prioritizeFeatured) {
            $query->orderByDesc('is_featured');
        }

        return $query
            ->orderByDesc('rating')
            ->orderByDesc('reviews_count')
            ->orderByDesc('popularity_score')
            ->orderByDesc('views_count')
            ->orderByDesc('id');
    }

    public function scopePelletBoilerCatalogOrder($query)
    {
        return $query
            ->orderByDesc('in_stock')
            ->orderByRaw('CASE WHEN price > 0 THEN 0 ELSE 1 END')
            ->orderBy('price');
    }

    public function scopePrioritizeAttributeOptions(
        $query,
        array $optionIds,
        string $valueTable = 'product_attribute_values'
    ) {
        $optionIds = collect($optionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($optionIds === []) {
            return $query;
        }

        if (! preg_match('/^[a-z0-9_]+$/i', $valueTable)) {
            throw new \InvalidArgumentException('Invalid attribute value table name.');
        }

        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $productIdColumn = $query->getModel()->qualifyColumn('id');

        return $query->orderByRaw(
            "CASE WHEN EXISTS (
                SELECT 1
                FROM {$valueTable} AS priority_attribute_values
                WHERE priority_attribute_values.product_id = {$productIdColumn}
                  AND priority_attribute_values.option_id IN ({$placeholders})
            ) THEN 0 ELSE 1 END",
            $optionIds
        );
    }

    public function scopePrioritizeNamePatterns($query, array $patterns)
    {
        $patterns = collect($patterns)
            ->map(fn ($pattern) => trim((string) $pattern))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($patterns === []) {
            return $query;
        }

        $conditions = implode(' OR ', array_fill(0, count($patterns), $this->qualifyColumn('name').' LIKE ?'));

        return $query->orderByRaw(
            "CASE WHEN {$conditions} THEN 0 ELSE 1 END",
            $patterns
        );
    }

    public function scopeDeprioritizeCategories($query, array $categoryIds)
    {
        $categoryIds = collect($categoryIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($categoryIds === []) {
            return $query;
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        return $query->orderByRaw(
            'CASE WHEN '.$this->qualifyColumn('category_id')." IN ({$placeholders}) THEN 1 ELSE 0 END",
            $categoryIds
        );
    }

    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }

    public function scopeOrderable($query)
    {
        return $query
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('price', '>', 0)
            ->where(function ($query) {
                $query->where('availability_status', self::AVAILABILITY_CHECK)
                    ->orWhere(function ($query) {
                        $query->where('in_stock', true)
                            ->where(function ($statusQuery) {
                                $statusQuery->whereNull('availability_status')
                                    ->orWhere('availability_status', '!=', self::AVAILABILITY_OUT_OF_STOCK);
                            });
                    });
            });
    }

    public function supplierMappings(): HasMany
    {
        return $this->hasMany(SupplierProductMapping::class);
    }

    public function analogProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_analogs', 'product_id', 'analog_product_id')
            ->withPivot(['source', 'match_method', 'source_model'])
            ->withTimestamps();
    }

    public function analogOfProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_analogs', 'analog_product_id', 'product_id')
            ->withPivot(['source', 'match_method', 'source_model'])
            ->withTimestamps();
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function integrationProducts(): HasMany
    {
        return $this->hasMany(IntegrationProduct::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }

    public function supplierMailThreads(): HasMany
    {
        return $this->hasMany(CrmMailThread::class);
    }

    public function latestSupplierMailThread(): HasOne
    {
        return $this->hasOne(CrmMailThread::class)->latestOfMany();
    }

    public function marketPriceObservations(): HasMany
    {
        return $this->hasMany(MarketPriceObservation::class);
    }

    public function marketResearchReports(): HasMany
    {
        return $this->hasMany(MarketResearchReport::class);
    }

    public function latestMarketResearchReport(): HasOne
    {
        return $this->hasOne(MarketResearchReport::class)->latestOfMany('researched_at');
    }
}
