<?php

namespace Tests\Unit;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Services\CrmMail\CrmMailMessagePreview;
use App\Services\CrmMail\IncomingMailTelegramNotifier;
use App\Services\TelegramApi;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class IncomingMailTelegramNotifierTest extends TestCase
{
    public function test_it_sends_new_mail_details_and_crm_link_to_the_shared_telegram_group(): void
    {
        config([
            'app.url' => 'https://kotlov.by',
            'app.display_timezone' => 'Europe/Minsk',
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.mail_chat_id' => '-100123456789',
        ]);

        $mailbox = new CrmMailbox(['email' => 'info@kotlov.by']);
        $thread = new CrmMailThread(['subject' => 'Запрос']);
        $thread->id = 77;
        $message = new CrmMailMessage([
            'thread_id' => 77,
            'direction' => 'incoming',
            'from_name' => 'Иван <Отдел продаж>',
            'from_email' => 'client@example.com',
            'subject' => 'Котёл & доставка',
            'text_body' => "Здравствуйте!\nНужен котёл на 20 кВт.",
            'attachments_count' => 2,
            'received_at' => Carbon::parse('2026-10-14 11:23:00', 'UTC'),
        ]);
        $message->id = 15;
        $message->setRelation('thread', $thread);
        $message->setRelation('mailbox', $mailbox);

        $sent = [];
        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, string $text, array $options) use (&$sent): bool {
                $sent = compact('chatId', 'text', 'options');

                return true;
            })
            ->andReturn(['ok' => true, 'result' => ['message_id' => 501]]);

        $this->assertTrue((new IncomingMailTelegramNotifier($telegram, new CrmMailMessagePreview))->send($message));
        $this->assertSame('-100123456789', $sent['chatId']);
        $this->assertStringContainsString('Новое входящее письмо', $sent['text']);
        $this->assertStringContainsString('Иван &lt;Отдел продаж&gt; · client@example.com', $sent['text']);
        $this->assertStringContainsString('Котёл &amp; доставка', $sent['text']);
        $this->assertStringContainsString('Здравствуйте! Нужен котёл на 20 кВт.', $sent['text']);
        $this->assertStringContainsString('Вложения:</b> 2 шт.', $sent['text']);
        $this->assertStringContainsString('/admin/crm-mail-threads/77', $sent['text']);
        $this->assertStringContainsString('14.10.2026 14:23 (Минск)', $sent['text']);
        $this->assertSame('HTML', $sent['options']['parse_mode']);
        $this->assertTrue($sent['options']['disable_web_page_preview']);
        $this->assertStringContainsString('📨 Открыть письмо', $sent['options']['reply_markup']);
        $this->assertStringContainsString('/admin/crm-mail-threads/77', $sent['options']['reply_markup']);
    }

    public function test_it_removes_email_css_from_the_telegram_preview(): void
    {
        config([
            'app.url' => 'https://kotlov.by',
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.mail_chat_id' => '-100123456789',
        ]);

        $message = new CrmMailMessage([
            'thread_id' => 88,
            'from_email' => 'client@example.com',
            'subject' => 'Документы',
            'text_body' => 'blockquote.rt { margin: 0 0 15px; border-left: 4px solid #81c784; } Добрый день. Документы во вложении. p.cpr_body { background: #ffffff; font-family: Arial; }',
        ]);
        $message->id = 16;
        $message->setRelation('thread', new CrmMailThread);
        $message->setRelation('mailbox', new CrmMailbox(['email' => 'info@kotlov.by']));

        $sentText = '';
        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, string $text) use (&$sentText): bool {
                $sentText = $text;

                return true;
            })
            ->andReturn(['ok' => true]);

        $this->assertTrue((new IncomingMailTelegramNotifier(
            $telegram,
            new CrmMailMessagePreview,
        ))->send($message));
        $this->assertStringContainsString('Добрый день. Документы во вложении.', $sentText);
        $this->assertStringNotContainsString('blockquote.rt', $sentText);
        $this->assertStringNotContainsString('font-family', $sentText);
        $this->assertStringNotContainsString('background:', $sentText);
    }

    public function test_it_does_not_call_telegram_without_credentials(): void
    {
        config([
            'services.telegram.bot_token' => null,
            'services.telegram.mail_chat_id' => null,
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldNotReceive('sendMessage');

        $this->assertFalse((new IncomingMailTelegramNotifier(
            $telegram,
            new CrmMailMessagePreview,
        ))->send(new CrmMailMessage));
    }
}
