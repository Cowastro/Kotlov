<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallRequest extends Model
{
    protected $fillable = [
        'client_id', 'installer_id', 'product_id',
        'customer_name', 'customer_phone', 'description',
        'region', 'city', 'preferred_date',
        'status', 'price_agreed',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'price_agreed'   => 'decimal:2',
    ];

    public const STATUSES = [
        'new'         => 'Новая',
        'accepted'    => 'Принята',
        'in_progress' => 'В работе',
        'done'        => 'Выполнена',
        'cancelled'   => 'Отменена',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
