<?php

namespace Tests\Feature;

use App\Filament\Resources\TelephonyCalls\Pages\ListTelephonyCalls;
use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use App\Filament\Resources\TelephonyConnections\TelephonyConnectionResource;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Order;
use App\Models\TelephonyCall;
use App\Models\TelephonyConnection;
use App\Models\TelephonyEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class A1TelephonyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-telephony-secret-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telephony.webhook_secret', $this->secret);
    }

    public function test_webhook_creates_one_call_and_deduplicates_repeated_event(): void
    {
        $payload = $this->startPayload();

        $this->postJson(route('telephony.a1.webhook', $this->secret), $payload)
            ->assertOk()->assertJson(['accepted' => true]);
        $this->postJson(route('telephony.a1.webhook', $this->secret), $payload)
            ->assertOk()->assertJson(['accepted' => true]);

        $this->assertDatabaseCount('telephony_calls', 1);
        $this->assertDatabaseCount('telephony_call_events', 1);
        $call = TelephonyCall::firstOrFail();
        $this->assertSame('375447910347', $call->customer_phone);
        $this->assertSame('2026-10-12 06:57:50', $call->started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-10-12 09:57:50',
            $call->started_at->timezone('Europe/Minsk')->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-12 06:57:50',
            $call->events()->firstOrFail()->occurred_at->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_webhook_accepts_a1_connectivity_probe_without_creating_a_call(): void
    {
        $this->post(route('telephony.a1.webhook', $this->secret))
            ->assertOk()
            ->assertJson(['accepted' => true, 'probe' => true]);

        $this->assertDatabaseCount('telephony_calls', 0);
        $this->assertDatabaseCount('telephony_call_events', 0);
    }

    public function test_webhook_accepts_form_encoded_call_events(): void
    {
        $this->post(route('telephony.a1.webhook', $this->secret), $this->startPayload())
            ->assertOk()
            ->assertJson(['accepted' => true]);

        $this->assertDatabaseCount('telephony_calls', 1);
        $this->assertDatabaseCount('telephony_call_events', 1);
    }

    public function test_finish_event_marks_missed_call_for_callback(): void
    {
        $this->postJson(route('telephony.a1.webhook', $this->secret), $this->startPayload())->assertOk();
        $this->postJson(route('telephony.a1.webhook', $this->secret), [
            ...$this->startPayload(),
            'notificationType' => 'FINISH',
            'duration' => 18,
            'crmCallFinishedStatus' => 'NOT_ANSWERED',
            'isCallFinished' => true,
        ])->assertOk();

        $call = TelephonyCall::firstOrFail();
        $this->assertSame('missed', $call->status);
        $this->assertSame('callback_required', $call->handling_status);
        $this->assertSame(18, $call->duration);
        $this->assertCount(2, $call->events);
    }

    public function test_inbound_cancelled_by_a1_is_treated_as_missed_call(): void
    {
        $this->postJson(route('telephony.a1.webhook', $this->secret), $this->startPayload())->assertOk();
        $this->postJson(route('telephony.a1.webhook', $this->secret), [
            ...$this->startPayload(),
            'notificationType' => 'FINISH',
            'duration' => 0,
            'crmCallFinishedStatus' => 'CANCELLED',
            'isCallFinished' => true,
        ])->assertOk();

        $call = TelephonyCall::firstOrFail();
        $this->assertSame('missed', $call->status);
        $this->assertSame('callback_required', $call->handling_status);
    }

    public function test_webhook_links_manager_and_latest_order_by_phone(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        TelephonyEmployee::query()->create([
            'user_id' => $manager->id,
            'provider' => 'a1',
            'external_id' => '42',
            'name' => 'Менеджер',
            'full_number' => '375291177593',
            'short_number' => '201',
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-TEST-PHONE',
            'status' => 'new',
            'customer_name' => 'Клиент',
            'customer_phone' => '+375 (44) 791-03-47',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $this->postJson(route('telephony.a1.webhook', $this->secret), $this->startPayload())->assertOk();

        $call = TelephonyCall::firstOrFail();
        $this->assertSame($manager->id, $call->assigned_user_id);
        $this->assertSame($order->id, $call->order_id);
    }

    public function test_wrong_secret_and_wrong_company_are_rejected(): void
    {
        $this->postJson('/integrations/a1/telephony/wrong-secret-123456789012', $this->startPayload())->assertNotFound();

        TelephonyConnection::query()->create([
            'provider' => 'a1', 'company_id' => '999', 'is_enabled' => true,
        ]);

        $this->postJson(route('telephony.a1.webhook', $this->secret), $this->startPayload())->assertForbidden();
        $this->assertDatabaseCount('telephony_calls', 0);
    }

    public function test_manager_can_work_with_calls_but_cannot_open_a1_credentials(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        TelephonyCall::query()->create([
            'provider' => 'a1', 'external_uuid' => 'call-access', 'direction' => 'inbound',
            'status' => 'missed', 'handling_status' => 'callback_required',
        ]);

        $this->actingAs($manager)
            ->get(TelephonyCallResource::getUrl('index', panel: 'admin'))
            ->assertOk();
        $this->actingAs($manager)
            ->get(TelephonyConnectionResource::getUrl('index', panel: 'admin'))
            ->assertForbidden();
    }

    public function test_manager_can_record_callback_feedback_visible_to_the_team(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'call-manager-feedback',
            'direction' => 'inbound',
            'customer_phone' => '375291112233',
            'status' => 'missed',
            'handling_status' => 'callback_required',
        ]);

        $this->actingAs($manager);
        Livewire::test(ListTelephonyCalls::class)
            ->callTableAction('manager_feedback', $call, [
                'manager_outcome' => 'callback_later',
                'manager_note' => 'Интересует электрический котёл 12 кВт, перезвонить после 18:00.',
                'next_follow_up_at' => '2026-10-18 18:30:00',
                'convert_to_client' => false,
            ])
            ->assertHasNoTableActionErrors();

        $call->refresh();
        $this->assertSame('callback_later', $call->manager_outcome);
        $this->assertSame('callback_required', $call->handling_status);
        $this->assertSame($manager->id, $call->assigned_user_id);
        $this->assertSame($manager->id, $call->handled_by);
        $this->assertSame('2026-10-18 18:30:00', $call->next_follow_up_at->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('электрический котёл', $call->manager_note);
    }

    public function test_manager_feedback_can_convert_unknown_number_into_an_active_crm_client(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'call-convert-client',
            'direction' => 'inbound',
            'customer_phone' => '375295554433',
            'status' => 'answered',
            'handling_status' => 'callback_required',
        ]);

        $this->actingAs($manager);
        Livewire::test(ListTelephonyCalls::class)
            ->callTableAction('manager_feedback', $call, [
                'manager_outcome' => 'interested',
                'manager_note' => 'Нужен расчёт котельной и коммерческое предложение.',
                'convert_to_client' => true,
                'full_name' => 'Иван Петров',
                'company_name' => 'ООО Тёплый дом',
                'classification' => 'b2b_prospect',
                'business_unit' => 'wholesale',
                'position' => 'Директор',
            ])
            ->assertHasNoTableActionErrors();

        $call->refresh();
        $counterparty = CrmCounterparty::query()->findOrFail($call->crm_counterparty_id);
        $contact = CrmContact::query()->findOrFail($call->crm_contact_id);
        $phone = CrmContactPoint::query()->where('contact_id', $contact->id)->firstOrFail();

        $this->assertSame('converted', $call->manager_outcome);
        $this->assertSame('handled', $call->handling_status);
        $this->assertSame('active', $counterparty->status);
        $this->assertSame('ООО Тёплый дом', $counterparty->name);
        $this->assertStringContainsString('коммерческое предложение', $counterparty->notes);
        $this->assertSame('Иван Петров', $contact->full_name);
        $this->assertSame($counterparty->id, $phone->counterparty_id);
        $this->assertSame('375295554433', $phone->normalized_value);
    }

    public function test_manager_can_close_a_call_as_an_order_and_link_the_order(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = Order::query()->create([
            'number' => 'ORD-FROM-CALL',
            'status' => 'new',
            'customer_name' => 'Клиент из звонка',
            'customer_phone' => '+375 29 111-22-33',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'call-order-created',
            'direction' => 'inbound',
            'customer_phone' => '375291112233',
            'status' => 'missed',
            'handling_status' => 'callback_required',
        ]);

        $this->actingAs($manager);
        Livewire::test(ListTelephonyCalls::class)
            ->callTableAction('manager_feedback', $call, [
                'manager_outcome' => 'order_created',
                'manager_note' => 'Оформлен заказ после обратного звонка.',
                'order_id' => $order->id,
                'convert_to_client' => false,
            ])
            ->assertHasNoTableActionErrors();

        $call->refresh();
        $this->assertSame('order_created', $call->manager_outcome);
        $this->assertSame('handled', $call->handling_status);
        $this->assertSame($order->id, $call->order_id);
        $this->assertFalse(TelephonyCall::query()->needsAttention()->whereKey($call)->exists());
    }

    /** @return array<string, mixed> */
    private function startPayload(): array
    {
        return [
            'notificationType' => 'START',
            'companyId' => 732,
            'crmCallType' => 'INBOUND',
            'numberA' => '375447910347',
            'numberB' => '375291177593',
            'lineNumber' => '375291179623',
            'dialType' => 'SINGLE',
            'date' => '2026-10-12T09:57:50',
            'uuid' => 'c1885f72-4ad4-435a-aa31-b2ab86f09252',
        ];
    }
}
