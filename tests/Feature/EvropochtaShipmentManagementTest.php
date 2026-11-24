<?php

namespace Tests\Feature;

use App\Filament\Resources\Evropochta\Pages\ListShipments;
use App\Models\Order;
use App\Models\User;
use App\Services\Shipping\EvropochtaPayment;
use App\Services\Shipping\EvropochtaShipmentException;
use App\Services\Shipping\EvropochtaShipmentManagement;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class EvropochtaShipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK = 'BY123456789012';
    private const URL = 'https://api-kassa.evropochta.by/api/external/postal/'.self::TRACK;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.evropochta.token' => 'test-token']);
        Mail::fake(); Queue::fake(); Http::preventStrayRequests();
        $this->manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function order(): Order
    {
        $o = Order::create(['number' => 'EURO-MANAGE', 'status' => 'processing', 'customer_name' => 'Клиент',
            'customer_phone' => '+375291234567', 'delivery_type' => 'evropochta', 'payment_type' => 'evropochta_cod',
            'payment_status' => 'pending', 'subtotal' => 70, 'delivery_price' => 7.9, 'total' => 77.9]);
        $o->forceFill(['evropochta_tracking_number' => self::TRACK])->save();
        return $o;
    }

    private function statusResponse(int $code = 0, string $name = 'Заявка создана'): array
    {
        return ['number' => self::TRACK, 'status' => ['value' => $code, 'name' => $name]];
    }

    public function test_payment_review_updates_carrier_and_snapshot_only_after_acknowledgement(): void
    {
        $o = $this->order();
        Http::fake([self::URL => function ($r) {
            return Http::response($r->method() === 'PATCH' ? ['message' => 'Внесены изменения в заявку'] : $this->statusResponse());
        }]);
        Livewire::test(ListShipments::class)->mountTableAction('updateEvropochtaPayment', $o)
            ->callMountedTableAction()->assertHasNoTableActionErrors();
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r->data() === [
            'payment_amount' => 77.9, 'shipment_payer' => 0, 'cash_on_delivery_payer' => 0,
        ]);
        Http::assertSentCount(2);
        $snapshot = $o->fresh()->evropochta_quote['shipment'];
        $this->assertEquals(77.9, $snapshot['payment_amount']);
        $this->assertSame('api_update', $snapshot['source']);
        $this->assertSame(0, $snapshot['cash_on_delivery_payer']);
        $this->assertTrue($o->statusHistory()->where('user_id', $this->manager->id)->exists());
    }

    public function test_accepted_parcel_cannot_be_edited_or_cancelled(): void
    {
        $o = $this->order();
        Http::fake([self::URL => Http::response($this->statusResponse(2, 'В пути'))]);
        foreach (['payment', 'cancel'] as $action) {
            try {
                $service = app(EvropochtaShipmentManagement::class);
                $action === 'payment' ? $service->changePayment($o, $this->manager, EvropochtaPayment::signature($o))
                    : $service->cancel($o, $this->manager);
                $this->fail('Accepted shipment must not be modified.');
            } catch (EvropochtaShipmentException $e) {
                $this->assertStringContainsString('уже приняла', $e->getMessage());
            }
        }
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
        $this->assertNull($o->fresh()->evropochta_quote);
    }

    public function test_carrier_rejection_does_not_save_a_fake_successful_payment(): void
    {
        $o = $this->order();
        Http::fake([self::URL => fn ($r) => Http::response($r->method() === 'PATCH'
            ? ['message' => 'Ошибка'] : $this->statusResponse(), $r->method() === 'PATCH' ? 422 : 200)]);
        try {
            app(EvropochtaShipmentManagement::class)->changePayment($o, $this->manager, EvropochtaPayment::signature($o));
            $this->fail('Rejected update must not be persisted.');
        } catch (EvropochtaShipmentException $e) {
            $this->assertStringContainsString('не подтвердила', $e->getMessage());
        }
        $this->assertNull($o->fresh()->evropochta_quote);
    }

    public function test_changed_total_requires_review_before_patch(): void
    {
        $o = $this->order(); $review = EvropochtaPayment::signature($o);
        $o->update(['total' => 78.9, 'delivery_price' => 8.9]);
        Http::fake([self::URL => Http::response($this->statusResponse())]);
        try {
            app(EvropochtaShipmentManagement::class)->changePayment($o, $this->manager, $review);
            $this->fail('Stale review must be rejected.');
        } catch (EvropochtaShipmentException $e) {
            $this->assertStringContainsString('изменился', $e->getMessage());
        }
        Http::assertNotSent(fn ($r) => $r->method() === 'PATCH');
    }

    public function test_refresh_is_throttled_and_updates_real_status_and_color(): void
    {
        $o = $this->order();
        Http::fake([self::URL => Http::response($this->statusResponse(2, 'В пути'))]);
        $service = app(EvropochtaShipmentManagement::class);
        $this->assertSame('В пути', $service->refresh($o, $this->manager));
        $this->assertStringContainsString('недавно', $service->refresh($o, $this->manager));
        Http::assertSentCount(1);
        $this->assertSame('transit', $o->fresh()->carrier_stage);
        $this->assertSame('warning', \App\Services\Shipping\EvropochtaShipmentSummary::color($o->fresh()));
    }

    public function test_cancellation_keeps_the_order_and_saves_carrier_attention_state(): void
    {
        $o = $this->order();
        Http::fake([self::URL => Http::response($this->statusResponse()),
            self::URL.'/cancel' => Http::response($this->statusResponse(9, 'Заявка отменена'))]);
        app(EvropochtaShipmentManagement::class)->cancel($o, $this->manager);
        $this->assertSame('processing', $o->fresh()->status);
        $this->assertSame('attention', $o->fresh()->carrier_stage);
        $this->assertSame(self::TRACK, $o->fresh()->evropochta_tracking_number);
        Http::assertSentCount(2);
    }

    public function test_filters_and_listing_do_not_generate_carrier_requests(): void
    {
        $o = $this->order();
        $o->forceFill(['carrier_stage' => 'created', 'carrier_provider' => 'evropochta',
            'carrier_reference' => self::TRACK, 'carrier_status_text' => 'Заявка создана', 'carrier_checked_at' => now()])->save();
        $page = Livewire::test(ListShipments::class)->assertSuccessful()->assertCanSeeTableRecords([$o]);
        $page->filterTable('shipment_stage', 'not_created')->assertCanNotSeeTableRecords([$o]);
        $page->filterTable('shipment_stage', 'created')->assertCanSeeTableRecords([$o]);
        Http::assertNothingSent();
    }

    public function test_disabled_manager_cannot_change_an_existing_shipment(): void
    {
        $o = $this->order();
        User::whereKey($this->manager->id)->update(['is_active' => false]);
        try {
            app(EvropochtaShipmentManagement::class)->changePayment($o, $this->manager, EvropochtaPayment::signature($o));
            $this->fail('Disabled manager must not modify the shipment.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        Http::assertNothingSent();
    }

    public function test_overlapping_request_does_not_contact_the_carrier(): void
    {
        $o = $this->order(); $lock = Cache::lock('evropochta.order-status.v2.'.self::TRACK.':request', 45); $lock->get();
        try {
            $this->assertStringContainsString('уже выполняется', app(EvropochtaShipmentManagement::class)->refresh($o, $this->manager));
        } finally { $lock->release(); }
        Http::assertNothingSent();
    }
}

