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
        'cost_net', 'cost_gross', 'cost_date', 'cost_doc', 'cost_kind', 'price_retail', 'price_wholesale', 'prices_synced_at', 'price_retail_at'];

    protected $casts = [
        'cost_net' => 'float',
        'cost_gross' => 'float',
        'cost_date' => 'date',
        'price_retail' => 'float',
        'price_wholesale' => 'float',
        'prices_synced_at' => 'datetime',
        'price_retail_at' => 'date',
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

    public static function grossCostLabel(?self $item): string
    {
        $gross = $item?->cost_gross;
        return 'Себестоимость 1С с НДС за шт.: '.($gross === null ? 'нет данных' : number_format($gross, 2, ',', ' ').' BYN')
            .($item?->cost_from_rub ? ' · пересчёт из RUB по курсу НБРБ на дату поступления' : '');
    }

    public static function fromExchangeKey(?string $key): ?self
    {
        return $key ? self::query()->where('ref_key', strtolower(explode('#', $key, 2)[0]))->first() : null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_key', 'ref_key');
    }
}
