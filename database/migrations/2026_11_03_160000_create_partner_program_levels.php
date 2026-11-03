<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_program_levels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('rank')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('benefits');
            $table->json('rules');
            $table->timestamps();
        });

        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->foreignId('partner_program_level_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('b2b_clients', fn (Blueprint $table) => $table->dropConstrainedForeignId('partner_program_level_id'));
        Schema::dropIfExists('partner_program_levels');
    }
};
