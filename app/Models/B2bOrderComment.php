<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bOrderComment extends Model
{
    protected $fillable = ['b2b_order_id', 'author_id', 'visible_to_client', 'body'];
    protected $casts = ['visible_to_client' => 'boolean'];
    public function order(): BelongsTo { return $this->belongsTo(B2bOrder::class, 'b2b_order_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
