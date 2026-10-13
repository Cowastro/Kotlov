<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table) {
            $table->string('sales_stage')->nullable()->after('status');
            $table->timestamp('next_follow_up_at')->nullable()->after('handled_at');
            $table->text('follow_up_note')->nullable()->after('next_follow_up_at');

            $table->index(['sales_stage', 'next_follow_up_at'], 'crm_mail_threads_sales_follow_up_index');
        });
    }

    public function down(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table) {
            $table->dropIndex('crm_mail_threads_sales_follow_up_index');
            $table->dropColumn(['sales_stage', 'next_follow_up_at', 'follow_up_note']);
        });
    }
};
