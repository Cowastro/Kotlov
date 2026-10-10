<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_channel_transitions', function (Blueprint $table): void {
            $table->foreignId('confirmed_by')->nullable()->after('ready_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            $table->foreignId('rolled_back_by')->nullable()->after('legacy_disabled_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable()->after('rolled_back_by');
            $table->text('rollback_reason')->nullable()->after('rolled_back_at');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_channel_transitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropConstrainedForeignId('rolled_back_by');
            $table->dropColumn(['confirmed_at', 'rolled_back_at', 'rollback_reason']);
        });
    }
};
