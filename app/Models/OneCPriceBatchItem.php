<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneCPriceBatchItem extends Model
{
    protected $table = 'onec_price_batch_items';

    protected $fillable = [
        'onec_price_batch_id', 'integration_product_id', 'item_key', 'characteristic_key', 'code', 'name',
        'retail', 'wholesale', 'retail_site', 'purchase', 'verdict', 'note',
    ];

    protected $casts = [
        'retail' => 'decimal:2',
        'wholesale' => 'decimal:2',
        'retail_site' => 'decimal:2',
        'purchase' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OneCPriceBatch::class, 'onec_price_batch_id');
    }
}
