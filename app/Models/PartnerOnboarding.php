<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Personal questionnaire link sent to a partner from the existing base. */
class PartnerOnboarding extends Model
{
    public const STATUSES = [
        'invited' => 'Приглашён',
        'opened' => 'Открыл анкету',
        'submitted' => 'Анкета заполнена',
        'approved' => 'Подключён',
        'rejected' => 'Отклонён',
    ];

    protected $fillable = [
        'token', 'email', 'company_name', 'unp', 'crm_counterparty_id', 'status', 'data',
        'b2b_client_id', 'created_by', 'decided_by', 'decision_note',
        'invited_at', 'opened_at', 'submitted_at', 'decided_at',
    ];

    protected $casts = [
        'data' => 'array',
        'invited_at' => 'datetime',
        'opened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function counterparty(): BelongsTo { return $this->belongsTo(CrmCounterparty::class, 'crm_counterparty_id'); }
    public function client(): BelongsTo { return $this->belongsTo(B2bClient::class, 'b2b_client_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isEditableByPartner(): bool
    {
        return in_array($this->status, ['invited', 'opened', 'submitted'], true);
    }

    public function url(): string
    {
        return route('partner-onboarding.show', $this->token);
    }
}
