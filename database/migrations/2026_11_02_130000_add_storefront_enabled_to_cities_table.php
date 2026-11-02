<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cities') || Schema::hasColumn('cities', 'storefront_enabled')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table): void {
            $table->boolean('storefront_enabled')
                ->default(false)
                ->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cities') || ! Schema::hasColumn('cities', 'storefront_enabled')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table): void {
            $table->dropColumn('storefront_enabled');
        });
    }
};
