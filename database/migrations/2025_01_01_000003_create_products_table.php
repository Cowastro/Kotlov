<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->index();
            $table->unsignedBigInteger('brand_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();

            // Основные поля
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('h1')->nullable();
            $table->string('sku')->nullable();

            // Цены
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('price_old', 10, 2)->nullable();
            $table->string('currency', 3)->default('BYN');

            // Контент
            $table->longText('content')->nullable();
            $table->text('short_description')->nullable();
            $table->json('images')->nullable();
            $table->json('specs')->nullable();
            $table->string('video_url')->nullable();

            // Физические характеристики
            $table->float('weight')->nullable();          // кг
            $table->string('unit')->default('шт');        // шт/компл/м
            $table->string('warranty')->nullable();       // "2 года"

            // Статусы
            $table->boolean('is_active')->default(true);
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_qty')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_sale')->default(false);
            $table->integer('sort_order')->default(0);

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();

            // Статистика
            $table->float('rating')->default(0);
            $table->integer('reviews_count')->default(0);
            $table->integer('views_count')->default(0);

            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
