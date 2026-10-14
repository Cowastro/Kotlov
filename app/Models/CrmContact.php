<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    protected $fillable = [
        'counterparty_id', 'user_id', 'full_name', 'position', 'external_1c_id',
        'is_primary', 'is_active', 'notes',
    ];

    protected $casts = ['is_primary' => 'boolean', 'is_active' => 'boolean'];

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'counterparty_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactPoints(): HasMany
    {
        return $this->hasMany(CrmContactPoint::class, 'contact_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(TelephonyCall::class, 'crm_contact_id');
    }

    public function mailThreads(): HasMany
    {
        return $this->hasMany(CrmMailThread::class, 'crm_contact_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'crm_contact_id')->latest();
    }

    public function getPhonesListAttribute(): string
    {
        return $this->contactPoints->where('type', 'phone')->pluck('value')->join(', ');
    }

    public function getEmailsListAttribute(): string
    {
        return $this->contactPoints->where('type', 'email')->pluck('value')->join(', ');
    }
}
