<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailboxSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailbox_switcher_separates_order_mail_from_the_main_mailbox(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $info = $this->mailbox('info@kotlov.by');
        $orders = $this->mailbox('order@kotlov.by');
        $infoThread = $this->thread($info, 'info-client@example.com');
        $orderThread = $this->thread($orders, 'order-client@example.com');

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListCrmMailThreads::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$infoThread, $orderThread])
            ->call('selectMailbox', (string) $orders->id)
            ->assertSet('mailbox', (string) $orders->id)
            ->assertCanSeeTableRecords([$orderThread])
            ->assertCanNotSeeTableRecords([$infoThread])
            ->call('selectMailbox', 'all')
            ->assertCanSeeTableRecords([$infoThread, $orderThread]);
    }

    public function test_message_badges_are_scoped_to_the_selected_mailbox(): void
    {
        $info = $this->mailbox('info@kotlov.by');
        $orders = $this->mailbox('order@kotlov.by');
        $infoThread = $this->thread($info, 'info-client@example.com');
        $orderThread = $this->thread($orders, 'order-client@example.com');

        $this->message($infoThread, false);
        $this->message($orderThread, false);
        $this->message($orderThread, true);

        $this->assertSame(1, ListCrmMailThreads::messageBadgeCounts($info->id)['all']);
        $this->assertSame(2, ListCrmMailThreads::messageBadgeCounts($orders->id)['all']);
        $this->assertSame(1, ListCrmMailThreads::messageBadgeCounts($orders->id)['unread']);
    }

    public function test_info_mailbox_is_the_default_sender_even_when_another_mailbox_was_created_first(): void
    {
        CrmMailbox::query()->delete();

        $other = $this->mailbox('sales@kotlov.by');
        $info = $this->mailbox('info@kotlov.by');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->assertSame($info->id, CrmMailbox::query()->preferredSendingOrder()->value('id'));

        Livewire::actingAs($manager)
            ->test(ListCrmMailThreads::class)
            ->mountAction('compose')
            ->assertSchemaStateSet(['mailbox_id' => $info->id]);

        $info->update(['is_enabled' => false]);

        Livewire::actingAs($manager)
            ->test(ListCrmMailThreads::class)
            ->mountAction('compose')
            ->assertSchemaStateSet(['mailbox_id' => $other->id]);
    }

    private function mailbox(string $email): CrmMailbox
    {
        return CrmMailbox::query()->updateOrCreate(['email' => $email], [
            'name' => $email,
            'username' => $email,
            'is_enabled' => true,
        ]);
    }

    private function thread(CrmMailbox $mailbox, string $email): CrmMailThread
    {
        return CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', $email),
            'participant_name' => $email,
            'participant_email' => $email,
            'subject' => 'Письмо '.$email,
            'status' => 'in_progress',
            'messages_count' => 1,
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    private function message(CrmMailThread $thread, bool $isRead): void
    {
        $thread->messages()->create([
            'mailbox_id' => $thread->mailbox_id,
            'direction' => 'incoming',
            'from_email' => $thread->participant_email,
            'subject' => $thread->subject,
            'is_read' => $isRead,
            'received_at' => now(),
        ]);
    }
}
