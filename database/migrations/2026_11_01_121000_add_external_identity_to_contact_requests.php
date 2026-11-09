<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->string('external_source')->nullable()->after('source');
            $table->string('external_id')->nullable()->after('external_source');
            $table->string('last_external_event_id')->nullable()->after('external_id');
            $table->unique(['external_source', 'external_id'], 'contact_requests_external_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->dropUnique('contact_requests_external_identity_unique');
            $table->dropColumn(['external_source', 'external_id', 'last_external_event_id']);
        });
    }
};
