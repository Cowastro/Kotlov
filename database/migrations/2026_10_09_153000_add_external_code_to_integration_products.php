<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_products', function (Blueprint $table) {
            $table->string('external_code')->nullable()->index()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('integration_products', function (Blueprint $table) {
            $table->dropColumn('external_code');
        });
    }
};
