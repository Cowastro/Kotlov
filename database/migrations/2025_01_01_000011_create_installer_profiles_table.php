<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->string('photo')->nullable();
            $table->text('bio')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->json('work_regions')->nullable();
            $table->json('specializations')->nullable();
            $table->integer('experience_years')->default(0);
            $table->string('certificate_photo')->nullable();
            $table->decimal('price_from', 10, 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->float('rating')->default(0);
            $table->integer('reviews_count')->default(0);
            $table->integer('orders_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_profiles');
    }
};
