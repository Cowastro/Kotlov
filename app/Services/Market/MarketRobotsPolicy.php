<?php

namespace App\Services\Market;

use App\Models\MarketPriceCollectionRun;
use App\Models\MarketPriceSource;

class MarketRobotsPolicy
{
    public function __construct(
        private readonly MarketCollectionHttpClient $http,
        private readonly MarketPriceCollectionManager $manager,
    ) {}

    public function allows(MarketPriceSource $source, MarketPriceCollectionRun $run, string $targetUrl): bool
    {
        if ($source->collection_method !== 'scrape') {
            return true;
        }

        $parts = parse_url((string) $source->base_url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            $this->manager->fail($run, 'robots_base_url_invalid', 'Невозможно сформировать адрес robots.txt.');

            return false;
        }

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $robotsUrl = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']).$port.'/robots.txt';
        $response = $this->http->get($run, $robotsUrl, robotsTxt: true);
        if ($response === null) {
            return false;
        }

        $path = '/'.ltrim((string) parse_url($targetUrl, PHP_URL_PATH), '/');
        if (! $this->pathAllowed($response->body(), $path)) {
            $this->manager->fail($run, 'robots_disallowed', 'robots.txt запрещает сбор пути '.$path.'.');

            return false;
        }

        return true;
    }

    private function pathAllowed(string $robots, string $path): bool
    {
        $rules = [];
        $applies = false;

        foreach (preg_split('/\R/', $robots) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s*#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                $applies = in_array(strtolower($value), ['*', 'kotlovmarketbot'], true);

                continue;
            }

            if ($applies && in_array($field, ['allow', 'disallow'], true) && $value !== '') {
                $rules[] = ['allow' => $field === 'allow', 'path' => $value];
            }
        }

        $matched = collect($rules)
            ->filter(fn (array $rule): bool => str_starts_with($path, $rule['path']))
            ->sortByDesc(fn (array $rule): int => strlen($rule['path']))
            ->first();

        return $matched === null || $matched['allow'];
    }
}
