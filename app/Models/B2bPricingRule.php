<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bPricingRule extends Model
{
    public const SCOPE_SUPPLIER = 'supplier';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_BRAND = 'brand';

    public const SCOPE_PRODUCT = 'product';

    public const TYPE_TARGET_MARGIN = 'target_margin';

    public const TYPE_MARKUP = 'markup';

    public const TYPE_FIXED_PRICE = 'fixed_price';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'supplier_id', 'name', 'scope_type', 'scope_id', 'calculation_type', 'value',
        'minimum_profit_byn', 'minimum_margin_percent', 'rounding_step_byn', 'minimum_retail_discount_percent',
        'priority', 'status', 'valid_from', 'valid_until', 'notes',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'minimum_profit_byn' => 'decimal:2',
        'minimum_margin_percent' => 'decimal:4',
        'rounding_step_byn' => 'decimal:2',
        'minimum_retail_discount_percent' => 'decimal:4',
        'priority' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isEffective(bool $includeDraft = false): bool
    {
        if (! in_array($this->status, $includeDraft ? [self::STATUS_ACTIVE, self::STATUS_DRAFT] : [self::STATUS_ACTIVE], true)) {
            return false;
        }

        return (! $this->valid_from || $this->valid_from->lte(now()))
            && (! $this->valid_until || $this->valid_until->gte(now()));
    }
}
