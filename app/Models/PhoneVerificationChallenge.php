<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerificationChallenge extends Model
{
    protected $fillable = [
        'token',
        'phone',
        'code_hash',
        'sms_id',
        'attempts',
        'expires_at',
        'verified_at',
        'request_ip_hash',
    ];

    protected $hidden = [
        'code_hash',
        'request_ip_hash',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
