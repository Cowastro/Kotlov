<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAccountingRequest extends Model
{
    public const STATUSES = [
        'draft' => 'Черновик / ошибка отправки',
        'sent' => 'Передано в бухгалтерию',
        'paid' => 'Оплачено',
        'cancelled' => 'Отменено',
    ];

    protected $fillable = [
        'order_id', 'mail_thread_id', 'requested_by_user_id', 'completed_by_user_id',
        'recipient_email', 'amount', 'due_date', 'status', 'comment', 'last_error',
        'sent_at', 'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function mailThread(): BelongsTo
    {
        return $this->belongsTo(CrmMailThread::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
