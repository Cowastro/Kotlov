<?php

namespace App\Console\Commands;

use App\Services\Suppliers\WebProductSources;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Checks the internet search behind «Найти в интернете» in the КП form:
 * whether the key is set and which pages a query finds. Read-only.
 *
 *   php artisan catalog:web-product-sources "Wellmix WRS D 50-120SF"
 */
class WebProductSourcesCommand extends Command
{
    protected $signature = 'catalog:web-product-sources {query* : Model to look for}';

    protected $description = 'Show the product pages the КП internet search finds for a query';

    public function handle(WebProductSources $sources): int
    {
        $this->line('metric.web_sources.configured='.(int) $sources->isConfigured());
        $found = $sources->search(implode(' ', (array) $this->argument('query')));
        foreach (array_keys($found) as $url) {
            $this->line('audit.web_sources.page='.Str::slug((string) parse_url($url, PHP_URL_HOST)).':'.Str::slug(Str::limit((string) parse_url($url, PHP_URL_PATH), 80, '')));
        }
        $this->line('metric.web_sources.pages='.count($found));

        return self::SUCCESS;
    }
}
