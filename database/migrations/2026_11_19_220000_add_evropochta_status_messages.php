<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('evropochta_status_sms_started_at')->nullable();
            $table->timestamp('evropochta_status_sms_polled_at')->nullable()->index();
        });

        Schema::create('evropochta_status_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number', 14);
            $table->string('carrier_event', 64);
            $table->string('carrier_status');
            $table->string('sms_status', 24)->default('pending')->index();
            $table->string('sms_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'tracking_number', 'carrier_event'], 'evropochta_status_message_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evropochta_status_messages');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['evropochta_status_sms_polled_at']);
            $table->dropColumn(['evropochta_status_sms_started_at', 'evropochta_status_sms_polled_at']);
        });
    }
};
