<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;

class B2bClient extends Model
{
    public const SERVICE_MODES = [
        'online_warehouse' => 'Онлайн-склад',
        'specification' => 'Комплектация по спецификации',
        'engineering' => 'Инженерное сопровождение',
    ];

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
        'user_id', 'counterparty_id', 'status', 'partner_type', 'b2b_price_level_id', 'service_mode', 'company_name', 'unp',
        'contact_name', 'contact_email', 'contact_phone',
        'responsible_user_id', 'reviewed_by', 'reviewed_at',
        'payment_terms', 'delivery_terms', 'minimum_order_amount',
        'payment_deferment_days', 'credit_limit', 'commercial_terms_valid_until',
        'allowed_integration_source_ids', 'allowed_integration_warehouse_ids', 'allowed_supplier_ids',
        'specification_service_enabled',
        'application_comment', 'manager_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'minimum_order_amount' => 'decimal:2',
        'payment_deferment_days' => 'integer',
        'credit_limit' => 'decimal:2',
        'commercial_terms_valid_until' => 'date',
        'allowed_integration_source_ids' => 'array',
        'allowed_integration_warehouse_ids' => 'array',
        'allowed_supplier_ids' => 'array',
        'specification_service_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (B2bClient $client): void {
            $client->members()->updateOrCreate(
                ['user_id' => $client->user_id],
                ['role' => 'owner', 'is_active' => true, 'joined_at' => now()],
            );
        });

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

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(B2bPriceLevel::class, 'b2b_price_level_id');
    }

    public function serviceModeLabel(): string
    {
        return self::SERVICE_MODES[$this->service_mode] ?? 'Онлайн-склад';
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
            B2bClientMember::class,
            'b2b_client_id',
            'user_id',
            'id',
            'user_id',
        );
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(B2bSpecification::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(B2bClientMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(B2bClientInvitation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(B2bClientAuditLog::class);
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
