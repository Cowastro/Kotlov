<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The 1C order the site found for a site order (entered in 1C by hand), refreshed after each read of 1C orders. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('onec_match_guid', 36)->nullable()->after('onec_checked_at');
            $table->string('onec_match_number', 50)->nullable()->after('onec_match_guid');
            $table->string('onec_match_reasons', 255)->nullable()->after('onec_match_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['onec_match_guid', 'onec_match_number', 'onec_match_reasons']);
        });
    }
};
