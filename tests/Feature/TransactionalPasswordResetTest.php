<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TransactionalPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_uses_the_transactional_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'partner@example.com']);

        $status = Password::sendResetLink(['email' => $user->email]);

        $this->assertSame(Password::RESET_LINK_SENT, $status);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_transactional_message_uses_the_dedicated_mailer_and_sender(): void
    {
        CrmMailbox::query()->create([
            'name' => 'KOTLOV.BY',
            'email' => 'no-reply@kotlov.by',
            'username' => 'no-reply@kotlov.by',
            'password' => 'secret-test-password',
            'imap_host' => 'imap.hoster.by',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.hoster.by',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
        ]);

        $user = User::factory()->make(['email' => 'partner@example.com']);
        $message = (new ResetPasswordNotification('test-token'))->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertSame('transactional', $message->mailer);
        $this->assertSame(['no-reply@kotlov.by', 'KOTLOV.BY'], $message->from);
        $this->assertSame('Доступ к аккаунту KOTLOV.BY', $message->subject);
        $this->assertSame('no-reply@kotlov.by', config('mail.mailers.transactional.username'));
        $this->assertSame('smtps', config('mail.mailers.transactional.scheme'));
    }
}
