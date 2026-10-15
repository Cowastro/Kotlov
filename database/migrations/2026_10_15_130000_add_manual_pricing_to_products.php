<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('manual_purchase_price_byn', 12, 2)->nullable()->after('price_old');
            $table->foreignId('manual_price_updated_by')->nullable()->after('manual_purchase_price_byn')->constrained('users')->nullOnDelete();
            $table->timestamp('manual_price_updated_at')->nullable()->after('manual_price_updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manual_price_updated_by');
            $table->dropColumn(['manual_purchase_price_byn', 'manual_price_updated_at']);
        });
    }
};
