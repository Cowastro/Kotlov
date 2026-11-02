<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_contact_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->json('phones')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('map_url')->nullable();
            $table->text('map_embed_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_contact_profiles');
    }
};
