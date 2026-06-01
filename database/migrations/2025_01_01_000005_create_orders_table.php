<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('number')->unique();

            $table->enum('status', [
                'new', 'confirmed', 'processing',
                'shipped', 'delivered', 'cancelled'
            ])->default('new');

            // Контакты
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();

            // Доставка
            $table->enum('delivery_type', ['pickup', 'courier', 'transport'])->default('courier');
            $table->string('delivery_region')->nullable();
            $table->string('delivery_city')->nullable();
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_price', 10, 2)->default(0);

            // Оплата
            $table->enum('payment_type', ['cash', 'card', 'invoice'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');

            // Промокод и скидка
            $table->string('coupon_code')->nullable();
            $table->decimal('discount', 10, 2)->default(0);

            // Суммы
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Комментарии
            $table->text('comment')->nullable();
            $table->text('admin_comment')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
