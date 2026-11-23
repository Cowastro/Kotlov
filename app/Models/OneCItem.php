<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An item or a group of the 1C «Номенклатура», as the agent read it, with the site card linked to it. */
class OneCItem extends Model
{
    protected $table = 'onec_items';

    protected $fillable = ['ref_key', 'code', 'description', 'article', 'parent_key', 'is_folder', 'deletion_mark', 'kind_key', 'unit_key',
        'path', 'product_id', 'created_from_site', 'same_name_count', 'synced_at', 'stock_on_hand', 'stock_reserved', 'stock_synced_at',
        'cost_net', 'cost_gross', 'cost_date', 'cost_doc', 'cost_kind'];

    protected $casts = [
        'cost_net' => 'float',
        'cost_gross' => 'float',
        'cost_date' => 'date',
        'site_link_count' => 'integer',
        'characteristic_count' => 'integer',
        'link_conflict' => 'boolean',
        'is_folder' => 'boolean',
        'deletion_mark' => 'boolean',
        'created_from_site' => 'boolean',
        'synced_at' => 'datetime',
        'stock_on_hand' => 'float',
        'stock_reserved' => 'float',
        'stock_synced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_key', 'ref_key');
    }
}
