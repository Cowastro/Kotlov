<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-purpose, auditable price refresh for the Ermak KVL items that are
 * published by Ligmet's retail storefront (100kaminov.by).
 *
 * The new Ligmet wholesale workbook does not include these KVL positions, so
 * they must not be invented from unrelated rows in the workbook. This command
 * only updates exact existing catalogue names and reports missing cards.
 */
class SyncLigmetErmakKvlCommand extends Command
{
    protected $signature = 'supplier:sync-ligmet-ermak-kvl
        {--apply : Write the public retail prices to existing product cards}';

    protected $description = 'Refresh Ermak KVL retail prices from Ligmet storefront (100kaminov.by).';

    private const SOURCE_BASE = 'https://100kaminov.by';

    /** @var array<string,string> exact KOTLOV product name => storefront path */
    private const ITEMS = [
        'Колонка водогрейная Ермак КВЛ-90 (сталь)' => '/p182797479-kolonka-vodogrejnaya-ermak.html',
        'Колонка водогрейная Ермак КВЛ-90 (чугун)' => '/p182797617-kolonka-vodogrejnaya-ermak.html',
        'Топка водогрейной колонки Ермак КВЛ (Сталь)' => '/p209796635-topka-vodogrejnoj-kolonki.html',
        'Бак Ермак КВЛ' => '/p209796774-bak-ermak-kvl.html',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $now = now();
        $stats = ['checked' => 0, 'updated' => 0, 'unchanged' => 0, 'missing_catalogue' => 0, 'errors' => 0];
        $rows = [];

        foreach (self::ITEMS as $name => $path) {
            $stats['checked']++;
            $url = self::SOURCE_BASE . $path;
            $price = $this->sourcePrice($url);
            $product = DB::table('products')
                ->where('name', $name)
                ->where('is_archived', false)
                ->first(['id', 'sku', 'price']);

            if ($price === null) {
                $stats['errors']++;
                $rows[] = [$name, '—', '—', 'ошибка чтения цены'];
                continue;
            }

            if ($product === null) {
                $stats['missing_catalogue']++;
                $rows[] = [$name, '—', number_format($price, 2, '.', ''), 'нет карточки — не создавал'];
                continue;
            }

            $old = (float) $product->price;
            $new = (float) $price;
            if (abs($old - $new) < 0.005) {
                $stats['unchanged']++;
                $rows[] = [$name, number_format($old, 2, '.', ''), number_format($new, 2, '.', ''), 'без изменений'];
                continue;
            }

            if ($apply) {
                DB::table('products')->where('id', $product->id)->update([
                    'price' => $new,
                    'updated_at' => $now,
                ]);
            }
            $stats['updated']++;
            $rows[] = [$name, number_format($old, 2, '.', ''), number_format($new, 2, '.', ''), $apply ? 'обновлено' : 'будет обновлено'];
        }

        $this->table(['позиция', 'было, BYN', 'стало, BYN', 'результат'], $rows);
        $this->newLine();
        $this->table(['метрика', 'кол-во'], array_map(
            fn (string $key, int $value) => [$key, $value],
            array_keys($stats),
            array_values($stats)
        ));

        if ($apply && DB::table('supplier_syncs')->where('key', 'ligmet_stock')->exists()) {
            DB::table('supplier_syncs')->where('key', 'ligmet_stock')->update([
                'last_run_at' => $now,
                'last_status' => $stats['errors'] === 0 ? 'updated' : 'failed',
                'last_exit_code' => $stats['errors'] === 0 ? 0 : 1,
                'updated_at' => $now,
            ]);
        }

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function sourcePrice(string $url): ?float
    {
        $context = stream_context_create(['http' => [
            'timeout' => 60,
            'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: text/html,*/*",
        ]]);
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            return null;
        }

        foreach ([
            '/"price"\\s*:\\s*"?([0-9.,]+)/iu',
            '/itemprop="price"[^>]*content="([0-9.,]+)"/iu',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return (float) str_replace(',', '.', $match[1]);
            }
        }

        return null;
    }
}
