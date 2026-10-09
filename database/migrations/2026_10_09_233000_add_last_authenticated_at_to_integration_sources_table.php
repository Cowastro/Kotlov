<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_sources', function (Blueprint $table): void {
            $table->timestamp('last_authenticated_at')->nullable()->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('integration_sources', function (Blueprint $table): void {
            $table->dropColumn('last_authenticated_at');
        });
    }
};
