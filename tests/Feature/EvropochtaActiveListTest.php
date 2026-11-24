<?php

namespace Tests\Feature;

use App\Filament\Resources\Evropochta\Pages\ListShipments;
use App\Models\Order;
use App\Models\User;
use App\Services\Shipping\CarrierOrderProgress;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class EvropochtaActiveListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); Queue::fake(); Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['services.evropochta.token' => 'fake']);
    }

    private function order(string $name, array $attributes = []): Order
    {
        $o = Order::create(['number' => $name, 'status' => 'processing', 'customer_name' => 'Клиент',
            'customer_phone' => '+375291234567', 'customer_email' => 'customer@example.test', 'delivery_type' => 'evropochta',
            'payment_type' => 'evropochta_cod', 'payment_status' => 'pending', 'subtotal' => 10, 'total' => 10]);
        $o->forceFill($attributes)->save();
        return $o;
    }

    public function test_default_work_tab_hides_cancelled_and_archived_but_all_remains_available(): void
    {
        $active = $this->order('ACTIVE');
        $closed = $this->order('CLOSED', ['status' => 'cancelled', 'archived_at' => now()]);
        $remote = $this->order('REMOTE-CANCEL', ['carrier_status_text' => 'Отменена']);
        $delivered = $this->order('DONE', ['status' => 'delivered']);
        $page = Livewire::test(ListShipments::class)->assertSet('activeTab', 'work')
            ->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$closed, $remote, $delivered]);
        $page->set('activeTab', 'closed')->assertCanSeeTableRecords([$closed, $remote, $delivered])->assertCanNotSeeTableRecords([$active]);
        $page->set('activeTab', 'all')->assertCanSeeTableRecords([$active, $closed, $remote, $delivered]);
        Http::assertNothingSent();
    }

    public function test_closed_track_is_verified_once_without_customer_messages_or_reopening(): void
    {
        $o = $this->order('CANCELLED', ['status' => 'cancelled', 'archived_at' => now(), 'evropochta_tracking_number' => 'BY123456789012']);
        Http::fake(['api-kassa.evropochta.by/*' => Http::response(['number' => 'BY123456789012', 'status' => ['value' => 99, 'name' => 'Отменена']])]);
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        $this->assertSame('Отменена', $o->fresh()->carrier_status_text);
        $this->assertSame('attention', $o->fresh()->carrier_stage);
        $this->assertSame('cancelled', $o->fresh()->status);
        $this->travel(31)->minutes();
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        Http::assertSentCount(1);
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('order_stage_email_claims', 0);
    }

    public function test_closed_order_still_rejects_responses_for_a_different_track(): void
    {
        $o = $this->order('CANCELLED', ['status' => 'cancelled', 'archived_at' => now(), 'evropochta_tracking_number' => 'BY123456789012']);
        app(CarrierOrderProgress::class)->euro($o, ['code' => 99, 'status' => 'Отменена'], now(), 'BY999999999999');
        $this->assertNull($o->fresh()->carrier_checked_at);
        Mail::assertNothingQueued();
    }

    public function test_unverified_track_is_not_misrepresented_as_a_created_shipment(): void
    {
        $o = $this->order('UNKNOWN', ['evropochta_tracking_number' => 'BY123456789012']);
        $page = Livewire::test(ListShipments::class)->filterTable('shipment_stage', 'created')->assertCanNotSeeTableRecords([$o]);
        $page->filterTable('shipment_stage', 'unverified')->assertCanSeeTableRecords([$o]);
        Http::assertNothingSent();
    }
    public function test_created_filter_uses_only_the_current_track_snapshot(): void
    {
        $o = $this->order('SNAPSHOT', ['evropochta_tracking_number' => 'BY123456789012',
            'evropochta_quote' => ['shipment' => ['number' => 'BY123456789012', 'status' => 'Заявка создана']]]);
        Livewire::test(ListShipments::class)->filterTable('shipment_stage', 'created')->assertCanSeeTableRecords([$o]);
        $o->forceFill(['evropochta_tracking_number' => 'BY999999999999'])->save();
        Livewire::test(ListShipments::class)->filterTable('shipment_stage', 'created')->assertCanNotSeeTableRecords([$o]);
        Http::assertNothingSent();
    }

    public function test_known_cancelled_carrier_shipment_is_not_polled_forever(): void
    {
        $o = $this->order('CARRIER-CANCEL', ['evropochta_tracking_number' => 'BY123456789012']);
        Http::fake(['api-kassa.evropochta.by/*' => Http::response(['number' => 'BY123456789012', 'status' => ['value' => 99, 'name' => 'Отменена']])]);
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        $this->assertSame('processing', $o->fresh()->status);
        $this->travel(31)->minutes();
        $this->artisan('shipping:sync-evropochta-status')->assertSuccessful();
        Http::assertSentCount(1);
    }
}
