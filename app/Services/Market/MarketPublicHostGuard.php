<?php

namespace App\Services\Market;

class MarketPublicHostGuard
{
    public function allows(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $addresses = gethostbynamel($host);
        if (! is_array($addresses) || $addresses === []) {
            return false;
        }

        return collect($addresses)->every(fn (string $address): bool => $this->isPublicIp($address));
    }

    private function isPublicIp(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
