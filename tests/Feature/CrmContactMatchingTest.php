<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmContacts\CrmContactResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\TelephonyCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmContactMatchingTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'crm-contact-matching-secret-123456';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telephony.webhook_secret', $this->secret);
    }

    public function test_incoming_call_is_matched_to_crm_contact_by_normalized_phone(): void
    {
        $counterparty = CrmCounterparty::query()->create([
            'name' => 'ООО Тестовый партнёр',
            'classification' => 'b2b_partner',
            'business_unit' => 'wholesale',
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => 'Иван Петров',
            'is_primary' => true,
        ]);
        $point = CrmContactPoint::query()->create([
            'contact_id' => $contact->id,
            'type' => 'phone',
            'value' => '+375 (44) 791-03-47',
            'is_primary' => true,
        ]);

        $this->assertSame('375447910347', $point->normalized_value);

        $this->postJson(route('telephony.a1.webhook', $this->secret), [
            'notificationType' => 'START',
            'companyId' => 732,
            'crmCallType' => 'INBOUND',
            'numberA' => '375447910347',
            'numberB' => '375291177593',
            'date' => '2026-10-12T09:57:50',
            'uuid' => 'crm-contact-match-call',
        ])->assertOk();

        $call = TelephonyCall::query()->firstOrFail();
        $this->assertSame($contact->id, $call->crm_contact_id);
        $this->assertSame($counterparty->id, $call->crm_counterparty_id);
        $this->assertSame('Иван Петров', $call->contact_name);
    }

    public function test_backfill_command_matches_existing_unidentified_calls(): void
    {
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'old-call',
            'direction' => 'inbound',
            'customer_phone' => '375291234567',
            'status' => 'answered',
            'handling_status' => 'handled',
        ]);
        $counterparty = CrmCounterparty::query()->create(['name' => 'Розничный клиент', 'classification' => 'retail']);
        $contact = CrmContact::query()->create(['counterparty_id' => $counterparty->id, 'full_name' => 'Анна']);
        CrmContactPoint::query()->create(['contact_id' => $contact->id, 'type' => 'phone', 'value' => '8 029 123-45-67']);

        $this->artisan('crm:match-telephony-calls')->assertSuccessful();

        $call->refresh();
        $this->assertSame($contact->id, $call->crm_contact_id);
        $this->assertSame($counterparty->id, $call->crm_counterparty_id);
    }

    public function test_manager_can_open_crm_resources(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($manager)
            ->get(CrmCounterpartyResource::getUrl('index', panel: 'admin'))
            ->assertOk();
        $this->actingAs($manager)
            ->get(CrmCounterpartyResource::getUrl('create', panel: 'admin'))
            ->assertOk();
        $this->actingAs($manager)
            ->get(CrmContactResource::getUrl('index', panel: 'admin'))
            ->assertOk();
        $this->actingAs($manager)
            ->get(CrmContactResource::getUrl('create', panel: 'admin'))
            ->assertOk();
    }
}
