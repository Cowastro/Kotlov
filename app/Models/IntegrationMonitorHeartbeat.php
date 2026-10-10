<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationMonitorHeartbeat extends Model
{
    public const ISSUE_SCANNER = 'integration_issue_scanner';

    protected $fillable = [
        'name', 'status', 'started_at', 'finished_at', 'duration_ms', 'summary', 'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'summary' => 'array',
    ];
}
