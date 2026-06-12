<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    public const AVAILABILITY_IN_STOCK = 'in_stock';
    public const AVAILABILITY_CHECK = 'check';
    public const AVAILABILITY_OUT_OF_STOCK = 'out_of_stock';

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
        'price', 'price_old', 'currency',
        'content', 'short_description',
        'images', 'specs', 'service_info', 'documents', 'promo_flags', 'video_url',
        'weight', 'unit', 'warranty',
        'is_active', 'is_archived', 'in_stock', 'availability_status', 'stock_qty',
        'is_featured', 'is_new', 'is_sale', 'sort_order',
        'meta_title', 'meta_keywords', 'meta_description',
        'rating', 'reviews_count', 'views_count',
    ];

    protected $casts = [
        'images'      => 'array',
        'specs'       => 'array',
        'service_info' => 'array',
        'documents'   => 'array',
        'promo_flags' => 'array',
        'is_active'   => 'boolean',
        'is_archived' => 'boolean',
        'in_stock'    => 'boolean',
        'is_featured' => 'boolean',
        'is_new'      => 'boolean',
        'is_sale'     => 'boolean',
        'price'       => 'decimal:2',
        'price_old'   => 'decimal:2',
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
        $status = $this->availability_status ?: null;

        // Explicitly marked "уточняйте наличие"
        if ($status === self::AVAILABILITY_CHECK) {
            return self::AVAILABILITY_CHECK;
        }

        // Explicitly marked "нет в наличии" — only case that shows "Нет в наличии"
        if ($status === self::AVAILABILITY_OUT_OF_STOCK) {
            return self::AVAILABILITY_OUT_OF_STOCK;
        }

        // In_stock flag is the source of truth for "В наличии"
        if ($this->in_stock) {
            return self::AVAILABILITY_IN_STOCK;
        }

        // Not in stock but not explicitly marked — show "Уточняйте наличие"
        return self::AVAILABILITY_CHECK;
    }

    public function availabilityLabel(): string
    {
        return self::availabilityStatusOptions()[$this->effectiveAvailabilityStatus()] ?? 'Уточняйте наличие';
    }

    public function canBeOrdered(): bool
    {
        return ! $this->is_archived
            && (float) $this->price > 0
            && in_array($this->effectiveAvailabilityStatus(), [
                self::AVAILABILITY_IN_STOCK,
                self::AVAILABILITY_CHECK,
            ], true);
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
            ->whereHas('attribute', fn($q) => $q
                ->where('in_product', true)
                ->whereNotIn('name', self::SUPPLIER_TECHNICAL_ATTRIBUTES))
            ->orderBy('attribute_id');
    }

    public function briefAttributes(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)
            ->with(['attribute', 'option'])
            ->whereHas('attribute', fn($q) => $q
                ->where('in_brief', true)
                ->whereNotIn('name', self::SUPPLIER_TECHNICAL_ATTRIBUTES))
            ->orderBy('attribute_id');
    }

    // Первое фото (сырое имя/путь из БД)
    public function getMainImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
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

        $images = $this->images;

        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (!is_array($images) || empty($images)) {
            return $placeholder;
        }

        $path = $images[$index] ?? $images[0] ?? null;

        if (!$path) {
            return $placeholder;
        }

        // product/000/000065/file.jpg — уже полный путь
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
            return '/' . ltrim($path, '/');
        }

        if (str_starts_with($path, 'product/')) {
            return '/proxy-image/' . $path;
        }

        // 000/000065/file.jpg — путь с двумя слешами
        if (substr_count($path, '/') >= 2) {
            return '/proxy-image/product/' . $path;
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
            return '/proxy-image/product/' . $dir1 . '/' . $dir2 . '/' . $path;
        }

        // Нестандартный SKU — строим путь по ID (sprintf для совместимости с форматом)
        $id = $this->id;
        if ($id) {
            $n1 = (int) floor($id / 1000);
            $dir1 = sprintf('00%d', $n1);
            $dir2 = str_pad($id, 6, '0', STR_PAD_LEFT);
            return '/proxy-image/product/' . $dir1 . '/' . $dir2 . '/' . $path;
        }

        return $placeholder;
    }

    // Процент скидки
    public function getDiscountPercentAttribute(): ?int
    {
        if ($this->price_old && $this->price_old > $this->price) {
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
                        $query->where('availability_status', self::AVAILABILITY_IN_STOCK)
                            ->where('in_stock', true);
                    });
            });
    }

    public function supplierMappings(): HasMany
    {
        return $this->hasMany(SupplierProductMapping::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }
}
