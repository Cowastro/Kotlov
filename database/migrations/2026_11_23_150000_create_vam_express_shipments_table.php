<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vam_express_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('remote_id', 32)->unique();
            $table->string('display_id', 32)->index();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->text('city')->nullable();
            $table->text('cargo')->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->decimal('delivery_cost', 14, 2)->nullable();
            $table->date('remote_created_at')->nullable()->index();
            $table->date('accepted_at')->nullable();
            $table->date('dispatch_at')->nullable();
            $table->string('carrier_status')->nullable()->index();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vam_express_shipments');
    }
};
