<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['order_id', 'reviewable_type', 'reviewable_id'], 'reviews_order_target_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique('reviews_order_target_unique');
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
