<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VamExpressShipment extends Model
{
    protected $guarded = ['id', 'order_id'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'delivery_cost' => 'decimal:2', 'remote_created_at' => 'date',
            'accepted_at' => 'date', 'dispatch_at' => 'date', 'synced_at' => 'datetime', 'checked_at' => 'datetime', 'profile_checked_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function portalUrl(): string
    {
        return 'https://vamexpress.by/UserUI/TicketEdit.aspx?id='.rawurlencode($this->remote_id);
    }
}
