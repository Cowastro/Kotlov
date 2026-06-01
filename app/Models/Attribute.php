<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $fillable = [
        'category_id', 'group_id', 'sort_order', 'type',
        'name', 'suffix', 'in_filter', 'in_sort',
        'in_product', 'in_brief', 'is_comparable',
    ];

    protected $casts = [
        'in_filter'     => 'boolean',
        'in_sort'       => 'boolean',
        'in_product'    => 'boolean',
        'in_brief'      => 'boolean',
        'is_comparable' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function scopeForProduct($query)
    {
        return $query->where('in_product', true)->orderBy('sort_order');
    }

    public function scopeForFilter($query)
    {
        return $query->where('in_filter', true)->orderBy('sort_order');
    }
}
