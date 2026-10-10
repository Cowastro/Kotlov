<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_channel_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('previewed')->index();
            $table->json('snapshot');
            $table->foreignId('control_exchange_run_id')->nullable()
                ->constrained('integration_exchange_runs')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('legacy_disabled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'created_at']);
            $table->index(['integration_source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_channel_transitions');
    }
};
