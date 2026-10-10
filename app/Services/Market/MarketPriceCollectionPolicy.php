<?php

namespace App\Services\Market;

use App\Models\MarketPriceCollectionRun;
use App\Models\MarketPriceSource;
use Carbon\CarbonInterface;

class MarketPriceCollectionPolicy
{
    /** @return array{allowed:bool,code:?string,message:?string} */
    public function canStart(MarketPriceSource $source, string $trigger, CarbonInterface $now): array
    {
        if (! $source->is_active) {
            return $this->denied('source_inactive', 'Источник выключен и не может участвовать в сборе.');
        }

        if (! array_key_exists($trigger, MarketPriceCollectionRun::TRIGGERS)) {
            return $this->denied('unknown_trigger', 'Неизвестный способ запуска сбора.');
        }

        if ($trigger === 'scheduled' && $source->collection_method === 'manual') {
            return $this->denied('manual_source', 'Источник настроен только на ручное добавление данных.');
        }

        if ($trigger === 'scheduled' && $source->next_collection_at?->gt($now)) {
            return $this->denied('not_due', 'Следующий сбор ещё не наступил.');
        }

        if ($source->usesRemoteCollection()) {
            if (! $source->collection_authorized) {
                return $this->denied('collection_not_authorized', 'Автоматический сбор не подтверждён администратором.');
            }

            $baseUrl = $this->parseHttpUrl((string) $source->base_url);
            if ($baseUrl === null || $this->isPrivateHost($baseUrl['host'])) {
                return $this->denied('unsafe_base_url', 'Укажите безопасный публичный HTTP(S)-адрес источника.');
            }

            if ($source->collection_method === 'scrape' && ! $source->respect_robots_txt) {
                return $this->denied('robots_policy_required', 'Для автоматического сбора обязательно соблюдение robots.txt.');
            }

            $running = $source->collectionRuns()
                ->where('status', 'running')
                ->where('started_at', '>=', $now->copy()->subHour())
                ->exists();
            if ($running) {
                return $this->denied('collection_in_progress', 'Для источника уже выполняется другой запуск сбора.');
            }
        }

        $usedToday = (int) $source->collectionRuns()
            ->where('started_at', '>=', $now->copy()->startOfDay())
            ->sum('requested_count');

        if ($usedToday >= $source->max_requests_per_day) {
            return $this->denied('daily_limit_reached', 'Дневной лимит источника уже исчерпан.');
        }

        return ['allowed' => true, 'code' => null, 'message' => null];
    }

    /** @return array{allowed:bool,code:?string,message:?string} */
    public function canRequest(
        MarketPriceSource $source,
        MarketPriceCollectionRun $run,
        string $url,
        CarbonInterface $now,
        bool $robotsTxt = false,
    ): array {
        if ($run->status !== 'running') {
            return $this->denied('run_not_active', 'Сессия сбора уже завершена или заблокирована.');
        }

        if ($run->requested_count >= $source->max_requests_per_run) {
            return $this->denied('run_limit_reached', 'Достигнут лимит запросов за один запуск.');
        }

        $usedToday = (int) $source->collectionRuns()
            ->where('started_at', '>=', $now->copy()->startOfDay())
            ->sum('requested_count');

        if ($usedToday >= $source->max_requests_per_day) {
            return $this->denied('daily_limit_reached', 'Достигнут дневной лимит запросов.');
        }

        return $this->canUseUrl($source, $url, $robotsTxt);
    }

    /** @return array{allowed:bool,code:?string,message:?string} */
    public function canUseUrl(MarketPriceSource $source, string $url, bool $robotsTxt = false): array
    {
        $target = $this->parseHttpUrl($url);
        $base = $this->parseHttpUrl((string) $source->base_url);

        if ($target === null || $base === null || $this->isPrivateHost($target['host'])) {
            return $this->denied('unsafe_url', 'URL предложения не является безопасным публичным HTTP(S)-адресом.');
        }

        if (mb_strtolower($target['host']) !== mb_strtolower($base['host'])) {
            return $this->denied('host_not_allowed', 'URL предложения находится вне разрешённого домена источника.');
        }

        if ($target['scheme'] !== $base['scheme'] || $target['port'] !== $base['port']) {
            return $this->denied('origin_not_allowed', 'URL должен использовать тот же протокол и порт, что и источник.');
        }

        $prefixes = collect($source->allowed_path_prefixes)
            ->map(fn (mixed $prefix): string => '/'.ltrim(trim((string) $prefix), '/'))
            ->filter(fn (string $prefix): bool => $prefix !== '/')
            ->values();

        if (! $robotsTxt && $prefixes->isNotEmpty() && ! $prefixes->contains(
            fn (string $prefix): bool => str_starts_with($target['path'], $prefix),
        )) {
            return $this->denied('path_not_allowed', 'Путь URL не входит в разрешённые разделы источника.');
        }

        return ['allowed' => true, 'code' => null, 'message' => null];
    }

    /** @return array{scheme:string,host:string,port:int,path:string}|null */
    private function parseHttpUrl(string $url): ?array
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return null;
        }
        if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return [
            'scheme' => strtolower((string) $parts['scheme']),
            'host' => strtolower((string) $parts['host']),
            'port' => (int) ($parts['port'] ?? (strtolower((string) $parts['scheme']) === 'https' ? 443 : 80)),
            'path' => '/'.ltrim((string) ($parts['path'] ?? ''), '/'),
        ];
    }

    private function isPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /** @return array{allowed:false,code:string,message:string} */
    private function denied(string $code, string $message): array
    {
        return compact('code', 'message') + ['allowed' => false];
    }
}
