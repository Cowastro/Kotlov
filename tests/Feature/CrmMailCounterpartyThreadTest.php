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

    public function test_incoming_mail_with_a_new_subject_starts_a_separate_lead_thread(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $this->thread($mailbox, $counterparty, 'buyer@example.com');

        $resolved = app(CrmMailThreadResolver::class)->resolve(
            $mailbox,
            'buyer@example.com',
            $counterparty->id,
            null,
            'ЕВРОПОЧТА. Документы для договора',
        );

        $this->assertNull($resolved);
    }

    public function test_new_subject_can_inherit_the_existing_client_context_without_merging_threads(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $manager = User::factory()->create();
        $thread = $this->thread($mailbox, $counterparty, 'buyer@example.com');
        $thread->update([
            'assigned_user_id' => $manager->id,
            'sales_stage' => 'proposal_sent',
        ]);

        $resolver = app(CrmMailThreadResolver::class);

        $this->assertNull($resolver->resolve(
            $mailbox,
            'buyer@example.com',
            $counterparty->id,
            null,
            'Совершенно новый запрос',
        ));
        $this->assertTrue($resolver->latestForClient(
            $mailbox,
            'buyer@example.com',
            $counterparty->id,
        )?->is($thread));
    }

    public function test_reply_subject_without_message_id_stays_in_the_matching_thread(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $thread = $this->thread($mailbox, $counterparty, 'buyer@example.com');

        $resolved = app(CrmMailThreadResolver::class)->resolve(
            $mailbox,
            'buyer@example.com',
            $counterparty->id,
            null,
            'Re: Первый запрос',
        );

        $this->assertTrue($resolved?->is($thread));
    }

    public function test_new_incoming_message_returns_taken_thread_to_reaction_queue(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $thread = $this->thread($mailbox, $counterparty, 'buyer@example.com');

        $thread->markCustomerReplied();

        $this->assertSame('waiting_manager', $thread->fresh()->status);
    }

    public function test_recovery_command_returns_an_already_imported_incoming_thread_to_reaction_queue(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $thread = $this->thread($mailbox, $counterparty, 'buyer@example.com');
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => 'europoshta-documents@example.com',
            'direction' => 'incoming',
            'from_email' => 'buyer@example.com',
            'subject' => 'ЕВРОПОЧТА. Документы для договора',
            'is_read' => false,
            'received_at' => now(),
        ]);

        $this->artisan('crm-mail:refresh-thread', [
            'thread' => $thread->id,
            '--recover-reaction' => true,
        ])->assertSuccessful();

        $this->assertSame('waiting_manager', $thread->fresh()->status);
        $this->assertSame(1, $thread->fresh()->unread_count);
        $this->assertSame('ЕВРОПОЧТА. Документы для договора', $thread->fresh()->subject);
    }

    public function test_refresh_command_restores_trashed_thread_with_latest_incoming_message(): void
    {
        [$mailbox, $counterparty] = $this->mailboxAndCounterparty();
        $thread = $this->thread($mailbox, $counterparty, 'buyer@example.com');
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'buyer@example.com',
            'subject' => 'Документы для договора',
            'is_read' => false,
            'received_at' => now(),
        ]);
        $thread->delete();

        $this->artisan('crm-mail:refresh-thread', [
            'thread' => $thread->id,
            '--recover-reaction' => true,
        ])
            ->expectsOutput('reaction_recovered=1')
            ->expectsOutput('thread_visible=1')
            ->assertSuccessful();

        $restored = CrmMailThread::query()->findOrFail($thread->id);
        $this->assertSame('waiting_manager', $restored->status);
        $this->assertNull($restored->deleted_at);
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
