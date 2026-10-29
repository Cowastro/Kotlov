<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bSpecificationAttachment extends Model
{
    protected $fillable = [
        'b2b_specification_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function specification(): BelongsTo
    {
        return $this->belongsTo(B2bSpecification::class, 'b2b_specification_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(B2bSpecificationItem::class, 'b2b_specification_attachment_id');
    }
}
