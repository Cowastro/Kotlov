<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0)->index();
            $table->string('name');
            $table->string('slug')->unique(); // alias из старой БД
            $table->string('h1')->nullable();
            $table->string('type')->default('main'); // main/child
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->longText('content')->nullable(); // SEO текст
            $table->string('meta_title')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon')->nullable(); // SVG иконка
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
