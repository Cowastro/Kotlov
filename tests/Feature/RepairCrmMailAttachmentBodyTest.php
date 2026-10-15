<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Services\CrmMail\CrmMailMessagePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairCrmMailAttachmentBodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_is_explicit_reversible_and_preserves_attachments_and_read_state(): void
    {
        $message = $this->message();
        $original = $message->text_body;
        $attachments = $message->metadata['attachments'];

        $this->artisan('crm-mail:repair-attachment-body', ['message' => $message->id])
            ->expectsOutput('eligible=1')->expectsOutput('repaired=0')->assertSuccessful();
        $this->assertSame($original, $message->fresh()->text_body);

        $this->artisan('crm-mail:repair-attachment-body', ['message' => $message->id, '--apply' => true])
            ->expectsOutput('repaired=1')->assertSuccessful();
        $message->refresh();
        $this->assertNull($message->text_body);
        $this->assertSame($original, $message->metadata['attachment_body_repair']['original_text_body']);
        $this->assertSame($attachments, $message->metadata['attachments']);
        $this->assertSame(1, $message->attachments_count);
        $this->assertTrue($message->is_read);
        $this->assertFalse(app(CrmMailMessagePreview::class)->hasVisibleContent($message));

        $this->artisan('crm-mail:repair-attachment-body', ['message' => $message->id, '--apply' => true])
            ->expectsOutput('eligible=0')->expectsOutput('repaired=0')->assertSuccessful();
        $this->assertSame($original, $message->fresh()->metadata['attachment_body_repair']['original_text_body']);
    }

    public function test_repair_does_not_remove_real_text_or_unrecoverable_attachment_data(): void
    {
        $message = $this->message();
        $message->text_body = "Добрый день, прошу подобрать насос.\n".$message->text_body;
        $message->save();
        $this->artisan('crm-mail:repair-attachment-body', ['message' => $message->id, '--apply' => true])
            ->expectsOutput('eligible=0')->assertSuccessful();
        $this->assertStringContainsString('Добрый день', $message->fresh()->text_body);

        $message = $this->message();
        $message->metadata = ['attachments' => []];
        $message->save();
        $this->artisan('crm-mail:repair-attachment-body', ['message' => $message->id, '--apply' => true])
            ->expectsOutput('eligible=0')->assertSuccessful();
        $this->assertNotNull($message->fresh()->text_body);
    }

    private function message(): CrmMailMessage
    {
        $mailbox = CrmMailbox::firstOrCreate(['email' => 'mime-test@example.com'], ['name' => 'Test', 'username' => 'mime-test@example.com']);
        $thread = CrmMailThread::create(['mailbox_id' => $mailbox->id, 'thread_key' => uniqid('mime'), 'subject' => 'насос', 'participant_email' => 'mime-sender@example.com']);

        return $thread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'mime-sender@example.com',
            'subject' => 'насос',
            'text_body' => "--=_boundary\r\nContent-Transfer-Encoding: base64\r\nContent-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document;\r\nContent-Disposition: attachment; filename=test.docx\r\n\r\nUEsDBA==\r\n--=_boundary--",
            'is_read' => true,
            'attachments_count' => 1,
            'metadata' => ['attachments' => [['name' => 'test.docx', 'path' => 'crm-mail-attachments/test.docx', 'stored' => true]]],
        ]);
    }
}
