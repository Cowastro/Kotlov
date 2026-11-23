<?php

namespace Tests\Feature;

use App\Filament\Pages\VamExpressCalculator;
use App\Filament\Resources\VamExpressDrafts\Pages\EditVamExpressDraft;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressDraft;
use App\Services\Shipping\VamExpressDraftReference;
use App\Services\Shipping\VamExpressDrafts;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressPortal;
use App\Services\Shipping\VamExpressQuote;
use App\Services\Shipping\VamExpressReference;
use App\Services\Shipping\VamExpressSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VamExpressDraftsTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORY = 'Дымоход одностенный  *(10-15 шт.)';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
        Http::preventStrayRequests();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_reference_is_parsed_from_provider_form_without_executing_scripts(): void
    {
        $html = '<input type="hidden" name="ctl00$MainContent$hfUserID" value="349">'
            .'<input type="hidden" name="ctl00$MainContent$hfUserDiscount" value="10">'
            .'<input type="hidden" name="ctl00$MainContent$hfExpressCost" value="7.5">'
            .'<script>var availableUserProfiles = [{"value":"Профиль А","data":"2351"}];'
            .'var availableTitles = ["'.self::CATEGORY.'",]; var mail = {appkey:"lookup-secret"};'
            .'function calculateCost(){ var data = {userid:"1",apikey:"calculator-secret"}; }</script>';
        $context = (new VamExpressDraftReference)->parse($html);
        $this->assertSame('349', $context['user_id']);
        $this->assertSame('10', $context['discount']);
        $this->assertSame('Профиль А', $context['profiles'][2351]);
        $this->assertSame([self::CATEGORY], $context['goods']);
        $this->assertSame('calculator-secret', $context['calculator_key']);
        $this->expectException(VamExpressException::class);
        (new VamExpressDraftReference)->parse('<html>Login again</html>');
    }

    public function test_quote_matches_native_payload_rounds_fractional_cents_and_is_cached(): void
    {
        $this->staff();
        $this->reference();
        Http::fake(['https://vamexpress.by/WebServices/UserAPI/CalculatorAPI.asmx/Calculate' => Http::response('<string xmlns="DeliveryNetWebService">44.9999</string>')]);
        $payload = $this->payload();
        $payload['recipient_phone'] = '+375291111111';
        $quote = app(VamExpressQuote::class)->estimate($payload);
        $this->assertSame('45.00', $quote['amount']);
        $this->assertSame($quote, app(VamExpressQuote::class)->estimate($payload));
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $data = json_decode($request['jsonString'], true);

            return $data['profile_type'] === '2' && $data['user_id'] === '349' && $data['user_discount'] === '10'
                && $data['goods'] === [['description' => self::CATEGORY, 'number' => '1']]
                && ! isset($data['recipient_phone'], $data['recipient_last_name'], $data['assessed_cost'])
                && $data['express_cost'] === 0;
        });
    }

    public function test_invalid_cost_unknown_category_and_missing_assessment_never_become_zero_quotes(): void
    {
        $this->staff();
        $this->reference();
        foreach ([['goods' => [['description' => self::CATEGORY, 'number' => 1, 'declared_price' => 'abc']]],
            ['goods' => [['description' => 'Произвольный груз', 'number' => 1, 'declared_price' => 0]]],
            ['profile_id' => '3389'], ['city_id' => '999999']] as $override) {
            try {
                app(VamExpressQuote::class)->estimate(array_replace($this->payload(), $override));
                $this->fail('Invalid input must not produce a quote');
            } catch (VamExpressException|ValidationException) {
                $this->assertTrue(true);
            }
        }
        Http::assertNothingSent();
    }

    public function test_order_draft_is_idempotent_encrypted_and_quotes_invalidate_without_modifying_order(): void
    {
        $actor = $this->staff();
        $order = Order::create(['number' => 'ORD-VAM-DRAFT', 'status' => 'confirmed', 'customer_name' => 'Иван Петров',
            'customer_phone' => '291111111', 'delivery_address' => 'Адрес из заказа', 'delivery_city' => 'Кобрин', 'total' => 325]);
        $order->items()->create(['product_name' => 'Дымоход Теплов и Сухов', 'quantity' => 12, 'price' => 10]);
        $before = $order->fresh()->getAttributes();
        $service = app(VamExpressDrafts::class);
        $draft = $service->fromOrder($order, $actor);
        $this->assertSame($draft->id, $service->fromOrder($order, $actor)->id);
        $this->assertSame('Иван Петров', $draft->payload['recipient_source_name']);
        $this->assertArrayNotHasKey('recipient_last_name', $draft->payload);
        $this->assertSame('+375291111111', $draft->payload['recipient_phone']);
        $this->assertSame(1, $draft->payload['goods'][0]['number']);
        $this->assertStringNotContainsString('Иван', DB::table('vam_express_drafts')->value('payload'));
        $this->assertArrayNotHasKey('payload', $draft->toArray());
        $draft = $service->save($draft, $this->payload(), $draft->version, $actor);
        $quote = ['amount' => '45.00', 'calculated_at' => now()->toIso8601String(), 'fingerprint' => VamExpressQuote::fingerprint($draft->payload)];
        $draft = $service->attachQuote($draft, $quote, $draft->version, $actor);
        $stale = clone $draft;
        $payload = $draft->payload;
        $payload['goods'][0]['number'] = 2;
        $saved = $service->save($draft, $payload, $draft->version, $actor);
        $this->assertNull($saved->quoted_amount);
        $this->assertNull($saved->quote);
        try {
            $service->save($stale, $this->payload(), $stale->version, $actor);
            $this->fail('Concurrent overwrite must be rejected');
        } catch (ValidationException) {
            $this->assertSame(2, $saved->fresh()->payload['goods'][0]['number']);
        }
        $this->assertSame($before, $order->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_quick_calculator_saves_and_reopens_a_local_draft_and_edit_can_calculate(): void
    {
        $this->staff();
        $this->reference();
        Http::fake(['https://vamexpress.by/WebServices/UserAPI/CalculatorAPI.asmx/Calculate' => Http::response('<string>44.9999</string>')]);
        $this->get('/admin/vam-express-calculator')->assertOk();
        Livewire::test(VamExpressCalculator::class)->fillForm($this->payload())->call('calculate')->assertSet('quote.amount', '45.00')
            ->call('saveDraft')->assertHasNoFormErrors();
        $draft = VamExpressDraft::firstOrFail();
        $this->assertSame('45.00', $draft->quoted_amount);
        $this->get('/admin/vam-express-drafts/'.$draft->id.'/edit')->assertOk();
        Livewire::test(EditVamExpressDraft::class, ['record' => $draft->id])->callAction('estimate')->assertHasNoActionErrors();
        $this->assertSame('45.00', $draft->fresh()->quoted_amount);
        Http::assertSentCount(1);
    }

    public function test_clients_cannot_access_calculator_or_saved_drafts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]));
        $this->get('/admin/vam-express-calculator')->assertForbidden();
        $this->get('/admin/vam-express-drafts')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_readiness_keeps_profile_specific_documents_and_name_fields_separate(): void
    {
        $payload = $this->payload();
        $missing = app(VamExpressDrafts::class)->missing($payload);
        $this->assertContains('фамилия получателя', $missing);
        $this->assertContains('серия ТТН: 2 буквы', $missing);
        $payload['profile_id'] = '41283';
        $this->assertNotContains('серия ТТН: 2 буквы', app(VamExpressDrafts::class)->missing($payload));
    }

    public function test_reference_keys_are_encrypted_and_city_search_is_cached(): void
    {
        $this->staff();
        $this->mock(VamExpressPortal::class, function ($mock): void {
            $mock->shouldReceive('login')->once()->with('test-account', 'private-password');
            $mock->shouldReceive('draftReference')->once()->andReturn($this->context());
            $mock->shouldReceive('searchCities')->once()->with('Кобрин', 'lookup-secret')
                ->andReturn(json_encode(['suggestions' => [['data' => '65', 'value' => 'Кобрин г.', 'deliverydate' => 'вт.,чт.', 'deliveryterms' => 'до 2 дней']]], JSON_UNESCAPED_UNICODE));
        });
        $reference = app(VamExpressReference::class);
        $this->assertSame($reference->context(), $reference->context());
        $cache = Cache::get('vam-reference:'.hash('sha256', 'test-account'));
        $this->assertStringNotContainsString('calculator-secret', $cache);
        $this->assertSame([65 => 'Кобрин г.'], $reference->cities('Кобрин'));
        $this->assertSame([65 => 'Кобрин г.'], $reference->cities('Кобрин'));
        $this->assertSame('Кобрин г.', $reference->city('65')['label']);
        Http::assertNothingSent();
    }

    public function test_express_and_commission_profile_send_confirmed_fee_and_assessed_total(): void
    {
        $this->staff();
        $this->reference();
        Http::fake(['https://vamexpress.by/WebServices/UserAPI/CalculatorAPI.asmx/Calculate' => Http::response('<string>50.0199</string>')]);
        $payload = $this->payload();
        $payload['profile_id'] = '3389';
        $payload['express'] = true;
        $payload['goods'][0]['declared_price'] = '100.01';
        $payload['goods'][0]['number'] = 2;
        $this->assertSame('50.02', app(VamExpressQuote::class)->estimate($payload)['amount']);
        Http::assertSent(function ($request): bool {
            $json = json_decode($request['jsonString'], true);

            return $json['profile_type'] === '3' && $json['assessed_cost'] === '200.02' && $json['express_cost'] === '7.50';
        });
    }

    public function test_repeated_quick_save_is_idempotent_and_provider_error_is_not_a_zero_price(): void
    {
        $actor = $this->staff();
        $this->reference();
        $service = app(VamExpressDrafts::class);
        $first = $service->create($this->payload(), $actor, creationKey: 'same-click');
        $this->assertSame($first->id, $service->create($this->payload(), $actor, creationKey: 'same-click')->id);
        $this->assertSame(1, VamExpressDraft::count());
        Http::fake(['https://vamexpress.by/WebServices/UserAPI/CalculatorAPI.asmx/Calculate' => Http::response('<string>DeviationIsUnknown</string>')]);
        $this->expectException(VamExpressException::class);
        app(VamExpressQuote::class)->estimate($this->payload());
    }

    private function staff(): User
    {
        $actor = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($actor);
        app(VamExpressSettings::class)->save('test-account', 'private-password', true);

        return $actor;
    }

    private function reference(): void
    {
        $this->partialMock(VamExpressReference::class, fn ($mock) => $mock->shouldReceive('context')->andReturn($this->context()));
        Cache::put('vam-city:'.hash('sha256', 'test-account').':65', ['id' => '65', 'label' => 'Кобрин г.', 'days' => 'вт.,чт.', 'terms' => 'до 2 дней'], 3600);
    }

    private function context(): array
    {
        return ['profiles' => [2351 => 'Профиль юридического лица', 3389 => 'Договор поручения', 41283 => 'Почтовая лицензия'],
            'goods' => [self::CATEGORY], 'user_id' => '349', 'discount' => '10', 'express_cost' => '7.50',
            'lookup_key' => 'lookup-secret', 'calculator_key' => 'calculator-secret', 'calculator_user_id' => '1',
            'sender' => ['sender_city_id' => '25', 'sender_city_label' => 'Минск', 'sender_street' => 'Улица отправителя', 'sender_house' => '21']];
    }

    private function payload(): array
    {
        return array_replace(VamExpressDrafts::defaults(), ['profile_id' => '2351', 'city_id' => '65', 'express' => false,
            'goods' => [['description' => self::CATEGORY, 'model' => 'Теплов и Сухов', 'number' => 1, 'declared_price' => '0.00']]]);
    }
}
