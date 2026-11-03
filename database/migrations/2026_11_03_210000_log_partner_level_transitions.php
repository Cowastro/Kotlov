<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_program_levels', function (Blueprint $table): void {
            $table->foreignId('b2b_price_level_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('auto_apply_price_level')->default(false);
        });

        Schema::create('partner_level_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->json('old_level')->nullable();
            $table->json('new_level');
            $table->json('metrics');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_level_transitions');
        Schema::table('partner_program_levels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('b2b_price_level_id');
            $table->dropColumn('auto_apply_price_level');
        });
    }
};
