<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_integration_deliveries', function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable()->index()->after('remote_status');
            $table->foreignId('queued_by')->nullable()->after('queued_at')->constrained('users')->nullOnDelete();
            $table->string('remote_id')->nullable()->index()->after('queued_by');
            $table->string('remote_number')->nullable()->index()->after('remote_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('onec_order_guid')->nullable()->index()->after('onec_external_id');
            $table->string('onec_order_number')->nullable()->index()->after('onec_order_guid');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['onec_order_number']);
            $table->dropIndex(['onec_order_guid']);
            $table->dropColumn(['onec_order_guid', 'onec_order_number']);
        });

        Schema::table('order_integration_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['queued_by']);
            $table->dropIndex(['remote_number']);
            $table->dropIndex(['remote_id']);
            $table->dropIndex(['queued_at']);
            $table->dropColumn(['queued_at', 'queued_by', 'remote_id', 'remote_number']);
        });
    }
};
