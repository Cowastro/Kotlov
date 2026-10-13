<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
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
