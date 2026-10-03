<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('install_requests', function (Blueprint $table) {
            $table->json('project_details')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('install_requests', function (Blueprint $table) {
            $table->dropColumn('project_details');
        });
    }
};
