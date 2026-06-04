<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallRequest extends Model
{
    protected $fillable = [
        // Существующие поля
        'client_id',
        'installer_id',
        'product_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'description',
        'region',
        'city',
        'preferred_date',
        'status',
        'price_agreed',

        // Новые поля (Этап 8)
        'specialization',
        'address',
        'budget',
        'source',
        'notes',
        'installer_profile_id',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'price_agreed'   => 'decimal:2',
        'budget'         => 'decimal:2',
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

    public function installerProfile(): BelongsTo
    {
        return $this->belongsTo(InstallerProfile::class);
    }
}
