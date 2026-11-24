<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_price_batch_items', function (Blueprint $table) {
            $table->decimal('purchase_net', 12, 2)->nullable();
        });

        // Older manual batches copied the supplier's net amount into the gross cost field.
        // Identify those snapshots by their recorded source, linked item and exact price.
        DB::table('onec_price_batch_items')
            ->where('note', 'like', '%закупка поставщика (в 1С ещё не поступал)%')
            ->where('purchase', '>', 0)
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $productId = DB::table('integration_products')->where('id', $item->integration_product_id)->value('product_id')
                        ?? DB::table('onec_items')->where('ref_key', $item->item_key)->value('product_id');
                    if (! $productId) {
                        continue;
                    }
                    $confirmed = DB::table('supplier_products')->where('product_id', $productId)
                        ->where('match_status', 'matched')->where('price_byn', $item->purchase)
                        ->where('raw->procurement_source', 'БелОМО')->where('raw->vat_included', false)->exists();
                    if ($confirmed) {
                        DB::table('onec_price_batch_items')->where('id', $item->id)->update([
                            'purchase_net' => $item->purchase,
                            'purchase' => round((float) $item->purchase * 1.20, 2),
                            'note' => $item->note.' без НДС',
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('onec_price_batch_items', function (Blueprint $table) {
            $table->dropColumn('purchase_net');
        });
    }
};
