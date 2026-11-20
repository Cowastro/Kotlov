<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A request for installation is offered to the matching installers by e-mail; the first three who take it
 * get the client's contacts. One row = one installer offered one request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_request_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('install_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installer_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();

            $table->unique(['install_request_id', 'installer_profile_id']);
            $table->index(['install_request_id', 'taken_at']);
        });

        Schema::table('install_requests', function (Blueprint $table) {
            $table->timestamp('leads_sent_at')->nullable()->after('telegram_notified_at');
        });

        Schema::table('installer_profiles', function (Blueprint $table) {
            $table->boolean('lead_notifications')->default(true)->after('status');
        });

        // Requests that came before the mailing existed are not offered to anyone.
        DB::table('install_requests')->whereNull('leads_sent_at')->update(['leads_sent_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('installer_profiles', function (Blueprint $table) {
            $table->dropColumn('lead_notifications');
        });
        Schema::table('install_requests', function (Blueprint $table) {
            $table->dropColumn('leads_sent_at');
        });
        Schema::dropIfExists('install_request_leads');
    }
};
