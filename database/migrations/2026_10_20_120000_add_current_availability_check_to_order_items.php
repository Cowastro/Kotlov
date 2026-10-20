<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('current_availability')->nullable()->after('supply_captured_at');
            $table->timestamp('current_availability_checked_at')->nullable()->after('current_availability');
            $table->foreignId('current_availability_checked_by')
                ->nullable()
                ->after('current_availability_checked_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_availability_checked_by');
            $table->dropColumn(['current_availability', 'current_availability_checked_at']);
        });
    }
};
