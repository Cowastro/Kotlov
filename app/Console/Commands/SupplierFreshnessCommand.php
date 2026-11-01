<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SupplierFreshnessCommand extends Command
{
    protected $signature = 'supplier:freshness';

    protected $description = 'Per active supplier: linked offers and hours since their prices were last synced (counts only)';

    public function handle(): int
    {
        $rows = DB::table('suppliers')
            ->leftJoin('supplier_products', 'supplier_products.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.is_active', true)
            ->groupBy('suppliers.id', 'suppliers.code')
            ->orderBy('suppliers.code')
            ->get([
                'suppliers.code',
                DB::raw('COUNT(supplier_products.id) as offers'),
                DB::raw('SUM(CASE WHEN supplier_products.product_id IS NOT NULL THEN 1 ELSE 0 END) as linked'),
                DB::raw('MAX(supplier_products.last_synced_at) as synced_at'),
                DB::raw('MAX(supplier_products.updated_at) as updated_at'),
            ]);

        // Hours as numbers only: the server workflow prints these lines and nothing else.
        $hours = fn ($at): int => $at ? (int) now()->diffInHours($at, true) : -1;
        foreach ($rows as $row) {
            $code = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $row->code));
            $this->line("metric.supplier_fresh.{$code}.offers=".(int) $row->offers);
            $this->line("metric.supplier_fresh.{$code}.linked=".(int) $row->linked);
            $this->line("metric.supplier_fresh.{$code}.synced_h=".$hours($row->synced_at));
            $this->line("metric.supplier_fresh.{$code}.updated_h=".$hours($row->updated_at));
        }

        return self::SUCCESS;
    }
}
