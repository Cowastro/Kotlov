<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Индексы для ускорения работы в админке:
 *  - products: сортировка, фильтрация, поиск
 *  - orders: фильтрация по статусу, дате, ответственному
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('is_archived',  'idx_products_archived');
            $table->index('is_active',    'idx_products_active');
            $table->index('sort_order',   'idx_products_sort');
            $table->index('in_stock',     'idx_products_stock');
            $table->index('is_featured',  'idx_products_featured');
            $table->index('created_at',   'idx_products_created');
            // Составной индекс для основного запроса списка (не архив + активные)
            $table->index(['is_archived', 'is_active'], 'idx_products_archived_active');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status',     'idx_orders_status');
            $table->index('created_at', 'idx_orders_created');
            // Составной: фильтр по статусу + сортировка по дате
            $table->index(['status', 'created_at'], 'idx_orders_status_created');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_archived');
            $table->dropIndex('idx_products_active');
            $table->dropIndex('idx_products_sort');
            $table->dropIndex('idx_products_stock');
            $table->dropIndex('idx_products_featured');
            $table->dropIndex('idx_products_created');
            $table->dropIndex('idx_products_archived_active');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_created');
            $table->dropIndex('idx_orders_status_created');
        });
    }
};
