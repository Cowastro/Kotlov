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
    ];

    /** Reviewed obsolete cards; ID + SKU is the destructive-operation guard. */
    private const ARCHIVE_PRODUCTS = [
        3824 => 'PS-003.824',
        16193 => 'KOTLOV-003915',
        16458 => 'KOTLOV-004180',
        16548 => 'KOTLOV-004270',
        16549 => 'KOTLOV-004271',
        16599 => 'KOTLOV-004321',
        16600 => 'KOTLOV-004322',
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
                $product = $this->findActiveProductBySku($sku);
                if (! $product) {
                    $stats['guard_skips']++;
                    continue;
                }

                $link = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('product_id', $product->id)
                    ->get(['id', 'supplier_article'])
                    ->first(fn (object $candidate): bool => $this->normalizeArticle((string) $candidate->supplier_article) === $this->normalizeArticle($repair['old']));
                if (! $link) {
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
                    ->where(function ($query) use ($repair): void {
                        $query->where('supplier_article', $repair['article'])
                            ->orWhere('supplier_article_normalized', $this->normalizeArticle($repair['article']));
                    })
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

            $scratcher = $this->findActiveProductBySku('KOTLOV-004402');
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

            foreach (self::ARCHIVE_PRODUCTS as $productId => $sku) {
                $product = DB::table('products')
                    ->where('id', $productId)
                    ->where('sku', $sku)
                    ->first(['id', 'is_archived']);
                if (! $product || (bool) $product->is_archived) {
                    $stats['guard_skips']++;
                    continue;
                }

                $stats['archived']++;
                $this->line(sprintf('%s (id=%d): archive reviewed obsolete duplicate card.', $sku, $productId));
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

    private function findActiveProductBySku(string $sku): ?object
    {
        $active = DB::table('products')
            ->where('sku', $sku)
            ->where('is_archived', false)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first(['id']);

        return $active ?: DB::table('products')
            ->where('sku', $sku)
            ->orderByDesc('id')
            ->first(['id']);
    }
}
