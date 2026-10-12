<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResolveBaniaCollisionsCommand extends Command
{
    protected $signature = 'supplier:resolve-bania-collisions
        {--dry-run : Preview the guarded corrections}
        {--apply : Apply the guarded corrections}';

    protected $description = 'Resolve reviewed BANIA generic-article collisions and archive confirmed obsolete duplicate cards.';

    private const LINK_REPAIRS = [
        'KOTLOV-004144' => ['old' => 'ОПТИМУМ', 'article' => '4610094778165', 'retail' => 934.00, 'cost' => 771.93],
        'KOTLOV-004321' => ['old' => 'КУППЕРПРАКТИК', 'article' => 'ОК.68.000.00', 'retail' => 1430.00, 'cost' => 1241.93],
        'KOTLOV-004322' => ['old' => 'КУППЕРПРАКТИК', 'article' => 'ОК.59.000.00', 'retail' => 1755.00, 'cost' => 1521.27],
        'KOTLOV-003915' => ['old' => 'КУППЕРПРАКТИК', 'article' => 'ОК.71.000.00', 'retail' => 2005.00, 'cost' => 1739.14],
        'PS-003.824' => ['old' => 'АВРОРАINOX', 'article' => '34000', 'retail' => 1265.00, 'cost' => null],
    ];

    private const ARCHIVE_SKUS = [
        'KOTLOV-004271',
        'KOTLOV-004270',
        'KOTLOV-004180',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $supplierId = (int) DB::table('suppliers')->where('code', 'bania')->value('id');
        if ($supplierId <= 0) {
            $this->error('BANIA supplier is not registered.');
            return self::FAILURE;
        }

        $stats = ['link_repairs' => 0, 'price_restored' => 0, 'archived' => 0, 'guard_skips' => 0];
        $now = now();

        DB::transaction(function () use ($apply, $supplierId, $now, &$stats): void {
            foreach (self::LINK_REPAIRS as $sku => $repair) {
                $product = DB::table('products')->where('sku', $sku)->first(['id']);
                if (! $product) {
                    $stats['guard_skips']++;
                    continue;
                }

                $link = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('product_id', $product->id)
                    ->first(['id', 'supplier_article']);
                if (! $link || $this->normalizeArticle((string) $link->supplier_article) !== $this->normalizeArticle($repair['old'])) {
                    $stats['guard_skips']++;
                    continue;
                }

                $stats['link_repairs']++;
                $this->line(sprintf('%s: %s -> %s; retail %.2f', $sku, $repair['old'], $repair['article'], $repair['retail']));
                if (! $apply) {
                    continue;
                }

                $targetLink = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('supplier_article_normalized', $this->normalizeArticle($repair['article']))
                    ->first(['id', 'product_id']);
                if ($targetLink && $targetLink->product_id !== null && (int) $targetLink->product_id !== (int) $product->id) {
                    $stats['link_repairs']--;
                    $stats['archived']++;
                    $this->warn($sku . ': archive obsolete card; target supplier article is already linked to the canonical product.');
                    DB::table('products')->where('id', $product->id)->update([
                        'is_active' => false,
                        'is_archived' => true,
                        'in_stock' => false,
                        'updated_at' => $now,
                    ]);
                    DB::table('supplier_products')->where('id', $link->id)->update([
                        'in_stock' => false,
                        'stock_quantity' => 0,
                        'stock_status' => 'out_of_stock',
                        'updated_at' => $now,
                    ]);
                    continue;
                }

                $linkUpdates = [
                    'supplier_article' => $repair['article'],
                    'supplier_article_normalized' => $this->normalizeArticle($repair['article']),
                    'supplier_article_compact' => $this->normalizeArticle($repair['article']),
                    'updated_at' => $now,
                ];
                if ($repair['cost'] !== null) {
                    $linkUpdates += [
                        'price' => $repair['cost'],
                        'price_byn' => $repair['cost'],
                        'in_stock' => true,
                        'stock_quantity' => 1,
                        'stock_status' => 'in_stock',
                    ];
                } else {
                    $linkUpdates += [
                        'in_stock' => false,
                        'stock_quantity' => 0,
                        'stock_status' => 'out_of_stock',
                    ];
                }
                if ($targetLink) {
                    DB::table('supplier_products')->where('id', $link->id)->update([
                        'product_id' => null,
                        'updated_at' => $now,
                    ]);
                    $linkUpdates['product_id'] = $product->id;
                    DB::table('supplier_products')->where('id', $targetLink->id)->update($linkUpdates);
                } else {
                    DB::table('supplier_products')->where('id', $link->id)->update($linkUpdates);
                }
                DB::table('products')->where('id', $product->id)->update([
                    'price' => $repair['retail'],
                    'in_stock' => $repair['cost'] !== null,
                    'updated_at' => $now,
                ]);
            }

            $scratcher = DB::table('products')->where('sku', 'KOTLOV-004402')->first(['id']);
            $scratcherLink = $scratcher ? DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('product_id', $scratcher->id)
                ->where('supplier_article_normalized', '40300')
                ->exists() : false;
            if ($scratcher && $scratcherLink) {
                $stats['price_restored']++;
                $this->line('KOTLOV-004402: restore retail 1680.00 -> 7.00 (40300 source collision).');
                if ($apply) {
                    DB::table('products')->where('id', $scratcher->id)->update(['price' => 7.00, 'updated_at' => $now]);
                }
            } else {
                $stats['guard_skips']++;
            }

            foreach (self::ARCHIVE_SKUS as $sku) {
                $product = DB::table('products')->where('sku', $sku)->first(['id']);
                if (! $product) {
                    $stats['guard_skips']++;
                    continue;
                }

                $hasGenericEtnaLink = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('product_id', $product->id)
                    ->where('supplier_article_normalized', 'ЭТНА')
                    ->exists();
                if (! $hasGenericEtnaLink) {
                    $stats['guard_skips']++;
                    continue;
                }

                $stats['archived']++;
                $this->line($sku . ': archive obsolete generic-article ETNA card.');
                if (! $apply) {
                    continue;
                }

                DB::table('products')->where('id', $product->id)->update([
                    'is_active' => false,
                    'is_archived' => true,
                    'in_stock' => false,
                    'updated_at' => $now,
                ]);
                DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('product_id', $product->id)
                    ->update([
                        'in_stock' => false,
                        'stock_quantity' => 0,
                        'stock_status' => 'out_of_stock',
                        'updated_at' => $now,
                    ]);
            }

        });

        $this->table(['metric', 'count'], array_map(
            fn (string $metric, int $count): array => [$metric, $count],
            array_keys($stats),
            array_values($stats)
        ));

        return self::SUCCESS;
    }

    private function normalizeArticle(string $value): string
    {
        return mb_strtoupper(preg_replace('/[^0-9A-ZА-ЯЁ]+/u', '', trim($value)) ?? '');
    }
}
