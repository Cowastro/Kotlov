<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_selections', function (Blueprint $table) {
            // Availability snapshots for 150–2000 rows exceed MySQL TEXT's 64 KiB.
            $table->longText('meta')->nullable()->change();
            $table->unsignedBigInteger('legacy_saved_cart_id')->nullable();
            $table->unique(['user_id', 'legacy_saved_cart_id'], 'selection_legacy_owner_unique');
        });
    }

    public function down(): void
    {
        // Keep LONGTEXT on rollback: shrinking it could destroy saved allocations.
        Schema::table('customer_selections', function (Blueprint $table) {
            $table->dropUnique('selection_legacy_owner_unique');
            $table->dropColumn('legacy_saved_cart_id');
        });
    }
};
