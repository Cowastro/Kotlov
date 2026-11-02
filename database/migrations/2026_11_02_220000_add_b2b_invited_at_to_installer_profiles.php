<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installer_profiles', function (Blueprint $table): void {
            $table->timestamp('b2b_invited_at')->nullable()->after('orders_count');
        });
    }

    public function down(): void
    {
        Schema::table('installer_profiles', function (Blueprint $table): void {
            $table->dropColumn('b2b_invited_at');
        });
    }
};
