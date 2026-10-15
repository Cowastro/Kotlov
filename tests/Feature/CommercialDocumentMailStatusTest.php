<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Services\CommercialDocuments\CommercialDocumentPortal;
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

    public function test_sending_new_revision_makes_previous_revision_non_actionable(): void
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV', 'email' => 'revision-status@kotlov.by', 'username' => 'revision-status@kotlov.by',
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => 'commercial-revision-status',
            'participant_email' => 'client@example.com',
        ]);
        $original = CommercialDocument::query()->create([
            'type' => 'quote', 'status' => 'sent', 'crm_mail_thread_id' => $thread->id,
            'client_email' => 'client@example.com',
        ]);
        $revision = CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'draft',
            'revision_of_id' => $original->id,
            'revision_number' => 2,
            'crm_mail_thread_id' => $thread->id,
        ]);
        $oldLink = app(CommercialDocumentPortal::class)->publish($original);
        $oldSession = $oldLink->sessions()->create([
            'token_hash' => hash('sha256', 'old-device'),
            'device_label' => 'Компьютер · Chrome',
            'authorized_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        app(CrmMailSender::class)->markCommercialDocumentsSent($thread);

        $this->assertSame('needs_revision', $original->fresh()->status);
        $this->assertSame('sent', $revision->fresh()->status);
        $this->assertStringContainsString($revision->number, $original->fresh()->outcome_note);
        $this->assertNotNull($oldLink->fresh()->revoked_at);
        $this->assertNotNull($oldSession->fresh()->revoked_at);
        $this->assertDatabaseHas('commercial_document_portal_events', [
            'commercial_document_id' => $original->id,
            'portal_link_id' => $oldLink->id,
            'type' => 'link_revoked',
        ]);
    }
}
