<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installer_profiles', function (Blueprint $table) {
            $table->timestamp('priority_until')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('installer_profiles', function (Blueprint $table) {
            $table->dropIndex(['priority_until']);
            $table->dropColumn('priority_until');
        });
    }
};
