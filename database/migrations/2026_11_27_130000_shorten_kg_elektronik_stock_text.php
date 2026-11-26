<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The KG Elektronik offers just say «Под заказ»: no country or supplier in the text. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('supplier_products')->where('stock_text', 'Под заказ из Польши через ООО «СанБизнесГруп»')
            ->update(['stock_text' => 'Под заказ', 'updated_at' => now()]);
    }

    public function down(): void {}
};
