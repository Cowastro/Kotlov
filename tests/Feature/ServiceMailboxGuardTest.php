<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailboxes\Pages\ListCrmMailboxes;
use App\Models\CrmMailbox;
use App\Models\User;
use App\Services\CrmMail\CrmMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ServiceMailboxGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_mailboxes_are_excluded_from_manual_sending(): void
    {
        foreach (['order@kotlov.by', 'no-reply@kotlov.by', 'info@kotlov.by'] as $email) {
            CrmMailbox::query()->updateOrCreate(['email' => $email], [
                'name' => 'KOTLOV',
                'username' => $email,
                'password' => 'secret',
                'imap_host' => 'imap.hoster.by',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_folder' => 'INBOX',
                'smtp_host' => 'smtp.hoster.by',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
            ]);
        }

        $manualMailboxes = CrmMailbox::query()->manualSending()->pluck('email')->all();

        $this->assertContains('info@kotlov.by', $manualMailboxes);
        $this->assertNotContains('order@kotlov.by', $manualMailboxes);
        $this->assertNotContains('no-reply@kotlov.by', $manualMailboxes);

        $operatorMailboxes = CrmMailbox::query()->operatorSending()->pluck('email')->all();
        $this->assertContains('info@kotlov.by', $operatorMailboxes);
        $this->assertContains('order@kotlov.by', $operatorMailboxes);
        $this->assertNotContains('no-reply@kotlov.by', $operatorMailboxes);
    }

    public function test_manual_test_email_is_blocked_for_a_service_mailbox(): void
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV.BY',
            'email' => 'no-reply@kotlov.by',
            'username' => 'no-reply@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.hoster.by',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.hoster.by',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ручная отправка запрещена');

        app(CrmMailSender::class)->sendTest($mailbox, 'recipient@example.com', User::factory()->make());
    }

    public function test_service_mailbox_only_exposes_the_fixed_system_preview_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV.BY',
            'email' => 'order@kotlov.by',
            'username' => 'order@kotlov.by',
            'password' => 'secret',
            'imap_host' => 'imap.hoster.by',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'smtp_host' => 'smtp.hoster.by',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
        ]);

        $this->actingAs($admin);

        Livewire::test(ListCrmMailboxes::class)
            ->assertTableActionHidden('sendTest', $mailbox)
            ->assertTableActionVisible('sendServiceTest', $mailbox);
    }
}
