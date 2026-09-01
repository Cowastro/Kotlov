<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairTeplovSukhovMcBlackPricesCommand extends Command
{
    protected $signature = 'catalog:repair-teplov-mc-black-prices
        {--apply : Write prices, move supplier links, and archive duplicate cards; default is dry-run}';

    protected $description = 'Repair Teplov i Sukhov MC Black sandwich pipe prices that were split into duplicate imported cards.';

    /**
     * @var array<int,array{target:int, duplicate:int, article:string, price:float}>
     */
    private const REPAIRS = [
        [
            'target' => 11891,
            'duplicate' => 16004,
            'article' => 'TS.MCB.TRB.0150.77344-01',
            'price' => 211.09,
        ],
        [
            'target' => 11890,
            'duplicate' => 16005,
            'article' => 'TS.MCB.TRB.0150.77344-02',
            'price' => 141.14,
        ],
        [
            'target' => 11889,
            'duplicate' => 16006,
            'article' => 'TS.MCB.TRB.0150.77993',
            'price' => 94.50,
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $stats = [
            'checked' => 0,
            'price_updates' => 0,
            'supplier_links_moved' => 0,
            'duplicates_archived' => 0,
            'errors' => 0,
        ];

        foreach (self::REPAIRS as $repair) {
            $stats['checked']++;

            $target = Product::query()->find($repair['target']);
            $duplicate = Product::query()->find($repair['duplicate']);

            if (! $target || ! $duplicate) {
                $stats['errors']++;
                $rows[] = [
                    $repair['target'],
                    $repair['duplicate'],
                    $repair['article'],
                    'missing product',
                    '',
                    '',
                    '',
                ];
                continue;
            }

            $supplierLink = DB::table('supplier_products')
                ->where('supplier_article', $repair['article'])
                ->first(['id', 'product_id', 'price_byn']);

            $priceChanged = abs((float) $target->price - $repair['price']) >= 0.005;
            $linkNeedsMove = $supplierLink && (int) $supplierLink->product_id !== (int) $target->id;
            $duplicateActive = ! (bool) $duplicate->is_archived || (bool) $duplicate->is_active;

            $rows[] = [
                $target->id,
                $duplicate->id,
                $repair['article'],
                number_format((float) $target->price, 2, '.', ''),
                number_format($repair['price'], 2, '.', ''),
                $supplierLink ? ('#' . $supplierLink->id . ' product ' . $supplierLink->product_id . ' -> ' . $target->id) : 'not found',
                $duplicateActive ? 'archive duplicate' : 'already archived',
            ];

            if ($priceChanged) {
                $stats['price_updates']++;
            }

            if ($linkNeedsMove) {
                $stats['supplier_links_moved']++;
            }

            if ($duplicateActive) {
                $stats['duplicates_archived']++;
            }

            if (! $apply) {
                continue;
            }

            $target->forceFill([
                'price' => $repair['price'],
                'in_stock' => true,
                'availability_status' => Product::AVAILABILITY_IN_STOCK,
                'updated_at' => now(),
            ])->save();

            if ($supplierLink) {
                DB::table('supplier_products')
                    ->where('id', (int) $supplierLink->id)
                    ->update([
                        'product_id' => $target->id,
                        'product_sku' => $target->sku,
                        'price' => $repair['price'],
                        'price_byn' => $repair['price'],
                        'last_synced_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            $duplicate->forceFill([
                'is_active' => false,
                'is_archived' => true,
                'updated_at' => now(),
            ])->save();
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Teplov MC Black prices repaired.</>'
            : '<fg=yellow;options=bold>DRY RUN: no database changes.</>');

        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        $this->table(['target', 'duplicate', 'article', 'old_price', 'new_price', 'supplier_link', 'duplicate_action'], $rows);

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
