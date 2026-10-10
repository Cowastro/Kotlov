<?php

namespace App\Console\Commands;

use App\Models\MarketPriceSource;
use App\Services\Market\MarketPriceCollector;
use Illuminate\Console\Command;

class CollectMarketPricesCommand extends Command
{
    protected $signature = 'market:collect-prices {--source= : ID или код одного источника}';

    protected $description = 'Безопасно собрать рыночные предложения из разрешённых источников';

    public function handle(MarketPriceCollector $collector): int
    {
        $sourceKey = $this->option('source');
        if ($sourceKey === null) {
            $runs = $collector->collectDue();
        } else {
            $source = ctype_digit((string) $sourceKey)
                ? MarketPriceSource::query()->find((int) $sourceKey)
                : MarketPriceSource::query()->where('code', $sourceKey)->first();
            if (! $source) {
                $this->error('Источник не найден.');

                return self::FAILURE;
            }

            $runs = [$collector->collect($source, 'manual')];
        }

        $this->info('Источников: '.count($runs)
            .' · успешно: '.collect($runs)->where('status', 'success')->count()
            .' · с предупреждениями: '.collect($runs)->where('status', 'warning')->count()
            .' · ошибок/блокировок: '.collect($runs)->whereIn('status', ['failed', 'blocked'])->count());

        return collect($runs)->contains(fn ($run): bool => in_array($run->status, ['failed', 'blocked'], true))
            ? self::FAILURE
            : self::SUCCESS;
    }
}
