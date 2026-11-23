<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vam_express_shipments', function (Blueprint $table) {
            $table->string('sender_profile', 255)->nullable()->index();
            $table->timestamp('profile_checked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('vam_express_shipments', fn (Blueprint $table) => $table->dropColumn(['sender_profile', 'profile_checked_at']));
    }
};
