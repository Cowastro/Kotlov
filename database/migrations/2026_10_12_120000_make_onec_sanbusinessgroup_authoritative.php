<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $source = DB::table('integration_sources')->where('code', 'onec')->first();
        if (! $source) {
            return;
        }

        $supplier = DB::table('suppliers')
            ->where('code', 'sanbusinessgroup')
            ->orWhere('name', 'ООО «СанБизнесГруп»')
            ->first();

        $supplierId = $supplier?->id ?: DB::table('suppliers')->insertGetId([
            'code' => 'sanbusinessgroup',
            'name' => 'ООО «СанБизнесГруп»',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Основной поставщик каталога 1С.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settings = json_decode((string) ($source->settings ?? ''), true);
        $settings = is_array($settings) ? $settings : [];
        $settings['partner_name'] = 'ООО «СанБизнесГруп»';

        DB::table('integration_sources')->where('id', $source->id)->update([
            'supplier_id' => $supplierId,
            'name' => '1С СанБизнесГруп',
            'update_prices' => true,
            'update_stock' => true,
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);

        DB::table('integration_products')
            ->where('integration_source_id', $source->id)
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(COALESCE(stock_quantity, 0)) as total_quantity')
            ->groupBy('product_id')
            ->orderBy('product_id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $quantity = max(0, (int) $row->total_quantity);
                    DB::table('products')->where('id', $row->product_id)->update([
                        'in_stock' => $quantity > 0,
                        'stock_qty' => $quantity,
                        'availability_status' => $quantity > 0
                            ? Product::AVAILABILITY_IN_STOCK
                            : Product::AVAILABILITY_CHECK,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The previous source ownership and flags were inconsistent and must not be restored.
    }
};
