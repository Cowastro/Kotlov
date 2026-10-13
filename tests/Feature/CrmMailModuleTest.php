<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailboxes\CrmMailboxResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\LeadMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmMailModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_matched_to_existing_crm_contact(): void
    {
        $counterparty = CrmCounterparty::create(['name' => 'ООО Клиент']);
        $contact = CrmContact::create(['counterparty_id' => $counterparty->id, 'full_name' => 'Иван']);
        CrmContactPoint::create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'email',
            'value' => 'Sales@Example.com',
            'is_active' => true,
        ]);

        [$matchedCounterparty, $matchedContact] = app(LeadMatcher::class)->resolve('sales@example.com', null, true);

        $this->assertTrue($counterparty->is($matchedCounterparty));
        $this->assertTrue($contact->is($matchedContact));
        $this->assertSame(1, CrmCounterparty::count());
    }

    public function test_unknown_email_creates_unclassified_retail_lead_once(): void
    {
        [$counterparty, $contact] = app(LeadMatcher::class)->resolve('new.client@example.com', 'Новый клиент', true);
        [$sameCounterparty] = app(LeadMatcher::class)->resolve('NEW.CLIENT@example.com', null, true);

        $this->assertSame($counterparty->id, $sameCounterparty->id);
        $this->assertSame('lead', $counterparty->status);
        $this->assertSame('unclassified', $counterparty->classification);
        $this->assertSame('retail', $counterparty->business_unit);
        $this->assertSame('email', $counterparty->source);
        $this->assertSame($counterparty->id, $contact->counterparty_id);
        $this->assertSame(1, CrmCounterparty::count());
    }

    public function test_mailbox_password_is_encrypted_and_settings_are_admin_only(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'test-mailbox@kotlov.by',
            'username' => 'test-mailbox@kotlov.by',
            'password' => 'not-plain-text',
        ]);
        $stored = DB::table('crm_mailboxes')->where('id', $mailbox->id)->value('password');

        $this->assertNotSame('not-plain-text', $stored);
        $this->assertSame('not-plain-text', $mailbox->fresh()->password);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);

        $this->assertTrue(CrmMailThreadResource::canViewAny());
        $this->assertFalse(CrmMailboxResource::canViewAny());
    }

    public function test_taking_mail_lead_assigns_unowned_client_to_same_manager(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::create([
            'name' => 'Лид из почты',
            'status' => 'lead',
            'source' => 'email',
        ]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'leads@kotlov.by',
            'username' => 'leads@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'crm_counterparty_id' => $counterparty->id,
            'thread_key' => hash('sha256', 'lead@example.com|request'),
            'participant_email' => 'lead@example.com',
        ]);

        $thread->takeFor($manager->id);

        $this->assertSame($manager->id, $thread->fresh()->assigned_user_id);
        $this->assertSame('in_progress', $thread->fresh()->status);
        $this->assertSame($manager->id, $counterparty->fresh()->responsible_user_id);
    }

    public function test_taking_mail_lead_does_not_replace_existing_client_owner(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $otherManager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::create([
            'name' => 'Действующий клиент',
            'responsible_user_id' => $owner->id,
        ]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'crm@kotlov.by',
            'username' => 'crm@kotlov.by',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'crm_counterparty_id' => $counterparty->id,
            'thread_key' => hash('sha256', 'client@example.com|request'),
            'participant_email' => 'client@example.com',
        ]);

        $thread->takeFor($otherManager->id);

        $this->assertSame($otherManager->id, $thread->fresh()->assigned_user_id);
        $this->assertSame($owner->id, $counterparty->fresh()->responsible_user_id);
    }
}
