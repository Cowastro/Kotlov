<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Suppliers\SakovichPelletKits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $supplier = Supplier::where('code', 'santehsklad')->first();
            if ($supplier) {
                app(SakovichPelletKits::class)->refresh($supplier);
            }

            $product = Product::where('sku', 'KOTLOV-000320')->first();
            if (! $product) {
                return;
            }

            $composition = 'Теплообменник 35 кВт, горелка KIPI Rotary 8–36 кВт, бункер 450 л';
            $specs = $product->specs ?? [];
            foreach ($specs as &$spec) {
                if (($spec['key'] ?? '') === 'Комплектация') {
                    $spec['value'] = $composition;
                }
            }
            unset($spec);
            $product->update(['specs' => $specs]);
            DB::table('product_attribute_values')->where('product_id', $product->id)
                ->whereIn('attribute_id', DB::table('attributes')->select('id')->where('name', 'Комплектация'))
                ->where('value', 'like', '%PRAKTIK%')->update(['value' => $composition]);
        });
    }

    public function down(): void
    {
        // Preserve current supplier prices and product specifications.
    }
};
