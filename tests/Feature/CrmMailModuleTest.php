<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailboxes\CrmMailboxResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
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
}
