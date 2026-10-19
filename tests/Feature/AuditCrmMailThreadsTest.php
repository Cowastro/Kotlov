<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCrmMailThreadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recovers_all_hidden_incoming_threads_and_reports_mixed_subjects(): void
    {
        $mailbox = $this->mailbox();
        $hidden = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => 'hidden-incoming',
            'subject' => 'Первый запрос',
            'participant_email' => 'buyer@example.com',
            'status' => 'in_progress',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        CrmMailMessage::query()->create([
            'thread_id' => $hidden->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'buyer@example.com',
            'subject' => 'Первый запрос',
            'received_at' => now()->subMinute(),
        ]);
        CrmMailMessage::query()->create([
            'thread_id' => $hidden->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'buyer@example.com',
            'subject' => 'Другая тема',
            'received_at' => now(),
        ]);

        $this->artisan('crm-mail:audit-threads', ['--apply' => true])
            ->expectsOutput('hidden_incoming=1')
            ->expectsOutput('mixed_subject_threads=1')
            ->expectsOutput('recovered_reactions=1')
            ->assertSuccessful();

        $hidden->refresh();
        $this->assertSame('waiting_manager', $hidden->status);
        $this->assertNull($hidden->handled_at);
    }

    public function test_reply_prefixes_are_not_reported_as_mixed_subjects(): void
    {
        $mailbox = $this->mailbox();
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => 'reply-prefixes',
            'subject' => 'Запрос цены',
            'participant_email' => 'reply@example.com',
            'status' => 'waiting_manager',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        foreach (['Запрос цены', 'Re: Запрос цены', 'Ответ: Re: Запрос цены'] as $index => $subject) {
            CrmMailMessage::query()->create([
                'thread_id' => $thread->id,
                'mailbox_id' => $mailbox->id,
                'direction' => $index === 1 ? 'outgoing' : 'incoming',
                'from_email' => $index === 1 ? 'info@kotlov.by' : 'reply@example.com',
                'subject' => $subject,
                'received_at' => now()->addSeconds($index),
            ]);
        }

        $this->artisan('crm-mail:audit-threads')
            ->expectsOutput('mixed_subject_threads=0')
            ->assertSuccessful();
    }

    private function mailbox(): CrmMailbox
    {
        return CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'audit-test@kotlov.by',
            'username' => 'audit-test@kotlov.by',
            'is_enabled' => true,
        ]);
    }
}
