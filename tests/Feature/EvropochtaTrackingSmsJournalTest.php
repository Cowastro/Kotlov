<?php

namespace Tests\Feature;

use App\Jobs\SendEvropochtaTrackingSms;
use App\Models\Order;
use App\Services\Orders\EvropochtaTrackingSmsJournal;
use App\Services\Orders\OrderStatusSmsNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvropochtaTrackingSmsJournalTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $state): Order
    {
        Http::preventStrayRequests();
        config()->set('services.sms_by.enabled', false);
        $o = Order::create(['number' => 'SMS-TRACK', 'status' => 'processing', 'customer_name' => 'Клиент',
            'customer_phone' => '+375291110000', 'delivery_type' => 'evropochta', 'payment_type' => 'cash',
            'payment_status' => 'pending', 'subtotal' => 10, 'total' => 10]);
        $o->forceFill(['evropochta_tracking_number' => 'BY080064692225', 'evropochta_sms_status' => $state])->save();
        config(['services.sms_by.enabled' => true, 'services.sms_by.token' => 'test', 'services.sms_by.alphaname_id' => '42',
            'services.sms_by.base_url' => 'https://app.sms.by']);
        return $o;
    }

    public function test_existing_accepted_sms_is_imported_once_without_resending(): void
    {
        $o = $this->order('accepted');
        $o->forceFill(['evropochta_sms_id' => 'sms-saved', 'evropochta_sms_sent_at' => '2026-11-24 08:00:00'])->save();
        app(EvropochtaTrackingSmsJournal::class)->sync($o);
        app(EvropochtaTrackingSmsJournal::class)->sync($o);
        $message = $o->statusSms()->sole();
        $this->assertSame('accepted', $message->state);
        $this->assertSame('sms-saved', $message->sms_id);
        $this->assertSame('2026-11-24 08:00:00', $message->sent_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, app(OrderStatusSmsNotifier::class)->sendPending());
        Http::assertNothingSent();
    }

    public function test_pending_tracking_is_not_sent_by_the_status_sender(): void
    {
        $o = $this->order('pending');
        app(EvropochtaTrackingSmsJournal::class)->sync($o);
        $this->assertSame('tracking_pending', $o->statusSms()->sole()->state);
        $this->assertSame(0, app(OrderStatusSmsNotifier::class)->sendPending());
        Http::assertNothingSent();
    }

    public function test_tracking_job_updates_the_same_journal_record_and_sends_once(): void
    {
        $o = $this->order('pending');
        app(EvropochtaTrackingSmsJournal::class)->sync($o);
        Http::fake(['https://app.sms.by/api/v1/sendQuickSMS' => Http::response(['sms_id' => 'sms-new'])]);
        $job = new SendEvropochtaTrackingSms($o->id, $o->evropochta_tracking_number, '375291110000');
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        Http::assertSentCount(1);
        $this->assertSame('accepted', $o->statusSms()->sole()->state);
        $this->assertSame('sms-new', $o->statusSms()->sole()->sms_id);
    }

    public function test_uncertain_provider_result_is_logged_without_automatic_retry(): void
    {
        $o = $this->order('pending');
        Http::fake(['https://app.sms.by/api/v1/sendQuickSMS' => Http::failedConnection()]);
        $job = new SendEvropochtaTrackingSms($o->id, $o->evropochta_tracking_number, '375291110000');
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        $this->assertSame('unconfirmed', $o->statusSms()->sole()->state);
        $this->assertNull($o->statusSms()->sole()->sent_at);
    }

    public function test_tracking_sms_is_visible_in_the_order_history(): void
    {
        $o = $this->order('accepted');
        $o->forceFill(['evropochta_sms_id' => 'sms-saved', 'evropochta_sms_sent_at' => now()])->save();
        app(EvropochtaTrackingSmsJournal::class)->sync($o);
        $actor = \App\Models\User::factory()->create(['role' => 'admin', 'is_active' => true]);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \Livewire\Livewire::actingAs($actor)->test(\App\Filament\Resources\Orders\Pages\ViewOrder::class, ['record' => $o->id])
            ->assertSee('KOTLOV: трек Европочты BY080064692225')->assertSee('Принято SMS.BY')->assertSee('sms-saved');
        Http::assertNothingSent();
    }
}
