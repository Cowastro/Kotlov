<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;

class B2bClient extends Model
{
    public const STATUSES = [
        'pending' => 'Новая заявка',
        'approved' => 'Активный клиент',
        'paused' => 'Приостановлен',
        'rejected' => 'Отклонён',
    ];

    public const PARTNER_TYPES = [
        'wholesale' => 'Оптовый покупатель',
        'installer' => 'Монтажная организация',
    ];

    protected $fillable = [
        'user_id', 'counterparty_id', 'status', 'partner_type', 'company_name', 'unp',
        'contact_name', 'contact_email', 'contact_phone',
        'responsible_user_id', 'reviewed_by', 'reviewed_at',
        'payment_terms', 'delivery_terms',
        'allowed_integration_source_ids',
        'application_comment', 'manager_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'allowed_integration_source_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (B2bClient $client): void {
            if (! $client->isDirty('status') || $client->status === 'pending') {
                return;
            }

            $client->reviewed_at ??= now();
            $client->reviewed_by ??= Auth::id();
        });

        static::saved(function (B2bClient $client): void {
            $client->user()->update([
                'client_type' => $client->partner_type,
                'b2b_approved' => $client->status === 'approved',
                'company_name' => $client->company_name,
                'company_inn' => $client->unp,
                'b2b_comment' => $client->application_comment,
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'counterparty_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            User::class,
            'id',
            'user_id',
            'user_id',
            'id',
        );
    }

    public function scopeWithB2bOrderMetrics(Builder $query): Builder
    {
        return $query
            ->withCount([
                'orders as b2b_orders_count' => fn (Builder $orders): Builder => $orders->whereHas(
                    'items',
                    fn (Builder $items): Builder => $items->where('pricing_type', 'b2b'),
                ),
            ])
            ->withSum([
                'orders as b2b_orders_total' => fn (Builder $orders): Builder => $orders->whereHas(
                    'items',
                    fn (Builder $items): Builder => $items->where('pricing_type', 'b2b'),
                ),
            ], 'total');
    }
}
