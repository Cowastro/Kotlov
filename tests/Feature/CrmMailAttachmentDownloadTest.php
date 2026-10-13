<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmMailAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_download_a_private_incoming_mail_attachment(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $message = $this->messageWithAttachment('crm-mail-attachments/incoming/1/100/01-order.pdf');
        Storage::disk('local')->put($message->metadata['attachments'][0]['path'], 'PDF-CONTENT');

        $response = $this->actingAs($manager)->get(route('admin.crm-mail-messages.attachments.download', [
            'message' => $message,
            'attachment' => 0,
        ]));

        $response->assertOk();
        $this->assertSame('PDF-CONTENT', $response->streamedContent());
    }

    public function test_customer_cannot_download_crm_mail_attachments(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $message = $this->messageWithAttachment('crm-mail-attachments/incoming/1/100/01-order.pdf');
        Storage::disk('local')->put($message->metadata['attachments'][0]['path'], 'PDF-CONTENT');

        $this->actingAs($customer)->get(route('admin.crm-mail-messages.attachments.download', [
            'message' => $message,
            'attachment' => 0,
        ]))->assertForbidden();
    }

    private function messageWithAttachment(string $path): CrmMailMessage
    {
        $mailbox = CrmMailbox::create([
            'name' => 'Attachment test',
            'email' => 'attachments-test@kotlov.by',
            'username' => 'attachments-test@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', uniqid('attachment-test-', true)),
            'participant_email' => 'sender@example.com',
            'status' => 'new',
        ]);

        return CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'sender@example.com',
            'attachments_count' => 1,
            'metadata' => [
                'attachments' => [[
                    'name' => 'order.pdf',
                    'path' => $path,
                    'mime_type' => 'application/pdf',
                    'size' => 11,
                    'stored' => true,
                ]],
            ],
        ]);
    }
}
