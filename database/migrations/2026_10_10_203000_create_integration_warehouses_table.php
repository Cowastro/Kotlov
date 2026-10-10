<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['integration_source_id', 'external_id'],
                'integration_warehouses_source_external_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_warehouses');
    }
};
