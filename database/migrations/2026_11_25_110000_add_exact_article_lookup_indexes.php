<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The existing name+SKU FULLTEXT index cannot serve equality lookups by SKU.
        if (! Schema::hasIndex('products', ['sku'])) {
            Schema::table('products', fn (Blueprint $table) => $table->index('sku', 'products_sku_lookup_idx'));
        }
        // The existing supplier_id+compact index cannot serve searches across all suppliers.
        if (! Schema::hasIndex('supplier_products', ['supplier_article_compact'])) {
            Schema::table('supplier_products', fn (Blueprint $table) => $table->index('supplier_article_compact', 'sp_article_compact_lookup_idx'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('products', 'products_sku_lookup_idx')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropIndex('products_sku_lookup_idx'));
        }
        if (Schema::hasIndex('supplier_products', 'sp_article_compact_lookup_idx')) {
            Schema::table('supplier_products', fn (Blueprint $table) => $table->dropIndex('sp_article_compact_lookup_idx'));
        }
    }
};
