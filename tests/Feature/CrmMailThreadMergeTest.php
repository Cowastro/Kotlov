<?php

namespace Tests\Feature;

use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailThreadMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmMailThreadMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_an_old_deal_card_into_the_active_mail_chain(): void
    {
        $manager = User::factory()->create();
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'merge-test@kotlov.by',
            'username' => 'merge-test@kotlov.by',
            'is_enabled' => true,
        ]);
        $source = $this->thread($mailbox, [
            'thread_key' => 'old-request',
            'subject' => 'Запрос КП',
            'sales_stage' => 'new_inquiry',
            'next_follow_up_at' => now()->subHour(),
            'assigned_user_id' => $manager->id,
        ]);
        $target = $this->thread($mailbox, [
            'thread_key' => 'active-chain',
            'subject' => 'Re: КП-1',
            'status' => 'waiting_customer',
            'assigned_user_id' => $manager->id,
        ]);
        $source->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => 'request@example.com',
            'direction' => 'incoming',
            'from_email' => 'client@example.com',
            'subject' => 'Запрос КП',
            'is_read' => true,
            'received_at' => now()->subDay(),
        ]);
        $target->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => 'quote@example.com',
            'direction' => 'outgoing',
            'from_email' => $mailbox->email,
            'subject' => 'КП-1',
            'is_read' => true,
            'received_at' => now(),
        ]);
        $latestMessageAt = now()->addHour()->startOfSecond();
        $target->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => 'reply@example.com',
            'in_reply_to' => 'quote@example.com',
            'direction' => 'incoming',
            'from_email' => 'client@example.com',
            'subject' => 'Re: КП-1 — согласование',
            'is_read' => true,
            'received_at' => $latestMessageAt,
        ]);
        CommercialDocument::query()->create([
            'type' => 'quote',
            'status' => 'sent',
            'crm_mail_thread_id' => $target->id,
        ]);

        $merged = app(CrmMailThreadMerger::class)->merge($source, $target);

        $this->assertSoftDeleted($source);
        $this->assertSame(3, $merged->messages_count);
        $this->assertSame(3, $merged->messages->count());
        $this->assertSame('Re: КП-1 — согласование', $merged->subject);
        $this->assertTrue($merged->last_message_at->equalTo($latestMessageAt));
        $this->assertSame('proposal_sent', $merged->sales_stage);
        $this->assertSame('waiting_customer', $merged->status);
        $this->assertNotNull($merged->next_follow_up_at);
        $this->assertSame($target->id, CommercialDocument::query()->firstOrFail()->crm_mail_thread_id);
    }

    private function thread(CrmMailbox $mailbox, array $attributes): CrmMailThread
    {
        return $mailbox->threads()->create(array_merge([
            'participant_email' => 'client@example.com',
            'status' => 'in_progress',
            'priority' => 'normal',
            'messages_count' => 1,
            'unread_count' => 0,
            'last_message_at' => now(),
        ], $attributes));
    }
}
