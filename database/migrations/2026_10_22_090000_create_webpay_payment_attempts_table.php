<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webpay_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_order_num', 64)->unique();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('BYN');
            $table->string('environment', 16)->default('sandbox');
            $table->string('status', 24)->default('pending')->index();
            $table->string('seed', 64);
            $table->string('transaction_id', 100)->nullable()->unique();
            $table->string('webpay_order_id', 100)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_type', 16)->nullable();
            $table->string('rrn', 100)->nullable();
            $table->json('notification_payload')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webpay_payment_attempts');
    }
};
