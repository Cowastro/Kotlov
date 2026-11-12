<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->foreignId('source_thread_id')->nullable()->constrained('crm_mail_threads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_thread_id');
        });
    }
};
