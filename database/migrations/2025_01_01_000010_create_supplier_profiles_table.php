<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->string('company_name');
            $table->string('company_logo')->nullable();
            $table->string('legal_address')->nullable();
            $table->string('inn')->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->float('rating')->default(0);
            $table->integer('products_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_profiles');
    }
};
