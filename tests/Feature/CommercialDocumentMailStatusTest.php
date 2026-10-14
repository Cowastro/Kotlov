<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Services\CrmMail\CrmMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDocumentMailStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_thread_marks_only_draft_commercial_documents_as_sent(): void
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'documents-status@kotlov.by',
            'username' => 'documents-status@kotlov.by',
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => 'commercial-mail-status',
            'subject' => 'Коммерческие документы',
            'participant_email' => 'client@example.com',
        ]);
        $draft = CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'draft', 'crm_mail_thread_id' => $thread->id,
        ]);
        $accepted = CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'accepted', 'crm_mail_thread_id' => $thread->id,
            'accepted_at' => now()->subDay(),
        ]);
        $rejected = CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'rejected', 'crm_mail_thread_id' => $thread->id,
            'outcome_reason' => 'price', 'closed_at' => now()->subDay(),
        ]);

        $updated = app(CrmMailSender::class)->markCommercialDocumentsSent($thread);

        $this->assertSame(1, $updated);
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->sent_at);
        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertNotNull($accepted->fresh()->accepted_at);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('price', $rejected->fresh()->outcome_reason);
    }
}
