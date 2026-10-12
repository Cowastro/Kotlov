<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelephonyCall extends Model
{
    public const STATUSES = [
        'ringing' => 'Звонит',
        'answered' => 'Отвечен',
        'missed' => 'Пропущен',
        'cancelled' => 'Отменён клиентом',
        'busy' => 'Занято',
        'denied' => 'Отклонён',
        'failed' => 'Ошибка',
    ];

    public const HANDLING_STATUSES = [
        'unprocessed' => 'Нужно разобрать',
        'callback_required' => 'Нужно перезвонить',
        'in_progress' => 'В работе',
        'handled' => 'Обработан',
        'irrelevant' => 'Неактуален',
    ];

    protected $fillable = [
        'provider', 'external_uuid', 'company_id', 'direction', 'dial_type',
        'customer_phone', 'employee_phone', 'line_number', 'assigned_user_id',
        'matched_user_id', 'crm_counterparty_id', 'crm_contact_id', 'order_id', 'contact_request_id', 'status',
        'handling_status', 'started_at', 'answered_at', 'finished_at', 'duration',
        'conversation_duration', 'record_name', 'recording_url', 'failure_reason',
        'callback_status', 'handled_at', 'handled_by', 'manager_note', 'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'finished_at' => 'datetime',
        'handled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    public function crmCounterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'crm_counterparty_id');
    }

    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function handledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TelephonyCallEvent::class);
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereIn('handling_status', ['unprocessed', 'callback_required']);
    }

    public function getContactNameAttribute(): string
    {
        return $this->crmContact?->full_name
            ?? $this->order?->customer_name
            ?? $this->matchedUser?->name
            ?? $this->contactRequest?->name
            ?? 'Неизвестный номер';
    }
}
