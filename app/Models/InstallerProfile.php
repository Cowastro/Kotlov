<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallerProfile extends Model
{
    protected $fillable = [
        // Пользователь
        'user_id',

        // Контакты
        'contact_name',
        'phone',
        'additional_phone',
        'email',
        'website',
        'telegram',
        'viber',
        'whatsapp',

        // Компания
        'company_name',
        'legal_name',
        'unp',
        'address',

        // Основная информация
        'photo',
        'bio',
        'experience_years',
        'price_from',

        // География
        'city',
        'region',
        'work_regions',
        'work_cities',
        'work_radius_km',
        'nationwide',

        // Публичный профиль
        'slug',
        'short_description',
        'logo',
        'gallery',
        'certificate_photo',
        'certificate_files',
        'is_published',
        'status',

        // Верификация и рейтинг
        'is_verified',
        'rating',
        'reviews_count',
        'orders_count',

        // Специализации
        'specializations',
    ];

    protected $casts = [
        // JSON-массивы
        'work_regions'     => 'array',
        'work_cities'      => 'array',
        'specializations'  => 'array',
        'gallery'          => 'array',
        'certificate_files'=> 'array',

        // Boolean
        'is_verified'  => 'boolean',
        'is_published' => 'boolean',
        'nationwide'   => 'boolean',

        // Числовые
        'experience_years' => 'integer',
        'work_radius_km'   => 'integer',
        'reviews_count'    => 'integer',
        'orders_count'     => 'integer',
        'price_from'       => 'decimal:2',
        'rating'           => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function works(): HasMany
    {
        return $this->hasMany(InstallerWork::class);
    }

    public function installRequests(): HasMany
    {
        return $this->hasMany(InstallRequest::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
