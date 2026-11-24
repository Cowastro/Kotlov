<?php

namespace Tests\Feature;

use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCEvropochtaDeliveryMethodTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $method = 'evropochta'): array
    {
        $order = Order::create(['number' => 'EURO-TRACK', 'status' => 'processing', 'customer_name' => 'Клиент',
            'customer_phone' => '+375291110000', 'delivery_type' => $method, 'payment_type' => 'online_card',
            'payment_status' => 'paid', 'subtotal' => 10, 'total' => 10]);
        $order->forceFill(['evropochta_tracking_number' => 'TEST-TRACK-123'])->save();
        $profile = OneCOrderProfile::create(['name' => 'Розница', 'document' => 'customer', 'customer_mode' => 'retail',
            'is_active' => true, 'is_default' => true, 'header' => ['СпособДоставки' => 'ДоКлиента'], 'row_defaults' => [],
            'meta' => ['keys' => ['СпособДоставки'], 'info' => ['ДополнительнаяИнформацияПоДоставке']]]);
        return [$order, $profile, app(OneCOrderExport::class)];
    }

    public function test_europochta_defaults_to_our_transport_to_the_carrier_even_for_prepayment(): void
    {
        [$order, $profile, $export] = $this->fixture();
        $form = $export->defaults($order, $profile);
        $this->assertSame('СиламиПеревозчикаПоАдресу', $form['delivery']);
        $this->assertSame('СиламиПеревозчикаПоАдресу', $export->body($order, $profile, $form)['СпособДоставки']);
        $this->assertSame('ДоКлиента', $profile->fresh()->header['СпособДоставки']);
    }

    public function test_track_is_filled_in_form_and_document_without_duplicate_append(): void
    {
        [$order, $profile, $export] = $this->fixture();
        $form = $export->defaults($order, $profile);
        foreach (['info', 'comment'] as $field) $this->assertSame(1, substr_count($form[$field], 'TEST-TRACK-123'));
        $body = $export->body($order, $profile, $form);
        foreach (['ДополнительнаяИнформацияПоДоставке', 'Комментарий'] as $field) $this->assertSame(1, substr_count($body[$field], 'TEST-TRACK-123'));
        $body = $export->body($order, $profile, ['comment' => 'Мой комментарий'], 4);
        $this->assertStringContainsString('TEST-TRACK-123', $body['Комментарий']);
        $this->assertSame('СиламиПеревозчикаПоАдресу', $body['СпособДоставки']);
    }

    public function test_other_delivery_methods_preserve_their_defaults_and_do_not_use_old_europochta_track(): void
    {
        [$order, $profile, $export] = $this->fixture('courier');
        $form = $export->defaults($order, $profile);
        $this->assertSame('ДоКлиента', $form['delivery']);
        $this->assertStringNotContainsString('TEST-TRACK-123', $form['comment']);
        $order->delivery_type = 'pickup';
        $this->assertSame('Самовывоз', $export->defaults($order, $profile)['delivery']);
    }
}
