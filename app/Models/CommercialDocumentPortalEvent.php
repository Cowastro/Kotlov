<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialDocumentPortalEvent extends Model
{
    protected $fillable = [
        'commercial_document_id', 'portal_link_id', 'portal_session_id', 'type',
        'ip_hash', 'user_agent', 'metadata', 'occurred_at',
    ];

    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];
}
