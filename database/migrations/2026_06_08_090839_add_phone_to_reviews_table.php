<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('author_phone', 50)->nullable()->after('author_email');
        });

        // Перенести телефоны из author_email в author_phone и очистить невалидные email
        DB::statement("
            UPDATE reviews
            SET author_phone = author_email, author_email = NULL
            WHERE author_email IS NOT NULL
              AND author_email NOT LIKE '%@%'
              AND author_email != ''
        ");
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('author_phone');
        });
    }
};
