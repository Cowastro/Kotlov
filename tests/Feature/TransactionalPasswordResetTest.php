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

    public function test_reset_link_from_the_letter_opens_the_form_and_sets_a_new_password(): void
    {
        $user = User::factory()->create(['email' => 'partner@example.com', 'password' => 'old-password-1']);
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Задайте новый пароль')
            ->assertSee('partner@example.com', false)
            ->assertSee($token, false);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect('/')->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-1', $user->fresh()->password));
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'again-password-2',
            'password_confirmation' => 'again-password-2',
        ])->assertSessionHasErrors('email');
    }

    public function test_every_transactional_letter_leaves_through_the_no_reply_mailbox(): void
    {
        $this->assertNotSame('no-reply@kotlov.by', config('mail.mailers.transactional.username'));
        $this->noReplyMailbox();

        // Any letter (order, B2B, invitation, also in the queue worker) builds the mail manager first.
        app()->forgetInstance('mail.manager');
        app('mail.manager');

        $this->assertSame('smtp', config('mail.mailers.transactional.transport'));
        $this->assertSame('smtp.hoster.by', config('mail.mailers.transactional.host'));
        $this->assertSame('no-reply@kotlov.by', config('mail.mailers.transactional.username'));
        $this->assertSame('no-reply@kotlov.by', config('mail.transactional_from.address'));
        $mail = (new \App\Mail\B2bOrderStageMail(new \App\Models\B2bOrder(['number' => 'B2B-1']), 'shipped'));
        $this->assertSame('no-reply@kotlov.by', $mail->envelope()->from->address);
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
        $this->assertSame('emails.password-reset', $message->view);
        $this->assertCount(1, $message->callbacks);

        $html = (string) $message->render();
        $this->assertStringContainsString('cid:kotlov-logo', $html);
        $this->assertStringContainsString('#DC4646', $html);
        $this->assertStringContainsString('Задать новый пароль', $html);
        $this->assertStringNotContainsString('#3490dc', mb_strtolower($html));
    }

    private function noReplyMailbox(): CrmMailbox
    {
        return CrmMailbox::query()->create([
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
    }
}
