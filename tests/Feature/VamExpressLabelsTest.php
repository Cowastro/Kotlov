<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressShipment;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressLabelHtml;
use App\Services\Shipping\VamExpressSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VamExpressLabelsTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'abcdef01234567890123456789012345';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        Http::preventStrayRequests();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_label_preserves_official_layout_and_drops_scripts_attributes_and_external_css(): void
    {
        $safe = (new VamExpressLabelHtml)->extract($this->label(), 'abcdef0');
        $this->assertStringContainsString('width:7.4cm', $safe);
        $this->assertStringContainsString('Получатель &amp; компания', $safe);
        $this->assertStringNotContainsString('script', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('url(', $safe);
        $this->assertStringNotContainsString('html-header', $safe);
        $this->expectException(VamExpressException::class);
        (new VamExpressLabelHtml)->extract($this->label('1234567'), 'abcdef0');
    }

    public function test_first_print_selects_exact_remote_id_and_repeat_uses_private_cache_without_changing_order(): void
    {
        $this->staff();
        $order = Order::create(['number' => 'ORD-VAM-LABEL', 'status' => 'confirmed', 'customer_name' => 'Тест',
            'customer_phone' => '+375291111111', 'delivery_type' => 'transport', 'payment_type' => 'bank_transfer',
            'payment_status' => 'pending', 'subtotal' => 100, 'total' => 128, 'delivery_price' => 28]);
        $shipment = $this->shipment();
        $shipment->forceFill(['order_id' => $order->id])->save();
        $before = $order->fresh()->getAttributes();
        $this->fakePrint();
        $url = route('admin.vam-express.label', ['shipment' => $shipment->id, 'format' => '78']);
        $this->get($url)->assertOk()->assertSee('Печать / сохранить PDF')->assertSee('Получатель &amp; компания', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get($url)->assertOk();
        $cached = Cache::get('vam-label:'.hash('sha256', 'test-login').':'.self::ID.':78');
        $this->assertIsString($cached);
        $this->assertStringNotContainsString('Получатель', $cached);
        Http::assertSentCount(5);
        Http::assertSent(fn ($request) => ($request['ctl00$MainContent$btnPrintVinilForVinilPrinter'] ?? null) === 'Печать 78'
            && ($request['ctl00$MainContent$lvAllTickets$ctrl0$cbSelect'] ?? null) === 'on'
            && ($request['__VIEWSTATE'] ?? null) === 'print-search-token');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'TicketEdit') || str_contains((string) $request->body(), 'lbDelete'));
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertSame($order->id, $shipment->fresh()->order_id);
    }

    public function test_wrong_label_id_is_blocked_and_failure_cooldown_prevents_repeated_logins(): void
    {
        $this->staff();
        $shipment = $this->shipment();
        $this->fakePrint('1234567');
        $url = route('admin.vam-express.label', ['shipment' => $shipment->id, 'format' => 'a4']);
        $this->get($url)->assertStatus(503)->assertSee('ID на этикетке не совпадает')->assertDontSee('Получатель &amp; компания', false);
        $this->get($url)->assertStatus(503)->assertSee('Повторите через две минуты');
        Http::assertSentCount(5);
    }

    public function test_clients_and_inactive_staff_cannot_read_labels_or_make_carrier_requests(): void
    {
        $shipment = $this->shipment();
        foreach ([['client', true], ['manager', false]] as [$role, $active]) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
            $this->get(route('admin.vam-express.label', ['shipment' => $shipment->id, 'format' => 'a4']))->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_order_print_page_only_lists_shipments_linked_to_this_order(): void
    {
        $this->staff();
        $order = Order::create(['number' => 'ORD-VAM-PRINT', 'status' => 'new', 'customer_name' => 'Тест', 'customer_phone' => '+375291111111', 'total' => 100]);
        $shipment = $this->shipment();
        $shipment->forceFill(['order_id' => $order->id])->save();
        VamExpressShipment::create(['remote_id' => str_repeat('b', 32), 'display_id' => 'bbbbbbb']);
        $this->get(route('admin.orders.vam-express-labels', ['order' => $order->id]))->assertOk()
            ->assertSee('abcdef0')->assertDontSee('bbbbbbb')->assertSee('Лента 78 мм');
        Http::assertNothingSent();
    }

    public function test_unsupported_barcode_markup_is_not_silently_lost(): void
    {
        $this->expectException(VamExpressException::class);
        (new VamExpressLabelHtml)->extract(str_replace('</div>', '<img src="https://example.test/barcode"></div>', $this->label()), 'abcdef0');
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        app(VamExpressSettings::class)->save('test-login', 'test-secret', true);
    }

    private function shipment(): VamExpressShipment
    {
        return VamExpressShipment::create(['remote_id' => self::ID, 'display_id' => 'abcdef0', 'city' => 'Лида', 'carrier_status' => 'Не обработана']);
    }

    private function fakePrint(string $id = 'abcdef0'): void
    {
        Http::fakeSequence()->push('<input name="ctl00$MainContent$tbUserPass"><input type="hidden" name="__VIEWSTATE" value="login-token">')
            ->push('logged in')->push($this->listing())->push($this->listing())->push($this->label($id));
    }

    private function listing(): string
    {
        return '<input type="hidden" name="__VIEWSTATE" value="print-search-token"><table><tr><th>Город доставки</th><th>Статус</th></tr>'
            .'<tr><td><input type="checkbox" name="ctl00$MainContent$lvAllTickets$ctrl0$cbSelect"></td>'
            .'<td><a href="TicketEdit.aspx?id='.self::ID.'">abcdef0</a></td><td>Лида</td><td>Груз</td><td>0</td><td>28</td>'
            .'<td>23.11.2026</td><td></td><td>25.11.2026</td><td>Не обработана</td><td></td></tr></table>'
            .'<input type="submit" name="ctl00$MainContent$btnPrintVinil" value="Печать А4">'
            .'<input type="submit" name="ctl00$MainContent$btnPrintVinilForVinilPrinter" value="Печать 78">';
    }

    private function label(string $id = 'abcdef0'): string
    {
        return '<html><header>html-header</header><div id="thisPrint"><div style="width:7.4cm; font-size:23px; background:url(evil)" onclick="alert(1)">'
            .'<span id="lvAllPrint_ctrl0_lblSecureID">'.$id.'</span><br><b>Получатель &amp; компания</b><script>alert(1)</script></div></div></html>';
    }
}
