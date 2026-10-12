<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelephonyConnection extends Model
{
    protected $fillable = [
        'provider', 'company_id', 'webhook_secret', 'primary_api_key', 'access_token', 'refresh_token',
        'access_token_expires_at', 'refresh_token_expires_at', 'is_enabled',
        'webhook_last_received_at', 'api_last_success_at', 'history_synced_until', 'last_error',
    ];

    protected $hidden = ['webhook_secret', 'primary_api_key', 'access_token', 'refresh_token'];

    protected $casts = [
        'webhook_secret' => 'encrypted',
        'primary_api_key' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'is_enabled' => 'boolean',
        'webhook_last_received_at' => 'datetime',
        'api_last_success_at' => 'datetime',
        'history_synced_until' => 'datetime',
    ];
}
