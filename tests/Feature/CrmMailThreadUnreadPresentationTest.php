<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CrmMailThreadUnreadPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_newest_messages_are_shown_first_in_a_thread(): void
    {
        [$mailbox, $thread] = $this->mailThread();

        $oldest = $this->message($mailbox, $thread, 'Старое письмо', now()->subHour());
        $newest = $this->message($mailbox, $thread, 'Новое письмо', now());

        $loadedThread = CrmMailThreadResource::getEloquentQuery()->findOrFail($thread->id);

        $this->assertSame([$newest->id, $oldest->id], $loadedThread->messages->pluck('id')->all());
    }

    public function test_unread_messages_remain_visually_marked_during_the_first_open(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        [$mailbox, $thread] = $this->mailThread();

        $read = $this->message($mailbox, $thread, 'Ранее прочитано', now()->subHour(), true);
        $unread = $this->message($mailbox, $thread, 'Новое непрочитанное', now(), false);
        $thread->forceFill(['messages_count' => 2, 'unread_count' => 1])->save();

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertSet('initiallyUnreadMessageIds', [$unread->id])
            ->assertSee('Не прочитано')
            ->assertSee('is-unread', false);

        $this->assertTrue($unread->fresh()->is_read);
        $this->assertTrue($read->fresh()->is_read);
        $this->assertSame(0, $thread->fresh()->unread_count);
    }

    public function test_forwarded_message_is_shown_above_older_messages_without_reloading_page(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        [$mailbox, $thread] = $this->mailThread();
        $source = $this->message($mailbox, $thread, 'Старое письмо', now()->subMinute(), true);

        $sender = Mockery::mock(CrmMailSender::class);
        $sender->shouldReceive('forwardedText')
            ->once()
            ->withArgs(fn (CrmMailMessage $message): bool => $message->is($source))
            ->andReturn('Пересылаем сообщение.');
        $sender->shouldReceive('forward')
            ->once()
            ->andReturnUsing(function (
                CrmMailThread $sentThread,
                CrmMailMessage $sentSource,
                array $recipients,
                string $subject,
                string $body,
                User $user,
            ) use ($mailbox): CrmMailMessage {
                return CrmMailMessage::query()->create([
                    'thread_id' => $sentThread->id,
                    'mailbox_id' => $mailbox->id,
                    'sent_by_user_id' => $user->id,
                    'direction' => 'outgoing',
                    'from_name' => 'KOTLOV',
                    'from_email' => $mailbox->email,
                    'to' => collect($recipients)->map(fn (string $email): array => ['email' => $email])->all(),
                    'subject' => $subject,
                    'text_body' => $body,
                    'is_read' => true,
                    'received_at' => now(),
                    'sent_at' => now(),
                ]);
            });
        $this->app->instance(CrmMailSender::class, $sender);

        $component = Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->call('openForward', $source->id)
            ->fillForm([
                'recipients' => ['recipient@example.com'],
                'cc' => [],
                'subject' => 'Новое пересланное письмо',
                'body' => 'Пересылаем сообщение.',
                'original_attachments' => [],
                'attachments' => [],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $html = $component->html();
        $newPosition = mb_strpos($html, 'Новое пересланное письмо');
        $oldPosition = mb_strpos($html, 'Старое письмо');

        $this->assertIsInt($newPosition);
        $this->assertIsInt($oldPosition);
        $this->assertLessThan($oldPosition, $newPosition);
    }

    public function test_latest_message_has_quick_lead_actions(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        [$mailbox, $thread] = $this->mailThread();
        $this->message($mailbox, $thread, 'Запрос клиента', now());

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertSee('wire:click="takeThread"', false)
            ->assertSee('wire:click="markPotentialDeal"', false)
            ->assertSee('wire:click="skipThread"', false)
            ->call('takeThread')
            ->call('markPotentialDeal')
            ->call('skipThread')
            ->assertDontSee('wire:click="takeThread"', false)
            ->assertDontSee('wire:click="markPotentialDeal"', false)
            ->assertDontSee('wire:click="skipThread"', false);

        $thread->refresh();
        $this->assertSame($manager->id, $thread->assigned_user_id);
        $this->assertSame('closed', $thread->status);
        $this->assertSame('new_inquiry', $thread->sales_stage);
        $this->assertNotNull($thread->handled_at);
        $this->assertNull($thread->next_follow_up_at);
        $this->assertNull($thread->follow_up_note);
    }

    /** @return array{CrmMailbox, CrmMailThread} */
    private function mailThread(): array
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'thread-order@kotlov.by',
            'username' => 'thread-order@kotlov.by',
            'is_enabled' => true,
        ]);

        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'thread-order@example.com'),
            'participant_name' => 'Клиент',
            'participant_email' => 'thread-order@example.com',
            'subject' => 'Переписка',
            'status' => 'new',
        ]);

        return [$mailbox, $thread];
    }

    private function message(
        CrmMailbox $mailbox,
        CrmMailThread $thread,
        string $subject,
        mixed $receivedAt,
        bool $isRead = false,
    ): CrmMailMessage {
        return CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_name' => 'Клиент',
            'from_email' => 'thread-order@example.com',
            'to' => [['email' => $mailbox->email]],
            'subject' => $subject,
            'text_body' => $subject,
            'is_read' => $isRead,
            'received_at' => $receivedAt,
        ]);
    }
}
