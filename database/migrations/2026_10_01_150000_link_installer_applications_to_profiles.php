<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installer_applications', function (Blueprint $table) {
            $table->foreignId('installer_profile_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('installer_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('installer_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installer_profile_id');
        });
    }
};
