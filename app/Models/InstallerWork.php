<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallerWork extends Model
{
    protected $fillable = [
        'installer_profile_id',
        'title',
        'description',
        'work_type',
        'city',
        'region',
        'equipment_type',
        'brand',
        'photos',
        'completed_at',
        'is_published',
    ];

    protected $casts = [
        'photos'       => 'array',
        'completed_at' => 'date',
        'is_published' => 'boolean',
    ];

    public function installerProfile(): BelongsTo
    {
        return $this->belongsTo(InstallerProfile::class);
    }
}
