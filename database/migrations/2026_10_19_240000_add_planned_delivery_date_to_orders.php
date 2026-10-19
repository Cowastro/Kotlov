<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'planned_delivery_date')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->date('planned_delivery_date')->nullable()->after('delivery_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'planned_delivery_date')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('planned_delivery_date');
            });
        }
    }
};
