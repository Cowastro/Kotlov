<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('onec_external_id')->nullable()->index()->after('onec_exported_at');
            $table->string('onec_status')->nullable()->after('onec_external_id');
            $table->timestamp('onec_status_received_at')->nullable()->index()->after('onec_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['onec_status_received_at']);
            $table->dropIndex(['onec_external_id']);
            $table->dropColumn(['onec_external_id', 'onec_status', 'onec_status_received_at']);
        });
    }
};
