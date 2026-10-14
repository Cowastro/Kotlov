<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('popularity_score', 10, 4)->default(0)->index()->after('views_count');
            $table->timestamp('popularity_updated_at')->nullable()->after('popularity_score');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['popularity_score', 'popularity_updated_at']);
        });
    }
};
