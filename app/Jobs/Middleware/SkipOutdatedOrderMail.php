<?php

namespace App\Jobs\Middleware;

use App\Models\Order;
use Closure;

class SkipOutdatedOrderMail
{
    public function __construct(private int $orderId, private array $statuses, private ?string $carrierStage = null, private bool $checkCarrier = false, private ?string $carrierReference = null) {}

    public function handle($job, Closure $next): mixed
    {
        $query = Order::whereKey($this->orderId)->whereIn('status', $this->statuses);
        if ($this->checkCarrier) {
            $query->where('carrier_stage', $this->carrierStage);
            $query->where('carrier_reference', $this->carrierReference);
        }
        if ($query->exists()) {
            return $next($job);
        }

        return null;
    }
}
