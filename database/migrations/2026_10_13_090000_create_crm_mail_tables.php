<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username');
            $table->text('password')->nullable();
            $table->string('imap_host')->default('imap.hoster.by');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl');
            $table->string('imap_folder')->default('INBOX');
            $table->string('smtp_host')->default('smtp.hoster.by');
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->string('smtp_encryption')->default('ssl');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('create_leads')->default(true);
            $table->unsignedBigInteger('last_uid')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_mail_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained('crm_mailboxes')->cascadeOnDelete();
            $table->foreignId('crm_counterparty_id')->nullable()->constrained('crm_counterparties')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('thread_key', 64);
            $table->string('subject')->nullable();
            $table->string('participant_name')->nullable();
            $table->string('participant_email')->index();
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');
            $table->unsignedInteger('messages_count')->default(0);
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamps();

            $table->unique(['mailbox_id', 'thread_key']);
            $table->index(['status', 'last_message_at']);
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('crm_mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('crm_mail_threads')->cascadeOnDelete();
            $table->foreignId('mailbox_id')->constrained('crm_mailboxes')->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('direction')->default('incoming');
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->string('subject')->nullable();
            $table->longText('text_body')->nullable();
            $table->longText('html_body')->nullable();
            $table->unsignedInteger('attachments_count')->default(0);
            $table->boolean('is_read')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['mailbox_id', 'message_id']);
            $table->unique(['mailbox_id', 'imap_uid']);
            $table->index(['thread_id', 'received_at']);
        });

        DB::table('crm_mailboxes')->insert([
            'name' => 'KOTLOV',
            'email' => 'info@kotlov.by',
            'username' => 'info@kotlov.by',
            'imap_host' => 'imap.hoster.by',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.hoster.by',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'is_enabled' => false,
            'create_leads' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mail_messages');
        Schema::dropIfExists('crm_mail_threads');
        Schema::dropIfExists('crm_mailboxes');
    }
};
