<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialDocumentPortalLink extends Model
{
    protected $fillable = [
        'commercial_document_id', 'public_id', 'token_hash', 'token_encrypted', 'created_by',
        'expires_at', 'first_activated_at', 'last_opened_at', 'revoked_at', 'open_count',
    ];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected $casts = [
        'token_encrypted' => 'encrypted',
        'expires_at' => 'datetime',
        'first_activated_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(CommercialDocument::class, 'commercial_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CommercialDocumentPortalSession::class, 'portal_link_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(CommercialDocumentPortalChallenge::class, 'portal_link_id');
    }

    public function isUsable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }
}
