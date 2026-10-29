<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmCounterparties\Pages\ViewCrmCounterparty;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CommercialDocument;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use App\Services\Crm\CrmClientTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CrmSalesFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_proposal_becomes_a_potential_deal_with_follow_up(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        $manager = User::factory()->create();
        $thread = $this->thread();

        $thread->markProposalSent($manager->id);

        $thread->refresh();
        $this->assertSame('proposal_sent', $thread->sales_stage);
        $this->assertSame('waiting_customer', $thread->status);
        $this->assertSame($manager->id, $thread->assigned_user_id);
        $this->assertSame('2026-10-15 10:00:00', $thread->next_follow_up_at->format('Y-m-d H:i:s'));
        $this->assertTrue(CrmMailThread::query()->potentialDeals()->whereKey($thread)->exists());
    }

    public function test_customer_reply_returns_a_potential_deal_to_manager_attention(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        $thread = $this->thread(['sales_stage' => 'proposal_sent', 'status' => 'waiting_customer']);

        $thread->markCustomerReplied();

        $thread->refresh();
        $this->assertSame('negotiation', $thread->sales_stage);
        $this->assertSame('waiting_manager', $thread->status);
        $this->assertTrue($thread->next_follow_up_at->equalTo(now()));
        $this->assertTrue(CrmMailThread::query()->followUpDue()->whereKey($thread)->exists());
    }

    public function test_regular_correspondence_is_not_turned_into_a_deal_by_a_reply(): void
    {
        $thread = $this->thread(['status' => 'waiting_customer']);

        $thread->markCustomerReplied();

        $this->assertNull($thread->fresh()->sales_stage);
        $this->assertNull($thread->fresh()->next_follow_up_at);
    }

    public function test_manager_can_add_an_internal_note_and_reminder_to_any_mail_thread(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $thread = $this->thread([
            'status' => 'in_progress',
            'subject' => 'Уточнение цены поставщика',
            'sales_stage' => null,
        ]);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertActionVisible('manage_notes')
            ->callAction('manage_notes', [
                'priority' => 'urgent',
                'manager_note' => 'Важно получить цену с НДС.',
                'reminder_enabled' => true,
                'next_follow_up_at' => '2026-10-16 10:00:00',
                'follow_up_note' => 'Проверить ответ поставщика и перезвонить.',
            ])
            ->assertHasNoActionErrors();

        $thread->refresh();

        $this->assertSame('urgent', $thread->priority);
        $this->assertSame('Важно получить цену с НДС.', $thread->manager_note);
        $this->assertSame('Проверить ответ поставщика и перезвонить.', $thread->follow_up_note);
        $this->assertSame('2026-10-16 10:00:00', $thread->next_follow_up_at->format('Y-m-d H:i:s'));
        $this->assertNull($thread->sales_stage);

        Carbon::setTestNow('2026-10-16 10:01:00');

        $this->assertTrue(CrmMailThread::query()->followUpDue()->whereKey($thread)->exists());
        $this->assertStringStartsWith('Просрочено: 16.10 ', CrmMailThreadResource::followUpLabel($thread));

        Livewire::actingAs($manager)
            ->test(ListCrmMailThreads::class)
            ->set('activeTab', 'follow_up')
            ->assertSee('Уточнение цены поставщика')
            ->assertSee('Просрочено');
    }

    public function test_ai_created_sent_offer_has_visible_marker_in_deals_list(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $thread = $this->thread([
            'assigned_user_id' => $manager->id,
            'sales_stage' => 'proposal_sent',
            'status' => 'waiting_customer',
            'next_follow_up_at' => now()->subMinute(),
            'subject' => 'Коммерческое предложение',
        ]);
        CommercialDocument::create([
            'number' => 'КП-TEST-00001',
            'type' => 'quote',
            'status' => 'sent',
            'ai_assisted' => true,
            'crm_mail_thread_id' => $thread->id,
            'issued_at' => today(),
            'currency' => 'BYN',
            'seller_snapshot' => [],
        ]);

        $thread->load('commercialDocuments');
        $this->assertSame('heroicon-o-sparkles', CrmMailThreadResource::commercialOfferIcon($thread));
        $this->assertStringContainsString('ИИ', CrmMailThreadResource::commercialOfferTooltip($thread));

        Livewire::actingAs($manager)
            ->test(ListCrmMailThreads::class)
            ->set('activeTab', 'potential_deals')
            ->assertSee('КП отправлено')
            ->assertSee('Просрочено');
    }

    public function test_new_email_subject_shows_the_clients_previous_quote_and_deal_stage(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'type' => 'organization',
            'classification' => 'b2b_prospect',
            'business_unit' => 'wholesale',
            'status' => 'lead',
            'name' => 'ООО «Мозырский лесозавод»',
            'source' => 'email',
        ]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'sales-history@kotlov.by',
            'username' => 'sales-history@kotlov.by',
        ]);
        $oldThread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'crm_counterparty_id' => $counterparty->id,
            'thread_key' => hash('sha256', 'old-request'),
            'participant_email' => 'client@example.com',
            'subject' => 'Первый запрос',
            'status' => 'waiting_customer',
            'sales_stage' => 'proposal_sent',
            'last_message_at' => now()->subDay(),
        ]);
        $newThread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'crm_counterparty_id' => $counterparty->id,
            'thread_key' => hash('sha256', 'new-request'),
            'participant_email' => 'client@example.com',
            'subject' => 'Заявка Гидравлический разделитель',
            'status' => 'waiting_manager',
            'last_message_at' => now(),
        ]);
        $oldThread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'direction' => 'outgoing',
            'from_email' => 'sales@kotlov.by',
            'subject' => 'Коммерческое предложение',
            'text_body' => 'Направляем ранее подготовленное коммерческое предложение.',
            'is_read' => true,
            'received_at' => now()->subDay(),
        ]);
        $newThread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'client@example.com',
            'subject' => 'Заявка Гидравлический разделитель',
            'text_body' => 'Просим выставить новое предложение.',
            'is_read' => false,
            'received_at' => now(),
        ]);
        $quote = CommercialDocument::query()->create([
            'number' => 'КП-HISTORY-00001',
            'type' => 'quote',
            'status' => 'sent',
            'crm_mail_thread_id' => $oldThread->id,
            'crm_counterparty_id' => $counterparty->id,
            'issued_at' => today()->subDay(),
        ]);

        $this->assertTrue(CrmMailThreadResource::linkedQuote($newThread)?->is($quote));
        $this->assertSame('proposal_sent', CrmMailThreadResource::effectiveSalesStage($newThread));
        $this->assertSame('heroicon-o-document-check', CrmMailThreadResource::commercialOfferIcon($newThread));
        $this->assertStringContainsString('другой переписке', CrmMailThreadResource::commercialOfferTooltip($newThread));

        $timeline = app(CrmClientTimeline::class)->for($counterparty, null);
        $this->assertTrue($timeline->contains(fn (array $event): bool => str_contains($event['title'], 'Входящее письмо')));
        $this->assertTrue($timeline->contains(fn (array $event): bool => str_contains($event['title'], 'Исходящее письмо')));
        $this->assertTrue($timeline->contains(fn (array $event): bool => $event['url_type'] === 'document'));

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        Livewire::actingAs($manager)
            ->test(ViewCrmCounterparty::class, ['record' => $counterparty->getRouteKey()])
            ->assertSeeText('Единая цепочка клиента')
            ->assertSeeText('Входящее письмо')
            ->assertSeeText('Исходящее письмо')
            ->assertSeeText('КП-HISTORY-00001');
    }

    public function test_linked_quote_and_mail_thread_share_stage_reminder_and_required_reaction(): void
    {
        Carbon::setTestNow('2026-10-19 12:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $thread = $this->thread([
            'assigned_user_id' => $manager->id,
            'status' => 'waiting_customer',
            'sales_stage' => 'proposal_sent',
            'next_follow_up_at' => now()->addDay(),
            'follow_up_note' => 'Ожидаем первичный ответ клиента.',
        ]);
        $quote = CommercialDocument::query()->create([
            'number' => 'КП-SYNC-00001',
            'type' => 'quote',
            'status' => 'sent',
            'crm_mail_thread_id' => $thread->id,
            'manager_id' => $manager->id,
            'valid_until' => today()->addDays(10),
        ]);

        $thread->update([
            'status' => 'waiting_manager',
            'sales_stage' => 'negotiation',
            'next_follow_up_at' => now(),
            'follow_up_note' => 'Получен ответ: клиент просит уточнить срок поставки.',
        ]);

        $quote->refresh();
        $this->assertSame('negotiation', $quote->follow_up_stage);
        $this->assertSame('negotiation', $quote->last_contact_result);
        $this->assertTrue($quote->next_follow_up_at->equalTo(now()));
        $this->assertSame('Получен ответ: клиент просит уточнить срок поставки.', $quote->follow_up_note);
        $this->assertTrue($quote->needsManagerControl());
        $this->assertTrue(CrmMailThread::query()->requiresReaction()->whereKey($thread)->exists());
        $this->assertSame('mail', $quote->contact_history[0]['source']);

        Livewire::actingAs($manager)
            ->test(ListCrmMailThreads::class)
            ->set('activeTab', 'needs_response')
            ->assertCanSeeTableRecords([$thread])
            ->assertTableActionVisible('open_quote', $thread);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeText('КП-SYNC-00001')
            ->assertSeeText('Обсудили условия');

        $callbackAt = now()->addDays(2)->setTime(10, 30);
        app(CommercialDocumentWorkflow::class)->recordContact(
            $quote,
            $manager,
            'decision_pending',
            'decision_pending',
            'Клиент думает, вернуться после внутреннего согласования.',
            $callbackAt,
        );

        $thread->refresh();
        $this->assertSame('decision_pending', $thread->sales_stage);
        $this->assertSame('waiting_customer', $thread->status);
        $this->assertSame($callbackAt->timestamp, $thread->next_follow_up_at?->timestamp);
        $this->assertSame('Клиент думает, вернуться после внутреннего согласования.', $thread->follow_up_note);
        $this->assertFalse(CrmMailThread::query()->requiresReaction()->whereKey($thread)->exists());
    }

    private function thread(array $attributes = []): CrmMailThread
    {
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->safeEmail(),
        ]);

        return CrmMailThread::create(array_merge([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', fake()->uuid()),
            'participant_email' => fake()->safeEmail(),
        ], $attributes));
    }
}
