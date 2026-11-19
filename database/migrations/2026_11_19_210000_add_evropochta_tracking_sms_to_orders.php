<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('evropochta_sms_status', 24)->nullable();
            $table->string('evropochta_sms_id')->nullable();
            $table->timestamp('evropochta_sms_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['evropochta_sms_status', 'evropochta_sms_id', 'evropochta_sms_sent_at']);
        });
    }
};
