<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailboxes\CrmMailboxResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\LeadMatcher;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CrmMailModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_matched_to_existing_crm_contact(): void
    {
        $counterparty = CrmCounterparty::create(['name' => 'ООО Клиент']);
        $contact = CrmContact::create(['counterparty_id' => $counterparty->id, 'full_name' => 'Иван']);
        CrmContactPoint::create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'Sales@Example.com',
            'is_active' => true,
        ]);

        [$matchedCounterparty, $matchedContact] = app(LeadMatcher::class)->resolve('sales@example.com', null, true);

        $this->assertTrue($counterparty->is($matchedCounterparty));
        $this->assertTrue($contact->is($matchedContact));
        $this->assertSame(1, CrmCounterparty::count());
    }

    public function test_unknown_email_creates_unclassified_retail_lead_once(): void
    {
        [$counterparty, $contact] = app(LeadMatcher::class)->resolve('new.client@example.com', 'Новый клиент', true);
        [$sameCounterparty] = app(LeadMatcher::class)->resolve('NEW.CLIENT@example.com', null, true);

        $this->assertSame($counterparty->id, $sameCounterparty->id);
        $this->assertSame('lead', $counterparty->status);
        $this->assertSame('unclassified', $counterparty->classification);
        $this->assertSame('retail', $counterparty->business_unit);
        $this->assertSame('email', $counterparty->source);
        $this->assertSame($counterparty->id, $contact->counterparty_id);
        $this->assertSame(1, CrmCounterparty::count());
    }

    public function test_mailbox_password_is_encrypted_and_settings_are_admin_only(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'test-mailbox@kotlov.by',
            'username' => 'test-mailbox@kotlov.by',
            'password' => 'not-plain-text',
        ]);
        $stored = DB::table('crm_mailboxes')->where('id', $mailbox->id)->value('password');

        $this->assertNotSame('not-plain-text', $stored);
        $this->assertSame('not-plain-text', $mailbox->fresh()->password);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);

        $this->assertTrue(CrmMailThreadResource::canViewAny());
        $this->assertFalse(CrmMailboxResource::canViewAny());
    }

    public function test_taking_mail_lead_assigns_unowned_client_to_same_manager(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::create([
            'name' => 'Лид из почты',
            'status' => 'lead',
            'source' => 'email',
        ]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'leads@kotlov.by',
            'username' => 'leads@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'crm_counterparty_id' => $counterparty->id,
            'thread_key' => hash('sha256', 'lead@example.com|request'),
            'participant_email' => 'lead@example.com',
        ]);

        $thread->takeFor($manager->id);

        $this->assertSame($manager->id, $thread->fresh()->assigned_user_id);
        $this->assertSame('in_progress', $thread->fresh()->status);
        $this->assertSame($manager->id, $counterparty->fresh()->responsible_user_id);
    }

    public function test_taking_mail_lead_does_not_replace_existing_client_owner(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $otherManager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::create([
            'name' => 'Действующий клиент',
            'responsible_user_id' => $owner->id,
        ]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'crm@kotlov.by',
            'username' => 'crm@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'crm_counterparty_id' => $counterparty->id,
            'thread_key' => hash('sha256', 'client@example.com|request'),
            'participant_email' => 'client@example.com',
        ]);

        $thread->takeFor($otherManager->id);

        $this->assertSame($otherManager->id, $thread->fresh()->assigned_user_id);
        $this->assertSame($owner->id, $counterparty->fresh()->responsible_user_id);
    }

    public function test_mail_thread_can_be_moved_to_trash_and_restored(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'trash@kotlov.by',
            'username' => 'trash@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'trash@example.com|request'),
            'participant_email' => 'trash@example.com',
        ]);

        $thread->delete();

        $this->assertNull(CrmMailThread::find($thread->id));
        $this->assertNotNull(CrmMailThread::onlyTrashed()->find($thread->id));

        $thread->restore();

        $this->assertNotNull(CrmMailThread::find($thread->id));
    }

    public function test_mail_thread_can_be_marked_as_spam_and_returned_to_work(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'spam@kotlov.by',
            'username' => 'spam@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'spam@example.com|advertising'),
            'participant_email' => 'spam@example.com',
            'status' => 'new',
            'unread_count' => 2,
        ]);
        $newerThread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'spam@example.com|another advertising subject'),
            'participant_email' => 'spam@example.com',
            'status' => 'new',
            'unread_count' => 1,
        ]);

        $thread->markAsSpam();

        $this->assertSame('spam', $thread->fresh()->status);
        $this->assertSame(0, $thread->fresh()->unread_count);
        $this->assertNotNull($thread->fresh()->handled_at);
        $this->assertSame('spam', $newerThread->fresh()->status);
        $this->assertSame(0, $newerThread->fresh()->unread_count);
        $this->assertTrue(CrmMailThread::senderIsBlocked($mailbox->id, 'SPAM@example.com'));

        $thread->restoreFromSpam();

        $this->assertSame('waiting_manager', $thread->fresh()->status);
        $this->assertNull($thread->fresh()->handled_at);
        $this->assertSame('waiting_manager', $newerThread->fresh()->status);
        $this->assertFalse(CrmMailThread::senderIsBlocked($mailbox->id, 'spam@example.com'));
    }

    public function test_incoming_activity_cannot_restore_a_spam_thread_to_work(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'blocked-sender@kotlov.by',
            'username' => 'blocked-sender@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'spam@example.com|advertising'),
            'participant_email' => 'spam@example.com',
            'status' => 'spam',
            'unread_count' => 0,
        ]);

        $thread->markCustomerReplied();

        $this->assertSame('spam', $thread->fresh()->status);
        $this->assertSame(0, $thread->fresh()->unread_count);
    }

    public function test_branded_reply_contains_manager_signature_and_safe_customer_text(): void
    {
        $manager = User::factory()->create([
            'name' => 'Александр Менеджер',
            'first_name' => 'Александр',
            'last_name' => 'Менеджер',
            'job_title' => 'Менеджер по продажам',
            'phone' => '+375 (29) 000-00-00',
        ]);
        $sender = app(CrmMailSender::class);

        $html = $sender->brandedHtml('Здравствуйте! <script>alert(1)</script>', $manager);
        $text = $sender->brandedText('Здравствуйте!', $manager);

        $this->assertStringContainsString('KOTLOV', $html);
        $this->assertStringContainsString('Менеджер Александр', $html);
        $this->assertStringContainsString('Менеджер по продажам', $html);
        $this->assertStringContainsString('+375 (29) 000-00-00', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('ООО «СанБизнесГруп»', $html);
        $this->assertStringContainsString('220075, г. Минск, ул. Селицкого, д. 39Б', $html);
        $this->assertStringNotContainsString('ООО «СБГ бай»', $html);
        $this->assertStringContainsString('Команда KOTLOV.BY', $text);
        $this->assertStringContainsString('С уважением, Менеджер Александр', $text);
        $this->assertStringContainsString('Менеджер по продажам', $text);
        $this->assertStringContainsString('+375 (29) 000-00-00', $text);
        $this->assertStringContainsString('info@kotlov.by', $text);
    }

    public function test_supplier_mail_draft_can_be_edited_without_sending(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'supplier-drafts@kotlov.by',
            'username' => 'supplier-drafts@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'supplier-draft-test'),
            'participant_name' => 'Поставщик',
            'participant_email' => '',
            'subject' => 'Исходная тема',
            'status' => 'draft',
            'messages_count' => 1,
        ]);
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'direction' => 'draft',
            'from_email' => $mailbox->email,
            'subject' => 'Исходная тема',
            'text_body' => 'Исходный текст',
            'is_read' => true,
        ]);

        app(SupplierRequestMailDraftService::class)->update(
            $thread,
            ['orders@supplier.test', 'director@supplier.test'],
            'Новая тема',
            'Новый текст письма',
            $manager,
            ['accounting@supplier.test'],
            ['crm-mail-attachments/offer.pdf'],
            ['crm-mail-attachments/offer.pdf' => 'Коммерческое предложение.pdf'],
        );

        $this->assertDatabaseHas('crm_mail_threads', [
            'id' => $thread->id,
            'participant_email' => 'orders@supplier.test',
            'subject' => 'Новая тема',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('crm_mail_messages', [
            'thread_id' => $thread->id,
            'direction' => 'draft',
            'text_body' => 'Новый текст письма',
            'attachments_count' => 1,
        ]);

        $message = $thread->messages()->where('direction', 'draft')->firstOrFail();
        $this->assertSame(['orders@supplier.test', 'director@supplier.test'], collect($message->to)->pluck('email')->all());
        $this->assertSame(['accounting@supplier.test'], collect($message->cc)->pluck('email')->all());
        $this->assertSame('Коммерческое предложение.pdf', $message->metadata['attachments'][0]['name']);
    }

    public function test_new_message_is_sent_immediately_by_default(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'compose-send@kotlov.by',
            'username' => 'compose-send@kotlov.by',
            'is_enabled' => true,
        ]);
        $sender = Mockery::mock(CrmMailSender::class);
        $sender->shouldReceive('sendDraft')
            ->once()
            ->withArgs(fn (CrmMailThread $thread, User $user): bool => $thread->status === 'draft'
                && $thread->participant_email === 'client@example.com'
                && $user->is($manager))
            ->andReturnUsing(fn (CrmMailThread $thread) => $thread->messages()->where('direction', 'draft')->firstOrFail());
        $this->app->instance(CrmMailSender::class, $sender);

        Livewire::actingAs($manager)
            ->test(ListCrmMailThreads::class)
            ->assertActionExists('compose', function ($action): bool {
                $footerActions = $action->getModalFooterActions();

                return $action->getModalSubmitActionLabel() === 'Отправить письмо'
                    && isset($footerActions['saveDraft'])
                    && $footerActions['saveDraft']->getLabel() === 'Сохранить в черновики';
            })
            ->callAction('compose', [
                'mailbox_id' => $mailbox->id,
                'participant_name' => 'Новый клиент',
                'recipients' => ['client@example.com'],
                'cc' => ['copy@example.com'],
                'subject' => 'Коммерческое предложение',
                'body' => 'Здравствуйте! Направляем предложение.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('crm_mail_threads', [
            'mailbox_id' => $mailbox->id,
            'participant_email' => 'client@example.com',
            'subject' => 'Коммерческое предложение',
        ]);
    }

    public function test_new_message_can_optionally_be_saved_as_draft(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'compose-draft@kotlov.by',
            'username' => 'compose-draft@kotlov.by',
            'is_enabled' => true,
        ]);
        $sender = Mockery::mock(CrmMailSender::class);
        $sender->shouldNotReceive('sendDraft');
        $this->app->instance(CrmMailSender::class, $sender);

        Livewire::actingAs($manager)
            ->test(ListCrmMailThreads::class)
            ->callAction('compose', [
                'mailbox_id' => $mailbox->id,
                'recipients' => ['later@example.com'],
                'subject' => 'Письмо на потом',
                'body' => 'Черновик письма.',
            ], ['save_as_draft' => true])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('crm_mail_threads', [
            'mailbox_id' => $mailbox->id,
            'participant_email' => 'later@example.com',
            'subject' => 'Письмо на потом',
            'status' => 'draft',
        ]);
    }
}
