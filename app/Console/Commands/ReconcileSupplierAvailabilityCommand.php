<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileSupplierAvailabilityCommand extends Command
{
    protected $signature = 'supplier:reconcile-availability
        {--apply : Recalculate catalog availability for every product linked to an active supplier}';

    protected $description = 'Safely reconcile storefront availability with all active supplier stock links.';

    public function handle(SupplierProductAvailabilityService $availability): int
    {
        $productIds = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('s.is_active', true)
            ->whereNotNull('sp.product_id')
            ->distinct()
            ->orderBy('sp.product_id')
            ->pluck('sp.product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $before = $this->inconsistencyCounts();
        $this->table(['Проверка', 'Количество'], $this->rows($before));

        if (! (bool) $this->option('apply')) {
            $this->warn('Предпросмотр: данные не изменены. Для исправления добавьте --apply.');
            return self::SUCCESS;
        }

        foreach (array_chunk($productIds, 500) as $chunk) {
            $availability->refreshMany($chunk);
        }

        // A storefront label of "Уточняйте наличие" must never be paired with
        // the boolean in-stock flag, even for manually managed products that do
        // not currently have an active supplier link.
        DB::table('products')
            ->where('availability_status', Product::AVAILABILITY_CHECK)
            ->where('in_stock', true)
            ->update([
                'in_stock' => false,
                'stock_qty' => null,
                'updated_at' => now(),
            ]);

        $after = $this->inconsistencyCounts();
        $this->newLine();
        $this->info('Пересчитано связанных товаров: ' . count($productIds));
        $this->table(['Проверка после исправления', 'Количество'], $this->rows($after));

        return array_sum($after) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{false_positive:int,false_negative:int,uncertain_in_stock:int} */
    private function inconsistencyCounts(): array
    {
        $hasActiveLink = fn ($query) => $query
            ->selectRaw('1')
            ->from('supplier_products as linked_sp')
            ->join('suppliers as linked_s', 'linked_s.id', '=', 'linked_sp.supplier_id')
            ->whereColumn('linked_sp.product_id', 'p.id')
            ->where('linked_s.is_active', true);

        $hasConfirmedStock = fn ($query) => $query
            ->selectRaw('1')
            ->from('supplier_products as stock_sp')
            ->join('suppliers as stock_s', 'stock_s.id', '=', 'stock_sp.supplier_id')
            ->whereColumn('stock_sp.product_id', 'p.id')
            ->where('stock_s.is_active', true)
            ->where('stock_sp.in_stock', true);

        return [
            'false_positive' => DB::table('products as p')
                ->where('p.in_stock', true)
                ->where('p.is_archived', false)
                ->whereExists($hasActiveLink)
                ->whereNotExists($hasConfirmedStock)
                ->count(),
            'false_negative' => DB::table('products as p')
                ->where('p.in_stock', false)
                ->where('p.is_archived', false)
                ->whereExists($hasConfirmedStock)
                ->count(),
            'uncertain_in_stock' => DB::table('products as p')
                ->where('p.availability_status', Product::AVAILABILITY_CHECK)
                ->where('p.in_stock', true)
                ->where('p.is_archived', false)
                ->count(),
        ];
    }

    /** @param array{false_positive:int,false_negative:int,uncertain_in_stock:int} $counts */
    private function rows(array $counts): array
    {
        return [
            ['Нет подтверждённого остатка, но на сайте «В наличии»', $counts['false_positive']],
            ['Остаток подтверждён, но на сайте не «В наличии»', $counts['false_negative']],
            ['«Уточняйте наличие» одновременно с флагом in_stock', $counts['uncertain_in_stock']],
        ];
    }
}
