<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallRequestLead extends Model
{
    protected $fillable = [
        'install_request_id',
        'installer_profile_id',
        'notified_at',
        'opened_at',
        'taken_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'opened_at' => 'datetime',
        'taken_at' => 'datetime',
    ];

    public function installRequest(): BelongsTo
    {
        return $this->belongsTo(InstallRequest::class);
    }

    public function installerProfile(): BelongsTo
    {
        return $this->belongsTo(InstallerProfile::class);
    }
}
