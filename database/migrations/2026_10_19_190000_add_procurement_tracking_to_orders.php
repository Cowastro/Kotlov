<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('corrected_at')->nullable()->after('admin_comment');
            $table->foreignId('corrected_by')->nullable()->after('corrected_at')->constrained('users')->nullOnDelete();
            $table->timestamp('procurement_sent_at')->nullable()->after('corrected_by');
            $table->foreignId('procurement_sent_by')->nullable()->after('procurement_sent_at')->constrained('users')->nullOnDelete();
            $table->string('procurement_recipient')->nullable()->after('procurement_sent_by');
        });

        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->after('assigned_user_id')->constrained()->nullOnDelete();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'created_at']);
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropConstrainedForeignId('procurement_sent_by');
            $table->dropColumn(['corrected_at', 'procurement_sent_at', 'procurement_recipient']);
        });
    }
};
