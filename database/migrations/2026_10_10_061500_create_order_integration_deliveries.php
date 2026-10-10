<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_integration_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_source_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('external_id')->nullable()->index();
            $table->string('remote_status')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('exported_at')->nullable()->index();
            $table->timestamp('status_received_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'integration_source_id'], 'order_source_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_integration_deliveries');
    }
};
