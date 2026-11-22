<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An item or a group of the 1C «Номенклатура», as the agent read it, with the site card linked to it. */
class OneCItem extends Model
{
    protected $table = 'onec_items';

    protected $fillable = ['ref_key', 'code', 'description', 'article', 'parent_key', 'is_folder', 'deletion_mark', 'kind_key', 'unit_key',
        'path', 'product_id', 'created_from_site', 'same_name_count', 'synced_at'];

    protected $casts = [
        'is_folder' => 'boolean',
        'deletion_mark' => 'boolean',
        'created_from_site' => 'boolean',
        'synced_at' => 'datetime',
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
