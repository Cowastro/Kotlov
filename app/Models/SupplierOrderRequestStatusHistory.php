<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderRequestStatusHistory extends Model
{
    protected $fillable = [
        'supplier_order_request_id', 'user_id', 'actor_name', 'actor_scope',
        'status_from', 'status_to', 'note',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderRequest::class, 'supplier_order_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
