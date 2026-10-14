<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailThreadResolver;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmMailCounterpartyThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_different_contact_emails_to_one_counterparty_thread(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $thread = $this->thread($mailbox, $counterparty, 'buyer@example.com');

        $resolved = app(CrmMailThreadResolver::class)->resolve(
            $mailbox,
            'accounting@example.com',
            $counterparty->id,
        );

        $this->assertTrue($resolved?->is($thread));
    }

    public function test_it_falls_back_to_email_when_counterparty_is_not_known(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $thread = $this->thread($mailbox, $counterparty, 'Buyer@Example.com');

        $resolved = app(CrmMailThreadResolver::class)->resolve($mailbox, 'buyer@example.com');

        $this->assertTrue($resolved?->is($thread));
    }

    public function test_manual_outgoing_mail_is_appended_to_existing_counterparty_thread(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Иван Покупатель',
            'is_primary' => true,
            'is_active' => true,
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'buyer@example.com',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $thread = $this->thread($mailbox, $counterparty, 'buyer@example.com');
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => 'request@example.com',
            'direction' => 'incoming',
            'from_email' => 'buyer@example.com',
            'subject' => 'Первый запрос',
            'is_read' => true,
            'received_at' => now()->subHour(),
        ]);
        $user = User::factory()->create();

        $result = app(SupplierRequestMailDraftService::class)->create(
            $mailbox,
            $user,
            'buyer@example.com',
            'Новая тема',
            'Ответ по новой теме',
        );

        $this->assertTrue($result->is($thread));
        $this->assertSame(1, CrmMailThread::query()->count());
        $this->assertSame(2, $thread->messages()->count());
        $this->assertDatabaseHas('crm_mail_messages', [
            'thread_id' => $thread->id,
            'direction' => 'draft',
            'subject' => 'Новая тема',
        ]);
    }

    /** @return array{CrmMailbox, CrmCounterparty} */
    private function mailboxAndCounterparty(): array
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'thread-test@kotlov.by',
            'username' => 'thread-test@kotlov.by',
            'is_enabled' => true,
        ]);
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_prospect',
            'business_unit' => 'wholesale',
            'status' => 'lead',
            'name' => 'Тестовый покупатель',
            'source' => 'email',
        ]);

        return [$mailbox, $counterparty];
    }

    private function thread(CrmMailbox $mailbox, CrmCounterparty $counterparty, string $email): CrmMailThread
    {
        return CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'crm_counterparty_id' => $counterparty->id,
            'thread_key' => hash('sha256', $email),
            'subject' => 'Первый запрос',
            'participant_email' => $email,
            'status' => 'in_progress',
            'priority' => 'normal',
            'last_message_at' => now()->subHour(),
        ]);
    }
}
