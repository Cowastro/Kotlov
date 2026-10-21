<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('availability_confirmed_at')->nullable()->after('payment_status');
            $table->unsignedBigInteger('availability_confirmed_by')->nullable()->after('availability_confirmed_at');
            $table->timestamp('customer_notified_at')->nullable()->after('availability_confirmed_by');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('new','checking','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'new'");

            DB::statement(<<<'SQL'
                UPDATE orders
                SET status = 'checking'
                WHERE status = 'confirmed'
                  AND (
                      NOT EXISTS (
                          SELECT 1
                          FROM order_items
                          WHERE order_items.order_id = orders.id
                      )
                      OR EXISTS (
                          SELECT 1
                          FROM order_items
                          WHERE order_items.order_id = orders.id
                            AND order_items.fulfillment_confirmed_at IS NULL
                      )
                  )
            SQL);
        }

        DB::table('orders')
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->whereNull('availability_confirmed_at')
            ->update(['availability_confirmed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("UPDATE orders SET status = 'new' WHERE status = 'checking'");
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('new','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'new'");
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'availability_confirmed_at',
                'availability_confirmed_by',
                'customer_notified_at',
            ]);
        });
    }
};
