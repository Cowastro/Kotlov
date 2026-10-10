<?php

namespace App\Services\Market;

use App\Models\MarketPriceCollectionRun;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class MarketCollectionHttpClient
{
    public function __construct(
        private readonly MarketPriceCollectionManager $manager,
        private readonly MarketPublicHostGuard $hostGuard,
    ) {}

    public function get(MarketPriceCollectionRun $run, string $url, bool $robotsTxt = false): ?Response
    {
        if (! $this->manager->reserveRequest($run, $url, robotsTxt: $robotsTxt)) {
            return null;
        }

        if (! $this->hostGuard->allows($url)) {
            $this->manager->fail($run, 'unsafe_resolved_host', 'Домен не разрешился в безопасный публичный IP-адрес.');

            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withUserAgent('KOTLOVMarketBot/1.0 (+https://kotlov.by)')
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url);
        } catch (Throwable $exception) {
            $this->manager->fail($run, 'http_failed', $exception->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $code = $response->redirect() ? 'redirect_blocked' : 'http_status';
            $message = $response->redirect()
                ? 'Источник вернул перенаправление; автоматический переход заблокирован для защиты адреса.'
                : 'Источник вернул HTTP '.$response->status().'.';
            $this->manager->fail($run, $code, $message);

            return null;
        }

        if (strlen($response->body()) > 5 * 1024 * 1024) {
            $this->manager->fail($run, 'response_too_large', 'Ответ источника превышает безопасный лимит 5 МБ.');

            return null;
        }

        return $response;
    }
}
