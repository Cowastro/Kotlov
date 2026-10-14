<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use App\Services\CrmMail\CrmMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class CommercialDocumentMailAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_button_preserves_generated_pdf_attachment(): void
    {
        Storage::fake('local');

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'documents@kotlov.by',
            'username' => 'documents@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'is_enabled' => true,
        ]);

        $document = CommercialDocument::query()->create([
            'type' => 'quote',
            'client_name' => 'Анна',
            'client_email' => 'anna@example.com',
        ]);
        $document->items()->create([
            'name' => 'Насос',
            'quantity' => 1,
            'unit_price' => 350,
            'vat_rate' => 20,
        ]);

        $thread = app(CommercialDocumentWorkflow::class)->createMailDraft($document, $manager);
        $formData = CrmMailThreadResource::draftData($thread);
        $attachmentPath = $formData['attachments'][0];
        $attachmentName = $formData['attachment_file_names'][$attachmentPath];

        $observedAttachment = null;
        $sender = Mockery::mock(CrmMailSender::class);
        $sender->shouldReceive('sendDraft')
            ->once()
            ->andReturnUsing(function (CrmMailThread $updatedThread, User $user) use ($manager, &$observedAttachment): CrmMailMessage {
                $draft = $updatedThread->messages()->where('direction', 'draft')->firstOrFail();
                $observedAttachment = [
                    'user_matches' => $user->is($manager),
                    'count' => $draft->attachments_count,
                    'metadata' => $draft->metadata['attachments'] ?? [],
                ];

                return $draft;
            });
        $this->app->instance(CrmMailSender::class, $sender);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertActionExists('send_draft')
            ->assertActionVisible('send_draft')
            ->assertActionEnabled('send_draft')
            ->mountAction('send_draft')
            ->assertSchemaStateSet([
                'attachments' => [$attachmentPath],
                'attachment_file_names' => [$attachmentPath => $attachmentName],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([
            'user_matches' => true,
            'count' => 1,
            'metadata' => [[
                'path' => $attachmentPath,
                'name' => $attachmentName,
            ]],
        ], $observedAttachment);
        Storage::disk('local')->assertExists($attachmentPath);
    }

    public function test_mail_sender_builds_a_real_mime_pdf_attachment_from_draft_metadata(): void
    {
        Storage::fake('local');
        $path = 'crm-mail-attachments/commercial-documents/1/offer.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');

        $message = new CrmMailMessage([
            'metadata' => ['attachments' => [[
                'path' => $path,
                'name' => 'Коммерческое предложение.pdf',
            ]]],
        ]);
        $email = new Email;

        $method = new \ReflectionMethod(CrmMailSender::class, 'attachFiles');
        $method->invoke(app(CrmMailSender::class), $email, $message);

        $this->assertCount(1, $email->getAttachments());
        $this->assertSame(
            'Коммерческое предложение.pdf',
            $email->getAttachments()[0]->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'),
        );
    }
}
