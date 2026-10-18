<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_products', function (Blueprint $table): void {
            $table->boolean('b2b_enabled')
                ->nullable()
                ->after('match_status')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('integration_products', function (Blueprint $table): void {
            $table->dropIndex(['b2b_enabled']);
            $table->dropColumn('b2b_enabled');
        });
    }
};
