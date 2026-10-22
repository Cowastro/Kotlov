<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebpayPaymentAttempt extends Model
{
    protected $fillable = [
        'order_id',
        'merchant_order_num',
        'amount',
        'currency',
        'environment',
        'status',
        'seed',
        'transaction_id',
        'webpay_order_id',
        'payment_method',
        'payment_type',
        'rrn',
        'notification_payload',
        'notified_at',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'notification_payload' => 'array',
        'notified_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
