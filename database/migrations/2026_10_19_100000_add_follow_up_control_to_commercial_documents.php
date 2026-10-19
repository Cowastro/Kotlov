<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->string('follow_up_stage', 64)->nullable()->after('status')->index();
            $table->string('last_contact_result', 64)->nullable()->after('follow_up_stage');
            $table->timestamp('last_contacted_at')->nullable()->after('last_contact_result');
            $table->timestamp('next_follow_up_at')->nullable()->after('last_contacted_at')->index();
            $table->text('follow_up_note')->nullable()->after('next_follow_up_at');
            $table->json('contact_history')->nullable()->after('follow_up_note');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropColumn([
                'follow_up_stage', 'last_contact_result', 'last_contacted_at',
                'next_follow_up_at', 'follow_up_note', 'contact_history',
            ]);
        });
    }
};
