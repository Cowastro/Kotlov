<?php

namespace Tests\Feature;

use App\Services\ServiceMailboxPreviewSender;
use Tests\TestCase;

class TestServiceMailboxesCommandTest extends TestCase
{
    public function test_command_sends_both_service_mailbox_previews(): void
    {
        $sender = $this->mock(ServiceMailboxPreviewSender::class);
        $sender->shouldReceive('sendAll')
            ->once()
            ->with('dzianis.hotta@gmail.com')
            ->andReturn(['order@kotlov.by', 'no-reply@kotlov.by']);

        $this->artisan('mail:test-service-mailboxes', ['--to' => 'dzianis.hotta@gmail.com'])
            ->expectsOutputToContain('Отправлено: order@kotlov.by')
            ->expectsOutputToContain('Отправлено: no-reply@kotlov.by')
            ->assertSuccessful();
    }

    public function test_command_rejects_an_invalid_recipient(): void
    {
        $this->artisan('mail:test-service-mailboxes', ['--to' => 'not-an-email'])
            ->expectsOutputToContain('Укажите корректный email')
            ->assertFailed();
    }

    public function test_preview_is_branded_and_explains_the_service_message(): void
    {
        $html = view('emails.service-mailbox-preview', [
            'subject' => 'Тест',
            'eyebrow' => 'ЗАКАЗ ПРИНЯТ',
            'title' => 'Спасибо за заказ',
            'message' => 'Тест системного письма.',
            'details' => ['Статус' => 'Принят в обработку'],
            'button' => 'Перейти на KOTLOV.BY',
            'notice' => 'Письмо отправлено автоматически.',
            'sender' => 'order@kotlov.by',
            'siteUrl' => 'https://kotlov.by',
        ])->render();

        $this->assertStringContainsString('KOTLOV.BY', $html);
        $this->assertStringContainsString('Спасибо за заказ', $html);
        $this->assertStringContainsString('order@kotlov.by', $html);
        $this->assertStringNotContainsString('имя сотрудника', $html);
    }
}
