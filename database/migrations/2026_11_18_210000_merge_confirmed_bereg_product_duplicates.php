<?php

use App\Services\MergeConfirmedBeregProducts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'merged_into_product_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('merged_into_product_id')->nullable()->constrained('products')->restrictOnDelete();
                $table->index('merged_into_product_id');
            });
        }
        app(MergeConfirmedBeregProducts::class)->run(json_decode(file_get_contents(resource_path('data/chisty-bereg-exact-duplicates-2026-11-18.json')), true, 512, JSON_THROW_ON_ERROR));
    }

    public function down(): void
    {
        // Data reconciliation is intentionally not reversed by a schema rollback.
        // Private backups retain the exact pre-merge rows and references.
    }
};
