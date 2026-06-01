<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('author_name'); // для незарегистрированных
            $table->string('author_email')->nullable();

            // Polymorphic — отзыв на товар или монтажника
            $table->morphs('reviewable'); // reviewable_type + reviewable_id

            $table->tinyInteger('rating'); // 1-5
            $table->text('text');
            $table->json('photos')->nullable();
            $table->boolean('is_approved')->default(false);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
