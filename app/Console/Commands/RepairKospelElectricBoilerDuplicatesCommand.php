<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairKospelElectricBoilerDuplicatesCommand extends Command
{
    protected $signature = 'catalog:repair-kospel-electric-boiler-duplicates
        {--apply : Move data to legacy public cards and archive duplicate imported cards; default is dry-run}';

    protected $description = 'Repair Kospel electric boiler duplicates created by a later import while preserving legacy public URLs.';

    /**
     * @var array<int,array{keep:int, duplicate:int, model:string}>
     */
    private const PAIRS = [
        ['keep' => 10199, 'duplicate' => 17915, 'model' => 'EKCO.L2M.04'],
        ['keep' => 10200, 'duplicate' => 17919, 'model' => 'EKCO.L2M.06'],
        ['keep' => 10201, 'duplicate' => 17993, 'model' => 'EKCO.L2M.08'],
        ['keep' => 10202, 'duplicate' => 17928, 'model' => 'EKCO.L2M.12'],
        ['keep' => 10203, 'duplicate' => 17991, 'model' => 'EKCO.L2M.15'],
        ['keep' => 10204, 'duplicate' => 17959, 'model' => 'EKCO.L2M.18'],
        ['keep' => 10205, 'duplicate' => 17960, 'model' => 'EKCO.L2M.21'],
        ['keep' => 10206, 'duplicate' => 17939, 'model' => 'EKCO.L2M.24'],
        ['keep' => 10207, 'duplicate' => 17945, 'model' => 'EKCO.L2M.30'],
        ['keep' => 10208, 'duplicate' => 17932, 'model' => 'EKCO.L2M.36'],
        ['keep' => 10209, 'duplicate' => 17933, 'model' => 'EKCO.LN2M.04'],
        ['keep' => 10210, 'duplicate' => 17913, 'model' => 'EKCO.LN2M.06'],
        ['keep' => 10211, 'duplicate' => 17949, 'model' => 'EKCO.LN2M.08'],
        ['keep' => 10212, 'duplicate' => 17979, 'model' => 'EKCO.LN2M.12'],
        ['keep' => 10213, 'duplicate' => 17917, 'model' => 'EKCO.LN2M.15'],
        ['keep' => 10214, 'duplicate' => 17937, 'model' => 'EKCO.LN2M.18'],
        ['keep' => 10215, 'duplicate' => 17935, 'model' => 'EKCO.LN2M.21'],
        ['keep' => 10216, 'duplicate' => 17956, 'model' => 'EKCO.LN2M.24'],
        ['keep' => 8276, 'duplicate' => 17970, 'model' => 'EKCO.R2.18'],
        ['keep' => 8277, 'duplicate' => 17968, 'model' => 'EKCO.R2.21'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $stats = [
            'checked' => 0,
            'price_updates' => 0,
            'supplier_links_moved' => 0,
            'duplicates_archived' => 0,
            'already_ok' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $rows = [];

        foreach (self::PAIRS as $pair) {
            $stats['checked']++;

            $keep = Product::query()->find($pair['keep']);
            $duplicate = Product::query()->find($pair['duplicate']);

            if (! $keep || ! $duplicate) {
                $stats['skipped']++;
                $rows[] = [
                    $pair['model'],
                    $pair['keep'],
                    $pair['duplicate'],
                    'missing product',
                    '',
                    '',
                    '',
                ];
                continue;
            }

            if (! $this->containsModel((string) $keep->name, $pair['model'])
                || ! $this->containsModel((string) $duplicate->name, $pair['model'])) {
                $stats['errors']++;
                $rows[] = [
                    $pair['model'],
                    $keep->id,
                    $duplicate->id,
                    'model mismatch',
                    $keep->name,
                    $duplicate->name,
                    '',
                ];
                continue;
            }

            $supplierLinks = DB::table('supplier_products')
                ->where('product_id', $duplicate->id)
                ->get(['id', 'supplier_article', 'price_byn']);

            $duplicateActive = (bool) $duplicate->is_active || ! (bool) $duplicate->is_archived;
            $newPrice = (float) $duplicate->price > 0 ? (float) $duplicate->price : (float) $keep->price;
            $priceChanged = $newPrice > 0 && abs((float) $keep->price - $newPrice) >= 0.005;

            if (! $priceChanged && $supplierLinks->isEmpty() && ! $duplicateActive) {
                $stats['already_ok']++;
            }

            if ($priceChanged) {
                $stats['price_updates']++;
            }

            if ($supplierLinks->isNotEmpty()) {
                $stats['supplier_links_moved'] += $supplierLinks->count();
            }

            if ($duplicateActive) {
                $stats['duplicates_archived']++;
            }

            $rows[] = [
                $pair['model'],
                $keep->id,
                $duplicate->id,
                number_format((float) $keep->price, 2, '.', '') . ' -> ' . number_format($newPrice, 2, '.', ''),
                $supplierLinks->pluck('supplier_article')->filter()->implode(', ') ?: '-',
                ($duplicateActive ? 'archive' : 'archived'),
                $keep->slug,
            ];

            if (! $apply) {
                continue;
            }

            $keep->forceFill([
                'price' => $newPrice,
                'in_stock' => (bool) $duplicate->in_stock || (bool) $keep->in_stock,
                'availability_status' => $duplicate->availability_status ?: $keep->availability_status,
                'updated_at' => now(),
            ])->save();

            foreach ($supplierLinks as $link) {
                DB::table('supplier_products')
                    ->where('id', (int) $link->id)
                    ->update([
                        'product_id' => $keep->id,
                        'product_sku' => $keep->sku,
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
            ? '<fg=red;options=bold>APPLY: Kospel duplicate products repaired.</>'
            : '<fg=yellow;options=bold>DRY RUN: no database changes.</>');

        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());
        $this->table(['model', 'keep', 'duplicate', 'price', 'supplier_articles', 'duplicate_action', 'keep_slug'], $rows);

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function containsModel(string $name, string $model): bool
    {
        return $this->canonicalModel($name) === $this->canonicalModel($model);
    }

    private function canonicalModel(string $value): string
    {
        $text = mb_strtoupper(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = str_replace('Ё', 'Е', $text);

        $patterns = [
            '/EKCO\s*\.?\s*LN2M\s*[-.]?\s*(\d{1,2})/u' => 'EKCO.LN2M',
            '/EKCO\s*\.?\s*L2M\s*[-.]?\s*(\d{1,2})/u' => 'EKCO.L2M',
            '/EKCO\s*\.?\s*R2\s*[-.]?\s*(\d{1,2})/u' => 'EKCO.R2',
        ];

        foreach ($patterns as $pattern => $prefix) {
            if (preg_match($pattern, $text, $match)) {
                return $prefix . '.' . str_pad((string) ((int) $match[1]), 2, '0', STR_PAD_LEFT);
            }
        }

        return preg_replace('/[^A-Z0-9]+/u', '', $text) ?? $text;
    }
}
