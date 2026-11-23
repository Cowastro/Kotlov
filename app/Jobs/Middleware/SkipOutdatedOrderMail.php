<?php

namespace App\Jobs\Middleware;

use App\Models\Order;
use Closure;

class SkipOutdatedOrderMail
{
    public function __construct(private int $orderId, private array $statuses) {}

    public function handle($job, Closure $next): mixed
    {
        if (Order::whereKey($this->orderId)->whereIn('status', $this->statuses)->exists()) {
            return $next($job);
        }

        return null;
    }
}
