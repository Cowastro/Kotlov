<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactRequest extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'message',
        'status',
        'responsible_user_id',
        'next_follow_up_at',
        'admin_notes',
        'product_id',
        'product_name',
        'product_url',
        'city',
        'source',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
    ];

    public const OPEN_STATUSES = ['new', 'read', 'in_progress', 'waiting'];

    public static array $statuses = [
        'new' => 'Новая',
        'read' => 'Просмотрена',
        'in_progress' => 'В работе',
        'waiting' => 'Ждём клиента',
        'done' => 'Обработана',
        'cancelled' => 'Неактуальна',
    ];

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function telephonyCalls(): HasMany
    {
        return $this->hasMany(TelephonyCall::class)->latest('started_at');
    }
}
