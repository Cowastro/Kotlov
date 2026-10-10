<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePlanStatusHistory extends Model
{
    protected $fillable = [
        'purchase_plan_id', 'user_id', 'status_from', 'status_to', 'comment',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PurchasePlan::class, 'purchase_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
