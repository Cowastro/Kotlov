<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailReplyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_opens_as_a_single_compose_form_without_a_second_confirmation(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'reply-action@kotlov.by',
            'username' => 'reply-action@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'reply-action@example.com|request'),
            'participant_name' => 'Тестовый клиент',
            'participant_email' => 'reply-action@example.com',
            'subject' => 'Запрос',
            'status' => 'new',
        ]);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertActionExists('reply', fn ($action): bool => ! $action->isConfirmationRequired()
                && $action->getModalSubmitActionLabel() === 'Отправить письмо');
    }

    public function test_message_can_open_forward_form_with_subject_body_and_attachments_prefilled(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'forward-action@kotlov.by',
            'username' => 'forward-action@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'forward-action@example.com|request'),
            'participant_name' => 'Тестовый клиент',
            'participant_email' => 'forward-action@example.com',
            'subject' => 'Запрос прайса',
            'status' => 'new',
        ]);
        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_name' => 'Клиент',
            'from_email' => 'forward-action@example.com',
            'to' => [['name' => 'KOTLOV', 'email' => 'forward-action@kotlov.by']],
            'subject' => 'Запрос прайса',
            'text_body' => 'Добрый день. Пришлите прайс.',
            'attachments_count' => 1,
            'is_read' => false,
            'received_at' => now(),
            'metadata' => ['attachments' => [[
                'path' => 'crm-mail-attachments/incoming/price.xlsx',
                'name' => 'price.xlsx',
            ]]],
        ]);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertActionExists('forward', fn ($action): bool => $action->getModalSubmitActionLabel() === 'Переслать')
            ->call('openForward', $message->id)
            ->assertActionMounted('forward')
            ->assertSchemaStateSet([
                'subject' => 'Fwd: Запрос прайса',
                'original_attachments' => ['crm-mail-attachments/incoming/price.xlsx'],
            ]);
    }

    public function test_employee_can_be_saved_to_mail_address_book_without_duplicates(): void
    {
        $email = CrmMailThreadResource::createAddressBookContact([
            'full_name' => 'Анна Бухгалтер',
            'email' => 'ANNA@EXAMPLE.COM',
            'position' => 'Бухгалтерия',
        ]);

        $this->assertSame('anna@example.com', $email);
        $this->assertSame('Анна Бухгалтер — anna@example.com', CrmMailThreadResource::addressBookOptions()[$email]);
        $this->assertDatabaseHas('crm_contacts', [
            'full_name' => 'Анна Бухгалтер',
            'position' => 'Бухгалтерия',
            'is_active' => true,
        ]);

        CrmMailThreadResource::createAddressBookContact([
            'full_name' => 'Другое имя',
            'email' => 'anna@example.com',
        ]);

        $this->assertSame(1, CrmContact::query()->count());
        $this->assertSame(1, CrmContactPoint::query()->where('normalized_value', 'anna@example.com')->count());
    }

    public function test_latest_message_opens_reply_action(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'latest-reply@kotlov.by',
            'username' => 'latest-reply@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'latest-reply@example.com'),
            'participant_name' => 'Клиент',
            'participant_email' => 'latest-reply@example.com',
            'subject' => 'Запрос',
            'status' => 'new',
        ]);
        CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'latest-reply@example.com',
            'subject' => 'Запрос',
            'text_body' => 'Первое письмо',
            'received_at' => now()->subMinute(),
        ]);
        $latest = CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_email' => 'latest-reply@example.com',
            'subject' => 'Re: Запрос',
            'text_body' => 'Последнее письмо',
            'received_at' => now(),
        ]);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->call('openReply', $latest->id)
            ->assertActionMounted('reply');
    }

    public function test_older_incoming_message_can_open_its_own_reply_action(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'reply-to-older@kotlov.by',
            'username' => 'reply-to-older@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'reply-to-older@example.com'),
            'participant_name' => 'Клиент',
            'participant_email' => 'latest-address@example.com',
            'subject' => 'Последняя тема',
            'status' => 'new',
        ]);
        $older = CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'message_id' => 'older-message@example.com',
            'direction' => 'incoming',
            'from_email' => 'older-address@example.com',
            'subject' => 'Более ранняя тема',
            'text_body' => 'Первое письмо',
            'received_at' => now()->subHour(),
        ]);
        CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'message_id' => 'newer-message@example.com',
            'direction' => 'incoming',
            'from_email' => 'latest-address@example.com',
            'subject' => 'Последняя тема',
            'text_body' => 'Второе письмо',
            'received_at' => now(),
        ]);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->call('openReply', $older->id)
            ->assertSet('replyMessageId', $older->id)
            ->assertActionMounted('reply');
    }

    public function test_forwarded_html_contains_readable_text_without_css(): void
    {
        $message = new CrmMailMessage([
            'from_name' => 'Поставщик',
            'from_email' => 'supplier@example.com',
            'to' => [['email' => 'info@kotlov.by']],
            'subject' => 'Заказ поставщику',
            'text_body' => null,
            'html_body' => '<html><head><style>tr.R4 { height: 46px; } td { border: #000 2px solid; }</style></head><body><h1>Заказ № 929412</h1><table><tr><td>Хомут трубный</td><td>4 шт.</td></tr></table></body></html>',
        ]);

        $text = app(CrmMailSender::class)->forwardedText($message);

        $this->assertStringContainsString('Заказ № 929412', $text);
        $this->assertStringContainsString('Хомут трубный', $text);
        $this->assertStringNotContainsString('tr.R4', $text);
        $this->assertStringNotContainsString('border:', $text);
    }
}
