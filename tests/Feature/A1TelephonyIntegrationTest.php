<?php

namespace Tests\Feature;

use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use App\Filament\Resources\TelephonyConnections\TelephonyConnectionResource;
use App\Models\Order;
use App\Models\TelephonyCall;
use App\Models\TelephonyConnection;
use App\Models\TelephonyEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame('375447910347', TelephonyCall::firstOrFail()->customer_phone);
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
