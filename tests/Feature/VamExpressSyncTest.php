<?php

namespace Tests\Feature;

use App\Filament\Resources\VamExpress\Pages\ListVamExpressShipments;
use App\Filament\Resources\VamExpress\Pages\ViewVamExpressShipment;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressShipment;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressHtml;
use App\Services\Shipping\VamExpressPortal;
use App\Services\Shipping\VamExpressSettings;
use App\Services\Shipping\VamExpressSync;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VamExpressSyncTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'abcdef01234567890123456789012345';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_parser_reads_full_identity_dates_and_money_and_rejects_changed_markup(): void
    {
        $parser = new VamExpressHtml;
        $row = $parser->rows($this->listing())[0];
        $this->assertSame(self::ID, $row['remote_id']);
        $this->assertSame('abcdef0', $row['display_id']);
        $this->assertSame('1234.50', $row['total_amount']);
        $this->assertSame('28.00', $row['delivery_cost']);
        $this->assertSame('3777.00', $parser->rows(str_replace('1 234,50', '3,777.00', $this->listing()))[0]['total_amount']);
        $this->assertSame('2026-11-23', $row['remote_created_at']);
        $this->assertNull($row['accepted_at']);
        $this->assertSame('2026-11-25', $row['dispatch_at']);
        $this->assertSame('Не обработана', $row['carrier_status']);
        $this->assertSame('ctl00$MainContent$lvDataPager$ctl00$ctl01', $parser->nextPageTarget($this->listing(), 2));
        $this->expectException(VamExpressException::class);
        $parser->rows('<html>Войдите в личный кабинет</html>');
    }

    public function test_portal_uses_only_login_list_pagination_and_search_with_fresh_form_tokens(): void
    {
        Http::fakeSequence()->push('<input name="ctl00$MainContent$tbUserPass"><input type="hidden" name="__VIEWSTATE" value="login-token">')
            ->push('signed in')->push($this->listing())->push($this->listing(false))->push($this->listing(false));
        $portal = app(VamExpressPortal::class);
        $portal->login('demo', 'secret');
        $this->assertCount(1, $portal->recent());
        $this->assertSame(self::ID, $portal->find(self::ID)[0]['remote_id']);
        Http::assertSentCount(5);
        Http::assertSent(fn ($request) => $request->url() === 'https://vamexpress.by/' && $request->method() === 'POST'
            && $request['ctl00$MainContent$tbUserPass'] === 'secret' && $request['__VIEWSTATE'] === 'login-token');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && ($request['__EVENTTARGET'] ?? null) === 'ctl00$MainContent$lvDataPager$ctl00$ctl01');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && ($request['ctl00$MainContent$stbID'] ?? null) === 'abcdef0'
            && $request['ctl00$MainContent$sddlStatus'] === '');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'TicketEdit') || str_contains((string) $request->body(), 'lbDelete'));
    }

    public function test_settings_are_admin_only_encrypted_and_never_refill_password(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/admin/vam-express-shipments')->assertOk();
        Livewire::test(ListVamExpressShipments::class)
            ->callAction('connection', ['username' => 'demo', 'password' => 'secret', 'enabled' => true])->assertHasNoActionErrors();
        $this->assertStringNotContainsString('secret', Storage::disk('local')->get('shipping/vamexpress/connection.enc'));
        Livewire::test(ListVamExpressShipments::class)->mountAction('connection')->assertSchemaStateSet(['username' => 'demo', 'password' => '']);
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        Livewire::test(ListVamExpressShipments::class)->assertActionHidden('connection');
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]));
        $this->assertFalse(VamExpressShipmentResource::canViewAny());
        $this->get('/admin/vam-express-shipments')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_repeat_sync_updates_order_progress_and_keeps_link_and_commercial_terms(): void
    {
        $settings = app(VamExpressSettings::class);
        $settings->save('demo', 'secret', true);
        $row = (new VamExpressHtml)->rows($this->listing())[0];
        $order = $this->order();
        $shipment = VamExpressShipment::create($row);
        $shipment->forceFill(['order_id' => $order->id])->save();
        $before = $order->fresh()->only(['subtotal', 'total', 'delivery_price', 'payment_status', 'delivery_type']);
        $this->mock(VamExpressPortal::class, function ($mock) use ($row): void {
            $mock->shouldReceive('login')->twice();
            $mock->shouldReceive('senderProfile')->once()->andReturn('Тестовый отправитель / Юридическое лицо');
            $mock->shouldReceive('recent')->twice()->andReturn([array_replace($row, ['carrier_status' => 'В пути', 'delivery_cost' => '37.00'])]);
        });
        app(VamExpressSync::class)->run();
        $this->assertFalse($settings->due());
        $this->travel(31)->minutes();
        app(VamExpressSync::class)->run();
        $this->assertSame(1, VamExpressShipment::count());
        $this->assertSame($order->id, $shipment->fresh()->order_id);
        $this->assertSame('В пути', $shipment->fresh()->carrier_status);
        $this->assertSame('37.00', $shipment->fresh()->delivery_cost);
        $this->assertSame('Тестовый отправитель / Юридическое лицо', $shipment->fresh()->sender_profile);
        $this->assertSame($before, $order->fresh()->only(array_keys($before)));
        $this->assertSame('В пути', $order->fresh()->customerStatusLabel());
    }

    public function test_shipment_page_queues_its_exact_id_and_displays_the_completed_check(): void
    {
        $this->freezeSecond();
        $settings = app(VamExpressSettings::class);
        $settings->save('demo', 'secret', true);
        $row = (new VamExpressHtml)->rows($this->listing())[0];
        $shipment = VamExpressShipment::create($row + ['checked_at' => now()->subHour()]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));

        $page = Livewire::test(ViewVamExpressShipment::class, ['record' => $shipment->id])
            ->assertSee('Обновить статус')
            ->assertSee($shipment->checked_at->timezone('Europe/Minsk')->format('d.m.Y H:i:s'))
            ->callAction('refreshStatus')
            ->assertSee('wire:poll.10s.visible', false)
            ->assertSee('Проверка в очереди');
        $this->assertSame([self::ID], $settings->get()['requested_ids']);
        Http::assertNothingSent();

        $this->mock(VamExpressPortal::class, function ($mock) use ($row): void {
            $mock->shouldReceive('login')->once();
            $mock->shouldReceive('recent')->once()->andReturn([]);
            $mock->shouldReceive('find')->once()->with(self::ID)->andReturn([array_replace($row, ['carrier_status' => 'В пути'])]);
            $mock->shouldReceive('senderProfile')->once()->andReturn('Тестовый отправитель');
        });
        $this->travel(1)->minute();
        app(VamExpressSync::class)->run();

        $page->call('refreshCheckedStatus')->assertSet('statusCheckRequestedAt', null)
            ->assertDontSee('Проверка в очереди')
            ->assertSee('В пути')
            ->assertSee(now('Europe/Minsk')->format('d.m.Y H:i:s'));
    }

    public function test_failed_login_preserves_existing_data_and_does_not_retry_each_minute(): void
    {
        $settings = app(VamExpressSettings::class);
        $settings->save('demo', 'secret', true);
        $settings->requestSync('abcdef0');
        $shipment = VamExpressShipment::create((new VamExpressHtml)->rows($this->listing())[0]);
        Http::fakeSequence()->push('<input name="ctl00$MainContent$tbUserPass">')->push('wrong password')->push('<html>login again</html>');
        $result = app(VamExpressSync::class)->run();
        $this->assertStringContainsString('данные входа', $result);
        $this->assertSame('Не обработана', $shipment->fresh()->carrier_status);
        $this->assertNull($shipment->fresh()->synced_at);
        $this->assertSame(['abcdef0'], $settings->get()['requested_ids']);
        $this->travel(5)->minutes();
        app(VamExpressSync::class)->run();
        Http::assertSentCount(3);
        $this->assertFalse($settings->due());
    }

    public function test_manager_can_link_and_unlink_without_changing_order_and_sync_lock_blocks_overlap(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        $order = $this->order();
        $before = $order->fresh()->getAttributes();
        $shipment = VamExpressShipment::create((new VamExpressHtml)->rows($this->listing())[0]);
        Livewire::test(ListVamExpressShipments::class)->callTableAction('linkOrder', $shipment, ['order_id' => $order->id])->assertHasNoActionErrors();
        $this->assertSame($order->id, $shipment->fresh()->order_id);
        Livewire::test(ListVamExpressShipments::class)->callTableAction('linkOrder', $shipment, ['order_id' => null])->assertHasNoActionErrors();
        $this->assertNull($shipment->fresh()->order_id);
        $before['delivery_type'] = 'vam_express';
        // Selecting a carrier saves the order and may advance updated_at.
        unset($before['updated_at']);
        $after = $order->fresh()->getAttributes();
        unset($after['updated_at']);
        $this->assertSame($before, $after);
        $lock = Cache::lock('vam-express-sync', 600);
        $lock->get();
        try {
            $this->assertSame('Обновление уже выполняется.', app(VamExpressSync::class)->run());
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
    }

    private function order(): Order
    {
        return Order::create(['number' => 'ORD-VAM-TEST', 'status' => 'processing', 'customer_name' => 'Тестовый получатель',
            'customer_phone' => '+375291111111', 'delivery_type' => 'transport', 'payment_type' => 'bank_transfer',
            'payment_status' => 'pending', 'subtotal' => 100, 'total' => 128, 'delivery_price' => 28]);
    }

    private function listing(bool $pager = true): string
    {
        $next = $pager ? '<a href="javascript:__doPostBack(\'ctl00$MainContent$lvDataPager$ctl00$ctl01\',\'\')">2</a>' : '';

        return '<form><input type="hidden" name="__VIEWSTATE" value="list-token"><table><tr><th>ID</th><th>Город доставки</th><th>Статус</th></tr>'
            .'<tr><td></td><td><a href="TicketEdit.aspx?id='.self::ID.'">abcdef0</a></td><td>Лида</td><td>Тестовый груз</td>'
            .'<td>1 234,50</td><td>28.00</td><td>23.11.2026</td><td></td><td>25.11.2026</td><td>Не обработана</td><td>Удалить</td></tr></table>'.$next.'</form>';
    }
}
