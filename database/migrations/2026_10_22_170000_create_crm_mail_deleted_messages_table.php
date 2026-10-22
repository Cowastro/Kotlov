<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mail_deleted_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_id')->constrained('crm_mailboxes')->cascadeOnDelete();
            $table->unsignedBigInteger('imap_uid');
            $table->string('message_id')->nullable();
            $table->timestamp('deleted_at');

            $table->unique(['mailbox_id', 'imap_uid']);
            $table->index('deleted_at');
        });

        Schema::create('crm_mail_blocked_senders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_id')->constrained('crm_mailboxes')->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('blocked_at');

            $table->unique(['mailbox_id', 'email']);
        });

        DB::table('crm_mail_threads')
            ->where('status', 'spam')
            ->whereNotNull('participant_email')
            ->select(['mailbox_id', 'participant_email'])
            ->distinct()
            ->orderBy('mailbox_id')
            ->each(function (object $thread): void {
                $email = mb_strtolower(trim((string) $thread->participant_email));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return;
                }

                DB::table('crm_mail_blocked_senders')->insertOrIgnore([
                    'mailbox_id' => $thread->mailbox_id,
                    'email' => $email,
                    'blocked_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mail_blocked_senders');
        Schema::dropIfExists('crm_mail_deleted_messages');
    }
};
