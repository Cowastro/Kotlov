<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bClientMember extends Model
{
    public const ROLES = [
        'owner' => 'Владелец кабинета',
        'buyer' => 'Закупщик',
        'accountant' => 'Бухгалтер',
        'viewer' => 'Наблюдатель',
    ];

    protected $fillable = [
        'b2b_client_id', 'user_id', 'role', 'is_active', 'invited_by', 'joined_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(B2bClient::class, 'b2b_client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
