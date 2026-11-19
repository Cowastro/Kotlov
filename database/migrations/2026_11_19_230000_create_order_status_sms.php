<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_sms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 32);
            $table->string('order_status', 32);
            $table->string('message', 255);
            $table->string('state', 20)->default('pending');
            $table->string('sms_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'event_key']);
            $table->index(['state', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_sms');
    }
};
