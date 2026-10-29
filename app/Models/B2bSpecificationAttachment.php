<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
