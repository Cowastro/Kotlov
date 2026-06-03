<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installer_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installer_profile_id')
                ->constrained('installer_profiles')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('work_type')->nullable();
            // Варианты: heating, heatpump, fireplace, chimney, sauna, service, commissioning, other
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            // Регионы: Минск, Минская область, Гомельская область,
            //          Гродненская область, Брестская область,
            //          Витебская область, Могилёвская область
            $table->string('equipment_type')->nullable();
            $table->string('brand')->nullable();
            $table->json('photos')->nullable();
            $table->date('completed_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_works');
    }
};
