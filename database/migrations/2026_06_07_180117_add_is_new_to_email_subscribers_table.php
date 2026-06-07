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
        Schema::table('email_subscribers', function (Blueprint $table) {
            $table->boolean('is_new')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('email_subscribers', function (Blueprint $table) {
            $table->dropColumn('is_new');
        });
    }
};
