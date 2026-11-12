<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailThreadResolver;
use App\Services\CrmMail\SplitMailRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SplitMailRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_preserves_old_refusal_quote_and_attachments_and_routes_future_replies(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        [$source, $message] = $this->request($manager);
        $quote = CommercialDocument::create([
            'number' => 'КП-SPLIT-1', 'type' => 'quote', 'status' => 'rejected',
            'crm_mail_thread_id' => $source->id, 'follow_up_stage' => 'lost',
            'outcome_note' => 'Клиент отказался от предыдущей закупки.',
        ]);
        $beforeQuote = $quote->fresh()->getAttributes();

        $new = app(SplitMailRequest::class)->split($source, $message->id, 'Новая закупка ТЭН', $actor);

        $this->assertSame('new_inquiry', $new->sales_stage);
        $this->assertSame('waiting_manager', $new->status);
        $this->assertSame($manager->id, $new->assigned_user_id);
        $this->assertSame($source->id, $new->source_thread_id);
        $this->assertSame(1, $new->messages_count);
        $this->assertSame(1, $new->unread_count);
        $this->assertSame('lost', $source->fresh()->sales_stage);
        $this->assertSame('closed', $source->fresh()->status);
        $this->assertSame(1, $source->fresh()->messages_count);
        $this->assertSame(0, $source->fresh()->unread_count);
        $this->assertEquals($beforeQuote, $quote->fresh()->getAttributes());
        $this->assertSame($new->id, $message->fresh()->thread_id);
        $this->assertSame('stable-request@example.com', $message->message_id);
        $this->assertSame('crm-mail-attachments/request.pdf', $message->fresh()->metadata['attachments'][0]['path']);
        $this->assertSame($actor->id, $message->fresh()->metadata['request_split_history'][0]['user_id']);
        $this->assertSame(2, CrmMailMessage::count());
        $this->assertTrue(app(CrmMailThreadResolver::class)->resolve(
            $source->mailbox, $message->from_email, null, $message->message_id, 'Re: Старая закупка',
        )->is($new));

        try {
            app(SplitMailRequest::class)->split($source, $message->id, 'Повтор', $actor);
            $this->fail('A moved message must not be split from its former thread twice.');
        } catch (ModelNotFoundException) {
            $this->assertSame(2, CrmMailThread::count());
        }
    }

    public function test_manager_can_open_modal_and_split_a_selected_incoming_message(): void
    {
        $actor = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        [$source, $message] = $this->request($actor);

        Livewire::actingAs($actor)->test(ViewCrmMailThread::class, ['record' => $source->getRouteKey()])
            ->call('openSplitRequest', $message->id)
            ->assertActionMounted('split_request')
            ->setActionData(['subject' => 'Отдельная закупка'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $new = CrmMailThread::where('source_thread_id', $source->id)->sole();
        $this->assertSame('Отдельная закупка', $new->subject);
        $this->assertSame('new_inquiry', $new->sales_stage);
        Livewire::actingAs($actor)->test(ViewCrmMailThread::class, ['record' => $source->getRouteKey()])
            ->assertSeeText('Отдельная закупка');
        Livewire::actingAs($actor)->test(ViewCrmMailThread::class, ['record' => $new->getRouteKey()])
            ->assertSeeText('Исходная переписка');
    }

    public function test_foreign_message_cannot_be_moved_from_another_thread(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        [$source] = $this->request($actor);
        [, $foreign] = $this->request($actor);
        $this->expectException(ModelNotFoundException::class);
        app(SplitMailRequest::class)->split($source, $foreign->id, 'Другой запрос', $actor);
    }

    public function test_employee_cannot_split_a_message_from_an_inaccessible_mailbox(): void
    {
        $owner = User::factory()->create(['role' => 'manager']);
        $other = User::factory()->create(['role' => 'manager']);
        [$source, $message] = $this->request($owner);
        $source->mailbox->users()->attach($owner);

        $this->expectException(ModelNotFoundException::class);
        app(SplitMailRequest::class)->split($source, $message->id, 'Новая закупка', $other);
    }

    public function test_outgoing_message_cannot_start_a_new_incoming_request(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        [$source] = $this->request($actor);
        $outgoing = $source->messages()->where('direction', 'outgoing')->sole();

        try {
            app(SplitMailRequest::class)->split($source, $outgoing->id, 'Новая закупка', $actor);
            $this->fail('Only an incoming message may start a new request.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame(1, CrmMailThread::count());
            $this->assertSame($source->id, $outgoing->fresh()->thread_id);
        }
    }

    private function request(User $manager): array
    {
        $mailbox = CrmMailbox::create(['name' => 'Sales', 'email' => fake()->unique()->safeEmail(), 'username' => fake()->unique()->safeEmail()]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id, 'thread_key' => fake()->uuid(),
            'subject' => 'Старая закупка', 'participant_email' => 'buyer@example.com',
            'assigned_user_id' => $manager->id, 'sales_stage' => 'lost', 'status' => 'closed',
        ]);
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id, 'direction' => 'outgoing', 'from_email' => 'sales@example.com',
            'is_read' => true, 'received_at' => now()->subDays(5),
        ]);
        $message = $thread->messages()->create([
            'mailbox_id' => $mailbox->id, 'direction' => 'incoming', 'from_email' => 'buyer@example.com',
            'subject' => 'Re: Старая закупка', 'message_id' => 'stable-request@example.com',
            'imap_uid' => 101, 'text_body' => 'Просим новое предложение.', 'is_read' => false,
            'received_at' => now(), 'attachments_count' => 1,
            'metadata' => ['attachments' => [['path' => 'crm-mail-attachments/request.pdf', 'name' => 'request.pdf']]],
        ]);

        return [$thread, $message];
    }
}
