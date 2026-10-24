<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chimney_calculation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status')->default('pending')->index();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->text('configuration')->nullable();
            $table->json('items');
            $table->unsignedBigInteger('equipment_product_id')->nullable()->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('promotion_mode')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamp('telegram_notified_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by_name')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->foreign('equipment_product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chimney_calculation_requests');
    }
};
