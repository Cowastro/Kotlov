<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialDocumentPortalChallenge extends Model
{
    protected $fillable = ['portal_link_id', 'public_id', 'code_hash', 'attempts', 'expires_at', 'consumed_at'];

    protected $hidden = ['code_hash'];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function link(): BelongsTo
    {
        return $this->belongsTo(CommercialDocumentPortalLink::class, 'portal_link_id');
    }
}
