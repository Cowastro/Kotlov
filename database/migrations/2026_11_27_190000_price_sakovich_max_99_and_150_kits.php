<?php

use App\Models\Supplier;
use App\Services\Suppliers\SakovichPelletKits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplier = Supplier::where('code', 'santehsklad')->first();
        if ($supplier) {
            DB::transaction(function () use ($supplier): void {
                app(SakovichPelletKits::class)->refresh($supplier);

                // The previous offer on the Max 150 kit priced only the boiler body.
                $supplier->supplierProducts()
                    ->where('supplier_article_normalized', 'PELLET-MAX-150')
                    ->whereHas('product', fn ($query) => $query->where('sku', 'KOTLOV-000318'))
                    ->update([
                        'product_id' => null,
                        'product_sku' => null,
                        'match_status' => 'unmatched',
                        'match_confidence' => null,
                    ]);
            });
        }
    }

    public function down(): void {}
};
