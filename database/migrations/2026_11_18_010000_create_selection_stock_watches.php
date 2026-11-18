<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_stock_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_selection_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true)->index();
            $table->longText('baseline')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('selection_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selection_stock_watch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->longText('items');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['selection_stock_watch_id', 'sequence'], 'selection_stock_alert_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selection_stock_alerts');
        Schema::dropIfExists('selection_stock_watches');
    }
};
