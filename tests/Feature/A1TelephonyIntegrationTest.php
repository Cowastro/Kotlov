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
use App\Services\Telephony\A1HistoryReconciler;
use App\Services\Telephony\A1OpenApiClient;
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

    public function test_outbound_webhook_keeps_employee_client_and_company_line_separate(): void
    {
        $manager = User::factory()->create([
            'name' => 'Никита Бостан',
            'phone' => '+375 29 351-29-82',
            'role' => 'manager',
            'is_active' => true,
        ]);
        TelephonyEmployee::query()->create([
            'user_id' => $manager->id,
            'provider' => 'a1',
            'external_id' => 'nikita-a1',
            'name' => 'Никита Бостан',
            'full_number' => '375293512982',
            'short_number' => '9982',
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-OUTBOUND-CLIENT',
            'status' => 'new',
            'customer_name' => 'Внешний клиент',
            'customer_phone' => '+375 29 684-44-29',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $this->postJson(route('telephony.a1.webhook', $this->secret), [
            ...$this->startPayload(),
            'uuid' => 'outbound-party-sides',
            'crmCallType' => 'OUTBOUND',
            'numberA' => '375293512982',
            'numberB' => '375296844429',
            'lineNumber' => '375293544041',
        ])->assertOk();

        $call = TelephonyCall::query()->where('external_uuid', 'outbound-party-sides')->firstOrFail();
        $this->assertSame('375296844429', $call->customer_phone);
        $this->assertSame('375293512982', $call->employee_phone);
        $this->assertSame('375293544041', $call->line_number);
        $this->assertSame($manager->id, $call->assigned_user_id);
        $this->assertSame($order->id, $call->order_id);
    }

    public function test_outbound_history_uses_callee_as_client_and_restores_employee_company_line(): void
    {
        $manager = User::factory()->create([
            'phone' => '+375 29 351-29-82',
            'role' => 'manager',
            'is_active' => true,
        ]);
        TelephonyEmployee::query()->create([
            'user_id' => $manager->id,
            'provider' => 'a1',
            'external_id' => 'nikita-history-a1',
            'name' => 'Никита Бостан',
            'full_number' => '375293512982',
        ]);

        $call = app(A1HistoryReconciler::class)->reconcile([
            'uuid' => 'outbound-history-sides',
            'callType' => 'OUTGOING',
            'callerNumber' => '375293512982',
            'calleeNumber' => '375296844429',
            'destinationNumber' => '375296844429',
            'callTimestamp' => 1793091292,
            'callDuration' => 45,
            'conversationDuration' => 32,
            'callStatus' => 'ANSWERED',
        ]);

        $this->assertNotNull($call);
        $this->assertSame('375296844429', $call->customer_phone);
        $this->assertSame('375293512982', $call->employee_phone);
        $this->assertSame('375293544041', $call->line_number);
        $this->assertSame($manager->id, $call->assigned_user_id);
    }

    public function test_history_removes_stale_employee_identity_from_outbound_client(): void
    {
        $manager = User::factory()->create([
            'name' => 'Никита Бостан',
            'phone' => '+375 29 351-29-82',
            'role' => 'manager',
            'is_active' => true,
        ]);
        TelephonyEmployee::query()->create([
            'user_id' => $manager->id,
            'provider' => 'a1',
            'external_id' => 'nikita-stale-a1',
            'name' => 'Никита Бостан',
            'full_number' => '375293512982',
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-CORRECTED-CLIENT',
            'status' => 'new',
            'customer_name' => 'Настоящий клиент',
            'customer_phone' => '+375 29 684-44-29',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
        TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'stale-employee-client',
            'direction' => 'outbound',
            'customer_phone' => '375296844429',
            'employee_phone' => '375293512982',
            'line_number' => '375293544041',
            'matched_user_id' => $manager->id,
            'assigned_user_id' => $manager->id,
            'status' => 'ringing',
        ]);

        $this->postJson(route('telephony.a1.webhook', $this->secret), [
            ...$this->startPayload(),
            'notificationType' => 'FINISH',
            'uuid' => 'stale-employee-client',
            'crmCallType' => 'OUTBOUND',
            'numberA' => '375293512982',
            'numberB' => '375296844429',
            'lineNumber' => '375293544041',
            'duration' => 45,
            'crmCallFinishedStatus' => 'ANSWERED',
        ])->assertOk();

        $call = TelephonyCall::query()->where('external_uuid', 'stale-employee-client')->firstOrFail();
        $this->assertNull($call->matched_user_id);
        $this->assertSame($order->id, $call->order_id);
        $this->assertSame($manager->id, $call->assigned_user_id);
        $this->assertSame('375296844429', $call->customer_phone);
    }

    public function test_employee_sync_prefers_phone_and_preserves_manual_links_over_shared_email(): void
    {
        TelephonyConnection::query()->create([
            'provider' => 'a1',
            'company_id' => '732',
            'is_enabled' => true,
        ]);

        $sharedAccount = User::factory()->create([
            'name' => 'Daddy',
            'email' => 'info@kotlov.by',
            'phone' => '+375 29 000-00-00',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $natalia = User::factory()->create([
            'name' => 'Наталья Горбачевская',
            'email' => 'natalia@example.test',
            'phone' => '+375 29 156-21-02',
            'role' => 'manager',
            'is_active' => true,
        ]);
        $manualManager = User::factory()->create([
            'name' => 'Ручная привязка',
            'email' => 'manual@example.test',
            'phone' => '+375 29 777-77-77',
            'role' => 'manager',
            'is_active' => true,
        ]);

        $nataliaEmployee = TelephonyEmployee::query()->create([
            'provider' => 'a1',
            'external_id' => 'natalia-a1',
            'user_id' => $sharedAccount->id,
            'name' => 'Наталья',
            'full_number' => '375291562102',
        ]);
        $manualEmployee = TelephonyEmployee::query()->create([
            'provider' => 'a1',
            'external_id' => 'manual-a1',
            'user_id' => $manualManager->id,
            'name' => 'Менеджер',
            'full_number' => '375291111111',
        ]);

        $client = $this->mock(A1OpenApiClient::class);
        $client->shouldReceive('employees')->once()->andReturn([
            [
                'employeeId' => 'natalia-a1',
                'name' => 'Наталья',
                'surname' => 'Горбачевская',
                'email' => 'info@kotlov.by',
                'fullNumber' => '+375 29 156-21-02',
            ],
            [
                'employeeId' => 'manual-a1',
                'name' => 'Менеджер',
                'email' => 'info@kotlov.by',
                'fullNumber' => '+375 29 111-11-11',
            ],
        ]);
        $client->shouldReceive('callHistory')->once()->andReturn([]);

        $this->artisan('telephony:sync-a1')->assertSuccessful();

        $this->assertSame($natalia->id, $nataliaEmployee->fresh()->user_id);
        $this->assertSame($manualManager->id, $manualEmployee->fresh()->user_id);
    }

    public function test_natalia_data_correction_reassigns_existing_employee_and_calls(): void
    {
        $sharedAccount = User::factory()->create([
            'email' => 'info@kotlov.by',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $natalia = User::factory()->create([
            'email' => 'natalia@example.test',
            'phone' => '+375 29 156-21-02',
            'role' => 'manager',
            'is_active' => true,
        ]);
        $employee = TelephonyEmployee::query()->create([
            'provider' => 'a1',
            'external_id' => 'natalia-a1',
            'user_id' => $sharedAccount->id,
            'name' => 'Наталья',
            'full_number' => '375291562102',
        ]);
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'natalia-existing-call',
            'employee_phone' => '375291562102',
            'assigned_user_id' => $sharedAccount->id,
            'direction' => 'outbound',
            'status' => 'answered',
        ]);

        $migration = require database_path('migrations/2026_10_27_160000_fix_natalia_a1_telephony_assignment.php');
        $migration->up();

        $this->assertSame($natalia->id, $employee->fresh()->user_id);
        $this->assertSame($natalia->id, $call->fresh()->assigned_user_id);
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
