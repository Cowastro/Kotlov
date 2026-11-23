<?php

namespace Tests\Feature;

use App\Filament\Resources\VamExpress\Pages\ListVamExpressShipments;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressShipment;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressHtml;
use App\Services\Shipping\VamExpressPortal;
use App\Services\Shipping\VamExpressProfiles;
use App\Support\Shipping\VamExpressStatus;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VamExpressProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    public function test_profile_is_read_from_the_existing_ticket_by_get_only_and_missing_profile_is_rejected(): void
    {
        $id = str_repeat('a', 32);
        Http::fakeSequence()->push('<input name="ctl00$MainContent$tbUserPass">')->push('signed in')
            ->push('<table><tr><th>Город доставки</th><th>Статус</th></tr></table>')
            ->push('<input readonly name="ctl00$MainContent$tbUserProfile" value="ООО &quot;Первый профиль&quot; / Юридическое лицо"><p>Другой отправитель</p>');
        $portal = app(VamExpressPortal::class);
        $portal->login('test', 'secret');
        $this->assertSame('ООО "Первый профиль" / Юридическое лицо', $portal->senderProfile($id));
        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://vamexpress.by/UserUI/TicketEdit.aspx?id='.$id);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), 'TicketEdit'));
        $this->expectException(VamExpressException::class);
        (new VamExpressHtml)->senderProfile('<input name="ctl00$MainContent$tbUserProfile" value="Профиль нового черновика">');
    }

    public function test_bounded_profile_fill_keeps_orders_links_and_existing_profiles(): void
    {
        $order = Order::create(['number' => 'ORD-PROFILES', 'status' => 'confirmed', 'customer_name' => 'Тест', 'customer_phone' => '+375291111111', 'total' => 128]);
        $before = $order->fresh()->getAttributes();
        $first = $this->shipment('a', 'Обработана');
        $first->forceFill(['order_id' => $order->id])->save();
        $second = $this->shipment('b', 'В пути');
        $fresh = $this->shipment('c', 'Доставлена', 'Уже проверенный профиль');
        $fresh->update(['profile_checked_at' => now()]);
        $portal = $this->mock(VamExpressPortal::class, function ($mock) use ($first, $second): void {
            $mock->shouldReceive('senderProfile')->with($first->remote_id)->once()->andReturn('Профиль А');
            $mock->shouldReceive('senderProfile')->with($second->remote_id)->once()->andReturn('Профиль Б');
        });
        $this->assertSame(2, app(VamExpressProfiles::class)->fill($portal, 2));
        $this->assertSame('Профиль А', $first->fresh()->sender_profile);
        $this->assertSame('Профиль Б', $second->fresh()->sender_profile);
        $this->assertSame('Уже проверенный профиль', $fresh->fresh()->sender_profile);
        $this->assertSame($order->id, $first->fresh()->order_id);
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_profile_status_tab_and_search_filters_intersect_and_can_be_reset(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        $wanted = $this->shipment('a', 'В пути', 'Профиль А');
        $otherProfile = $this->shipment('b', 'В пути', 'Профиль Б');
        $otherStatus = $this->shipment('c', 'Доставлена', 'Профиль А');
        Livewire::test(ListVamExpressShipments::class)
            ->filterTable('sender_profile', 'Профиль А')->set('activeTab', 'transit')->searchTable('Лида')
            ->assertCanSeeTableRecords([$wanted])->assertCanNotSeeTableRecords([$otherProfile, $otherStatus])
            ->filterTable('carrier_status', 'Доставлена')->assertCanNotSeeTableRecords([$wanted, $otherProfile, $otherStatus])
            ->callAction('resetSelection')
            ->assertCanSeeTableRecords([$wanted, $otherProfile, $otherStatus]);
    }

    public function test_statuses_have_distinct_colors_without_treating_processed_or_returns_as_delivered(): void
    {
        $this->assertNotSame(VamExpressStatus::color('Обработана'), VamExpressStatus::color('Доставлена'));
        $this->assertNotSame(VamExpressStatus::color('В пути'), VamExpressStatus::color('На складе'));
        $this->assertSame('cancelled', VamExpressStatus::group('Отказ на адресе (на складе)'));
        $this->assertSame('warning', VamExpressStatus::group('Частичный отказ (у курьера)'));
        $this->assertSame('return', VamExpressStatus::group('Дост. от кл. (на складе)'));
        $this->assertSame('unknown', VamExpressStatus::group('Неизвестный статус'));
        $this->shipment('a', 'Обработана');
        $delivered = $this->shipment('b', 'Доставлена');
        $this->shipment('c', 'Оплачено');
        $this->assertSame([$delivered->id], VamExpressStatus::scope(VamExpressShipment::query(), 'delivered')->pluck('id')->all());
    }

    private function shipment(string $prefix, string $status, ?string $profile = null): VamExpressShipment
    {
        return VamExpressShipment::create(['remote_id' => str_repeat($prefix, 32), 'display_id' => str_repeat($prefix, 7),
            'carrier_status' => $status, 'sender_profile' => $profile, 'city' => 'Лида', 'remote_created_at' => '2026-11-23']);
    }
}
