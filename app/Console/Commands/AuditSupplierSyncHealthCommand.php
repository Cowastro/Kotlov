<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditSupplierSyncHealthCommand extends Command
{
    protected $signature = 'supplier:audit-sync-health {--stale-hours=48 : Age after which supplier data is considered stale}';

    protected $description = 'Read-only aggregate health check for supplier price and stock synchronizations.';

    public function handle(): int
    {
        $staleHours = max(1, (int) $this->option('stale-hours'));

        if (! Schema::hasTable('supplier_products')) {
            $this->error('supplier_products table does not exist.');
            return self::FAILURE;
        }

        $rows = DB::table('suppliers as s')
            ->leftJoin('supplier_products as sp', 'sp.supplier_id', '=', 's.id')
            ->where('s.is_active', true)
            ->groupBy('s.id', 's.name', 's.code')
            ->selectRaw('s.id, s.name, s.code, COUNT(sp.id) as mappings')
            ->selectRaw('SUM(CASE WHEN sp.product_id IS NOT NULL THEN 1 ELSE 0 END) as linked')
            ->selectRaw('SUM(CASE WHEN sp.price_byn IS NOT NULL AND sp.price_byn > 0 THEN 1 ELSE 0 END) as priced')
            ->selectRaw('SUM(CASE WHEN sp.in_stock = 1 THEN 1 ELSE 0 END) as in_stock')
            ->selectRaw('MAX(sp.last_synced_at) as last_synced_at')
            ->selectRaw('MAX(sp.last_stock_synced_at) as last_stock_synced_at')
            ->orderBy('s.name')
            ->get();

        $table = [];
        foreach ($rows as $row) {
            $latest = $row->last_stock_synced_at ?: $row->last_synced_at;
            $ageHours = $latest ? (int) floor(now()->diffInHours($latest, true)) : null;
            $health = (int) $row->mappings === 0
                ? 'нет связок'
                : ($ageHours === null ? 'нет запусков' : ($ageHours > $staleHours ? 'устарело' : 'актуально'));

            $table[] = [
                $row->name,
                $row->code,
                (int) $row->mappings,
                (int) $row->linked,
                (int) $row->priced,
                (int) $row->in_stock,
                $latest ?: '—',
                $ageHours === null ? '—' : $ageHours,
                $health,
            ];
        }

        $this->table(
            ['supplier', 'code', 'mappings', 'linked', 'priced', 'stock+', 'last sync', 'age h', 'health'],
            $table
        );

        $activeSupplierSubquery = DB::table('supplier_products as asp')
            ->join('suppliers as active_s', 'active_s.id', '=', 'asp.supplier_id')
            ->whereColumn('asp.product_id', 'p.id')
            ->where('active_s.is_active', true)
            ->where('asp.in_stock', true);

        $anyActiveSupplierLink = DB::table('supplier_products as linked_sp')
            ->join('suppliers as linked_s', 'linked_s.id', '=', 'linked_sp.supplier_id')
            ->whereColumn('linked_sp.product_id', 'p.id')
            ->where('linked_s.is_active', true);

        $falsePositiveStock = DB::table('products as p')
            ->where('p.in_stock', true)
            ->where('p.is_archived', false)
            ->whereExists($anyActiveSupplierLink)
            ->whereNotExists($activeSupplierSubquery)
            ->count();

        $falseNegativeStock = DB::table('products as p')
            ->where('p.in_stock', false)
            ->where('p.is_archived', false)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('supplier_products as sp2')
                    ->join('suppliers as s2', 's2.id', '=', 'sp2.supplier_id')
                    ->whereColumn('sp2.product_id', 'p.id')
                    ->where('s2.is_active', true)
                    ->where('sp2.in_stock', true);
            })
            ->count();

        $uncertainShownAsStock = DB::table('products')
            ->where('availability_status', Product::AVAILABILITY_CHECK)
            ->where('in_stock', true)
            ->where('is_archived', false)
            ->count();

        $this->newLine();
        $this->table(['catalog check', 'count'], [
            ['Товар отмечен «В наличии», но ни один активный поставщик наличие не подтверждает', $falsePositiveStock],
            ['Поставщик подтверждает наличие, но товар на сайте не отмечен в наличии', $falseNegativeStock],
            ['Статус «Уточняйте наличие» одновременно с флагом in_stock', $uncertainShownAsStock],
        ]);

        if (Schema::hasTable('supplier_sync_runs')) {
            $this->newLine();
            $this->info('Recent journal runs:');
            $this->table(
                ['started', 'command', 'status', 'changes', 'price', 'stock'],
                DB::table('supplier_sync_runs')
                    ->latest('started_at')
                    ->limit(15)
                    ->get()
                    ->map(fn (object $run): array => [
                        $run->started_at,
                        $run->command,
                        $run->status,
                        $run->changes_count,
                        $run->price_changes_count,
                        $run->stock_changes_count,
                    ])->all()
            );
        }

        return self::SUCCESS;
    }
}
