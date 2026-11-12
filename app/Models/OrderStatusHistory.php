<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id', 'user_id',
        'status_from', 'status_to', 'comment',
        'deal_stage_from', 'deal_stage_to',
    ];

    public function enteredDealStage(): ?string
    {
        if (isset(Order::DEAL_STAGES[$this->deal_stage_to]) && $this->deal_stage_from !== $this->deal_stage_to) {
            return $this->deal_stage_to;
        }

        // Read stage changes already recorded before structured stage fields were added.
        if (preg_match('/\AКонтроль сделки\. Было: ([^\r\n·]+).*?\r?\nСтало: ([^\r\n·]+)/us', (string) $this->comment, $matches)) {
            $keys = array_flip(Order::DEAL_STAGES);
            $from = $keys[trim($matches[1])] ?? null;
            $to = $keys[trim($matches[2])] ?? null;

            return $from !== null && $to !== null && $from !== $to ? $to : null;
        }

        if ($this->status_from === null && $this->status_to === 'new') {
            return 'received';
        }
        $from = Order::fulfillmentDealStageForStatus($this->status_from);
        $to = Order::fulfillmentDealStageForStatus($this->status_to);

        return $to !== 'received' && $from !== $to ? $to : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
