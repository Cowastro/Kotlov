<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_source_pricing_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->json('changed_fields');
            $table->json('before_values');
            $table->json('after_values');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(
                ['integration_source_id', 'changed_at'],
                'integration_source_pricing_change_timeline',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_source_pricing_changes');
    }
};
