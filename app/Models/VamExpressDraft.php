<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VamExpressDraft extends Model
{
    protected $guarded = ['id', 'order_id', 'created_by', 'version', 'status'];

    protected $hidden = ['payload', 'quote'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'quote' => 'encrypted:array', 'version' => 'integer',
            'quoted_amount' => 'decimal:2', 'quoted_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
