<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->index(['city_id', 'is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropIndex(['city_id', 'is_published', 'published_at']);
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
