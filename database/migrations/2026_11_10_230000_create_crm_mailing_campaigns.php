<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Viber/SMS mailings through SMS.BY: a campaign sent to a mailing list, one row per recipient phone with the provider
 * message id and delivery status, and the phones that answered «СТОП».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mailing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailing_list_id')->constrained('crm_mailing_lists')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel', 20)->default('viber_sms');
            $table->text('text');
            $table->string('sms_text', 1000)->nullable();
            $table->string('button_caption', 30)->nullable();
            $table->string('button_link', 150)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('crm_mailing_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crm_mailing_campaigns')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('crm_mailing_list_members')->nullOnDelete();
            $table->string('phone', 20);
            $table->string('channel', 10)->nullable();
            $table->string('provider_message_id', 40)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('provider_status', 40)->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'phone']);
            $table->index(['status', 'sent_at']);
            $table->index('provider_message_id');
        });

        Schema::create('crm_mailing_optouts', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('source', 20)->default('manual');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mailing_optouts');
        Schema::dropIfExists('crm_mailing_messages');
        Schema::dropIfExists('crm_mailing_campaigns');
    }
};
