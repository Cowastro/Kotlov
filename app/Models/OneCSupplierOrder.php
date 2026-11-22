<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The «Заказ поставщику» made in 1C for the lines of one customer order bought from one supplier. */
class OneCSupplierOrder extends Model
{
    public const QUEUED = 'queued';

    public const CREATED = 'created';

    public const FAILED = 'failed';

    protected $table = 'onec_supplier_orders';

    protected $fillable = ['order_id', 'supplier_id', 'profile_id', 'status', 'counterparty_key', 'total', 'onec_order_guid', 'onec_order_number',
        'onec_doc_status', 'onec_posted', 'onec_deleted', 'onec_checked_at'];

    protected $casts = [
        'total' => 'decimal:2',
        'onec_posted' => 'boolean',
        'onec_deleted' => 'boolean',
        'onec_checked_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
