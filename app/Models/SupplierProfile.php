<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'company_logo',
        'legal_address', 'inn', 'description',
        'website', 'phone',
        'is_verified', 'rating', 'products_count',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
