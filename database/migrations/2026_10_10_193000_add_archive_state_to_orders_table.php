<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index()->after('onec_status_received_at');
            $table->foreignId('archived_by')->nullable()->after('archived_at')
                ->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable()->after('archived_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['archived_by']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'archived_by', 'archive_reason']);
        });
    }
};
