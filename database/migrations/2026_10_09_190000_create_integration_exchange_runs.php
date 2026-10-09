<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_exchange_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_source_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16)->index();
            $table->string('operation', 32)->index();
            $table->string('status', 24)->default('running')->index();
            $table->string('session_key', 64)->nullable()->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('files_count')->default(0);
            $table->unsignedBigInteger('bytes_received')->default(0);
            $table->unsignedInteger('items_received')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_exchange_runs');
    }
};
