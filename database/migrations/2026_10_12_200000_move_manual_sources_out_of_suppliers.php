<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'content_source_url')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->text('content_source_url')->nullable()->after('video_url');
            });
        }

        $manualSupplierIds = DB::table('suppliers')
            ->where('code', 'manual-source')
            ->pluck('id');

        if ($manualSupplierIds->isEmpty()) {
            return;
        }

        DB::table('supplier_products')
            ->whereIn('supplier_id', $manualSupplierIds)
            ->whereNotNull('source_url')
            ->where('source_url', '!=', '')
            ->orderBy('id')
            ->get(['product_id', 'source_url'])
            ->each(function (object $source): void {
                if (! $source->product_id) {
                    return;
                }

                DB::table('products')
                    ->where('id', $source->product_id)
                    ->where(fn ($query) => $query
                        ->whereNull('content_source_url')
                        ->orWhere('content_source_url', ''))
                    ->update(['content_source_url' => $source->source_url]);
            });

        DB::table('supplier_products')->whereIn('supplier_id', $manualSupplierIds)->delete();
        DB::table('suppliers')->whereIn('id', $manualSupplierIds)->delete();
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'content_source_url')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('content_source_url');
            });
        }
    }
};
