<?php

namespace App\Models;

use App\Services\Telephony\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmContactPoint extends Model
{
    protected $fillable = ['contact_id', 'type', 'value', 'normalized_value', 'label', 'is_primary', 'is_active'];

    protected $casts = ['is_primary' => 'boolean', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (CrmContactPoint $point): void {
            $point->value = trim($point->value);
            $point->normalized_value = $point->type === 'phone'
                ? (PhoneNumber::normalize($point->value) ?? '')
                : mb_strtolower($point->value);
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }
}
