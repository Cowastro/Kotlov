<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactRequest extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'phone',
        'email',
        'message',
        'status',
        'responsible_user_id',
        'next_follow_up_at',
        'admin_notes',
        'product_id',
        'requested_quantity',
        'client_response',
        'product_name',
        'product_url',
        'city',
        'source',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
        'requested_quantity' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function telephonyCalls(): HasMany
    {
        return $this->hasMany(TelephonyCall::class)->latest('started_at');
    }
}
