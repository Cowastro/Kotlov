<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bOrderEvent extends Model
{
    protected $fillable = ['b2b_order_id', 'actor_id', 'event', 'from_status', 'to_status', 'comment', 'metadata'];
    protected $casts = ['metadata' => 'array'];
    public function order(): BelongsTo { return $this->belongsTo(B2bOrder::class, 'b2b_order_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
