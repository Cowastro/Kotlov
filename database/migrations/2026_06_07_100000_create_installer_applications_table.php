<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installer_applications', function (Blueprint $table) {
            $table->id();
            $table->string('contact_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('company_name')->nullable();
            $table->integer('experience_years')->nullable();
            $table->json('specializations')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new, contacted, approved, rejected
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_applications');
    }
};
